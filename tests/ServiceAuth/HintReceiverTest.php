<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Shekel\ShekelLib\ServiceAuth\Consumer\FetchEligibilitySnapshot;
use Shekel\ShekelLib\ServiceAuth\Consumer\HintDecision;
use Shekel\ShekelLib\ServiceAuth\Consumer\HintReceiver;
use Shekel\ShekelLib\ServiceAuth\Tenant\TenantResolver;
use Shekel\ShekelLib\Tests\Support\Tokens;

class HintReceiverTest extends ConsumerTestCase
{
    private function hints(): HintReceiver
    {
        return $this->app->make(HintReceiver::class);
    }

    public function test_fetch_rules(): void
    {
        Bus::fake();
        $hints = $this->hints();

        $this->assertSame('no_projection', $hints->handle(Tokens::TENANT, self::SUBJECT, 0, 'refresh')->reason);
        $this->clearPending();

        $this->seedProjection(5, true, null, 60);
        $this->assertSame(HintDecision::SKIPPED, $hints->handle(Tokens::TENANT, self::SUBJECT, 5, 'change')->outcome);
        $this->assertSame(HintDecision::SKIPPED, $hints->handle(Tokens::TENANT, self::SUBJECT, 4, 'change')->outcome);
        $this->assertSame(HintDecision::QUEUED, $hints->handle(Tokens::TENANT, self::SUBJECT, 6, 'change')->outcome);
        $this->clearPending();

        $this->seedProjection(5, true, null, 11 * 3600);
        $this->assertSame(HintDecision::SKIPPED, $hints->handle(Tokens::TENANT, self::SUBJECT, 5, 'refresh')->outcome);
        $this->seedProjection(5, true, null, 12 * 3600 + 1);
        $this->assertSame(HintDecision::QUEUED, $hints->handle(Tokens::TENANT, self::SUBJECT, 5, 'refresh')->outcome);

        Bus::assertDispatchedTimes(FetchEligibilitySnapshot::class, 3);
    }

    public function test_fetches_are_coalesced_per_subject_and_rate_limited(): void
    {
        Bus::fake();
        $hints = $this->hints();

        // A flood of forged hints with arbitrary revisions: one pending fetch.
        for ($i = 0; $i < 50; $i++) {
            $hints->handle(Tokens::TENANT, self::SUBJECT, random_int(1, 1000000), 'change');
        }
        Bus::assertDispatchedTimes(FetchEligibilitySnapshot::class, 1);

        // Another subject is independent.
        $hints->handle(Tokens::TENANT, 'other-subject', 1, 'change');
        Bus::assertDispatchedTimes(FetchEligibilitySnapshot::class, 2);

        // Once the job starts, a new hint queues again, delayed to the min interval...
        $this->assertSame(0, $hints->fetchStarting(Tokens::TENANT, self::SUBJECT));
        Carbon::setTestNow(Carbon::now()->addSeconds(3));
        $this->assertSame(HintDecision::QUEUED, $hints->handle(Tokens::TENANT, self::SUBJECT, 7, 'change')->outcome);
        Bus::assertDispatched(FetchEligibilitySnapshot::class, fn ($job) => $job->subjectId === self::SUBJECT && $job->delay === 7);
        // ...and a job that starts early anyway (sync queue, retry) is told to wait.
        $this->assertSame(7, $hints->fetchStarting(Tokens::TENANT, self::SUBJECT));
    }

    public function test_on_a_queue_that_ignores_delays_a_flood_still_fetches_once_per_interval(): void
    {
        $this->fakeSignatureApi(fn () => $this->signedSnapshot(['subject_revision' => 1]));
        $hints = $this->hints();
        for ($i = 0; $i < 20; $i++) {
            $hints->handle(Tokens::TENANT, self::SUBJECT, 1000 + $i, 'change'); // sync queue runs each job
        }
        $this->assertSame(1, $this->snapshotCalls);
    }

    public function test_a_job_that_gives_up_frees_the_pending_slot(): void
    {
        Bus::fake();
        $hints = $this->hints();
        $hints->handle(Tokens::TENANT, self::SUBJECT, 1, 'change');
        $this->assertSame(HintDecision::COALESCED, $hints->handle(Tokens::TENANT, self::SUBJECT, 2, 'change')->outcome);

        $first = null;
        Bus::assertDispatched(FetchEligibilitySnapshot::class, function ($job) use (&$first) {
            return $first = $job;
        });
        $first->failed(new \RuntimeException('max attempts'));
        $this->assertSame(HintDecision::QUEUED, $hints->handle(Tokens::TENANT, self::SUBJECT, 2, 'change')->outcome);

        // A stale job failing later does not free the newer job's slot.
        $first->failed(new \RuntimeException('again'));
        $this->assertSame(HintDecision::COALESCED, $hints->handle(Tokens::TENANT, self::SUBJECT, 3, 'change')->outcome);
    }

    public function test_random_subject_ids_are_capped_by_a_tenant_budget(): void
    {
        Bus::fake();
        config(['shekel.service_auth.hint_tenant_fetches_per_minute' => 5]);
        $hints = $this->hints();
        $outcomes = [];
        for ($i = 0; $i < 20; $i++) {
            $outcomes[] = $hints->handle(Tokens::TENANT, 'subject-'.$i, 1, 'change')->outcome;
        }
        Bus::assertDispatchedTimes(FetchEligibilitySnapshot::class, 5);
        $this->assertSame(15, count(array_filter($outcomes, fn ($o) => $o === HintDecision::RATE_LIMITED)));
        // Another tenant has its own budget.
        $this->assertSame(HintDecision::QUEUED, $hints->handle('another-tenant', 'subject-0', 1, 'change')->outcome);
    }

    public function test_a_forged_hint_only_triggers_a_fetch_and_the_projection_changes_only_from_a_verified_snapshot(): void
    {
        $this->seedProjection(5, true);
        // signature-api returns a snapshot signed by someone else.
        $this->fakeSignatureApi(fn () => \Shekel\ShekelLib\Tests\Support\Tokens::es256($this->snapshotClaims(['subject_revision' => 99]), self::SNAPSHOT_KID, \Shekel\ShekelLib\Tests\Support\TestKeys::ec('attacker')));

        $this->hints()->handle(Tokens::TENANT, self::SUBJECT, 99, 'change'); // sync queue runs the job

        $this->assertSame(1, $this->snapshotCalls);
        $this->assertSame(5, $this->store->find(Tokens::TENANT, self::SUBJECT)->subjectRevision);
        $this->assertSame('stored-jws-5', $this->store->find(Tokens::TENANT, self::SUBJECT)->jws);
    }

    public function test_the_hint_endpoint_answers_202_behind_the_middleware(): void
    {
        Bus::fake();
        $this->app->instance(TenantResolver::class, new class implements TenantResolver {
            public function isKnown(string $tenantId): bool { return true; }
            public function select(string $tenantId): void {}
        });
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);
        Route::post('/v3/internal/signatures/hint', fn (Request $r, HintReceiver $h) => $h->receive($r))
            ->middleware('service.oauth:loan-api:signature.hint,signature-api');
        $auth = ['Authorization' => 'Bearer '.Tokens::rs256(Tokens::serviceClaims())];

        $this->postJson('/v3/internal/signatures/hint', ['client_id' => Tokens::TENANT, 'subject_id' => self::SUBJECT, 'subject_revision' => 3, 'purpose' => 'change'], $auth)
            ->assertStatus(202);
        $this->postJson('/v3/internal/signatures/hint', ['client_id' => Tokens::TENANT, 'subject_id' => self::SUBJECT, 'subject_revision' => 'x', 'purpose' => 'change'], $auth)
            ->assertStatus(422);
        $this->postJson('/v3/internal/signatures/hint', ['client_id' => Tokens::TENANT, 'subject_id' => self::SUBJECT, 'subject_revision' => 3, 'purpose' => 'other'], $auth)
            ->assertStatus(422);
        $this->postJson('/v3/internal/signatures/hint', ['client_id' => '77777777-7777-4777-8777-777777777777', 'subject_id' => self::SUBJECT, 'subject_revision' => 3], $auth)
            ->assertStatus(403);
        Bus::assertDispatchedTimes(FetchEligibilitySnapshot::class, 1);
    }

    private function clearPending(): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds(11));
        $this->hints()->fetchStarting(Tokens::TENANT, self::SUBJECT);
    }
}

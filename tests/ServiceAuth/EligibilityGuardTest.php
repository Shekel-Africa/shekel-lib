<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Shekel\ShekelLib\ServiceAuth\Consumer\EligibilityGuard;
use Shekel\ShekelLib\ServiceAuth\Consumer\GuardDecision;
use Shekel\ShekelLib\Tests\Support\Tokens;

/**
 * Every row of the spec §10.2 outage matrix, for both classes, reachable and down.
 */
class EligibilityGuardTest extends ConsumerTestCase
{
    private const ACTION = 'loan.create';

    private function check(string $class): GuardDecision
    {
        return $this->app->make(EligibilityGuard::class)->check(Tokens::TENANT, self::SUBJECT, self::ACTION, $class);
    }

    private function signatureApiUp(bool $eligible = true): void
    {
        $this->fakeSignatureApi(fn () => $this->signedSnapshot(['subject_revision' => 9], [
            self::ACTION => ['eligible' => $eligible, 'valid_until' => null, 'reasons' => $eligible ? [] : ['master_agreement_unsigned'], 'basis' => []],
        ]));
    }

    private function signatureApiDown(): void
    {
        $this->fakeSignatureApi(fn () => Http::response('down', 503));
    }

    public function test_the_pure_matrix(): void
    {
        $n = EligibilityGuard::NEW_CREDIT;
        $s = EligibilityGuard::SERVICING;
        $expected = [
            // state, reachable, class => outcome
            [EligibilityGuard::STATE_VALID_ELIGIBLE, true, $n, 'allow'], [EligibilityGuard::STATE_VALID_ELIGIBLE, true, $s, 'allow'],
            [EligibilityGuard::STATE_VALID_ELIGIBLE, false, $n, 'allow'], [EligibilityGuard::STATE_VALID_ELIGIBLE, false, $s, 'allow'],
            [EligibilityGuard::STATE_VALID_INELIGIBLE, true, $n, 'deny'], [EligibilityGuard::STATE_VALID_INELIGIBLE, true, $s, 'deny'],
            [EligibilityGuard::STATE_VALID_INELIGIBLE, false, $n, 'deny'], [EligibilityGuard::STATE_VALID_INELIGIBLE, false, $s, 'allow'],
            [EligibilityGuard::STATE_EXPIRED, true, $n, 'fetch'], [EligibilityGuard::STATE_EXPIRED, true, $s, 'fetch'],
            [EligibilityGuard::STATE_EXPIRED, false, $n, 'deny'], [EligibilityGuard::STATE_EXPIRED, false, $s, 'allow'],
            [EligibilityGuard::STATE_ABSENT, true, $n, 'fetch'], [EligibilityGuard::STATE_ABSENT, true, $s, 'fetch'],
            [EligibilityGuard::STATE_ABSENT, false, $n, 'deny'], [EligibilityGuard::STATE_ABSENT, false, $s, 'allow'],
        ];
        foreach ($expected as [$state, $reachable, $class, $outcome]) {
            $this->assertSame($outcome, EligibilityGuard::matrix($state, $reachable, $class), "$state/".($reachable ? 'up' : 'down')."/$class");
        }
    }

    public function test_states(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->assertSame(EligibilityGuard::STATE_ABSENT, EligibilityGuard::state(null, self::ACTION, $now));
        $this->assertSame(EligibilityGuard::STATE_VALID_ELIGIBLE, EligibilityGuard::state($this->seedProjection(1, true, $now + 10), self::ACTION, $now));
        $this->assertSame(EligibilityGuard::STATE_EXPIRED, EligibilityGuard::state($this->seedProjection(1, true, $now), self::ACTION, $now), 'now >= valid_until');
        $this->assertSame(EligibilityGuard::STATE_EXPIRED, EligibilityGuard::state($this->seedProjection(1, true, null, 86400), self::ACTION, $now), '24h old is stale');
        $this->assertSame(EligibilityGuard::STATE_VALID_ELIGIBLE, EligibilityGuard::state($this->seedProjection(1, true, null, 86399), self::ACTION, $now));
        $this->assertSame(EligibilityGuard::STATE_VALID_INELIGIBLE, EligibilityGuard::state($this->seedProjection(1, false, $now + 3600), self::ACTION, $now), 'pending_kyc before a future deadline');
        $this->assertSame(EligibilityGuard::STATE_VALID_INELIGIBLE, EligibilityGuard::state($this->seedProjection(1, true, null, 60, false), self::ACTION, $now), 'action missing');
    }

    // Row 1: valid + eligible -> allow, no call, reachable or down.
    public function test_row_valid_eligible_allows_without_a_call(): void
    {
        foreach ([true, false] as $up) {
            $up ? $this->signatureApiUp() : $this->signatureApiDown();
            $this->seedProjection(5, true);
            foreach ([EligibilityGuard::NEW_CREDIT, EligibilityGuard::SERVICING] as $class) {
                $decision = $this->check($class);
                $this->assertTrue($decision->allowed());
                $this->assertSame('stored-jws-5', $decision->jws, 'the JWS relied on is returned as evidence');
            }
            $this->assertSame(0, $this->snapshotCalls);
        }
    }

    // Row 2, reachable: valid + ineligible -> 409 agreement_required, no call.
    public function test_row_valid_ineligible_reachable_denies_409(): void
    {
        $this->signatureApiUp();
        $this->seedProjection(5, false, Carbon::now()->getTimestamp() + 3600);
        foreach ([EligibilityGuard::NEW_CREDIT, EligibilityGuard::SERVICING] as $class) {
            $decision = $this->check($class);
            $this->assertSame(GuardDecision::DENY, $decision->outcome);
            $response = $decision->toResponse();
            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame('agreement_required', $response->getData(true)['error']);
            $this->assertSame(['master_agreement_unsigned'], $response->getData(true)['reasons']);
        }
        $this->assertSame(0, $this->snapshotCalls);
    }

    // Row 2, down: new credit deny; servicing allow.
    public function test_row_valid_ineligible_down(): void
    {
        $this->signatureApiDown();
        $this->seedProjection(5, false, null, 86400 * 2); // stale: forces a failing fetch that marks signature-api down
        $this->check(EligibilityGuard::NEW_CREDIT);
        $this->seedProjection(5, false, Carbon::now()->getTimestamp() + 3600);
        $calls = $this->snapshotCalls;

        $this->assertSame(GuardDecision::DENY, $this->check(EligibilityGuard::NEW_CREDIT)->outcome);
        $servicing = $this->check(EligibilityGuard::SERVICING);
        $this->assertSame(GuardDecision::ALLOW, $servicing->outcome);
        $this->assertNull($servicing->jws);
        $this->assertSame($calls, $this->snapshotCalls, 'no call for a valid projection');
    }

    // Row 3, reachable: expired or stale -> synchronous fetch, store, decide on the fresh snapshot.
    public function test_row_expired_or_stale_reachable_fetches(): void
    {
        foreach (['expired' => [Carbon::now()->getTimestamp() - 1, 60], 'stale' => [null, 86400]] as $name => [$validUntil, $age]) {
            $this->signatureApiUp();
            $this->snapshotCalls = 0;
            $this->seedProjection(5, true, $validUntil, $age);

            $decision = $this->check(EligibilityGuard::NEW_CREDIT);
            $this->assertTrue($decision->allowed(), $name);
            $this->assertTrue($decision->fetched);
            $this->assertSame(1, $this->snapshotCalls);
            $this->assertSame(9, $this->store->find(Tokens::TENANT, self::SUBJECT)->subjectRevision);
            $this->assertNotSame('stored-jws-5', $decision->jws);
        }
        // Fresh snapshot says ineligible -> 409.
        $this->signatureApiUp(false);
        $this->seedProjection(5, true, null, 86400);
        $this->assertSame(GuardDecision::DENY, $this->check(EligibilityGuard::SERVICING)->outcome);
    }

    // Row 3, down: new credit deny; servicing allow.
    public function test_row_expired_or_stale_down(): void
    {
        $this->signatureApiDown();
        foreach ([[Carbon::now()->getTimestamp() - 1, 60], [null, 86400]] as [$validUntil, $age]) {
            $this->seedProjection(5, true, $validUntil, $age);
            $this->assertSame(GuardDecision::DENY, $this->check(EligibilityGuard::NEW_CREDIT)->outcome);
            $this->assertSame(GuardDecision::ALLOW, $this->check(EligibilityGuard::SERVICING)->outcome);
        }
    }

    // Row 4, reachable: absent -> synchronous fetch.
    public function test_row_absent_reachable_fetches(): void
    {
        $this->signatureApiUp();
        $decision = $this->check(EligibilityGuard::NEW_CREDIT);
        $this->assertTrue($decision->allowed());
        $this->assertSame(1, $this->snapshotCalls);
        $this->assertNotNull($this->store->find(Tokens::TENANT, self::SUBJECT));
    }

    // Row 4, down: new credit deny; servicing allow.
    public function test_row_absent_down(): void
    {
        $this->signatureApiDown();
        $this->assertSame(GuardDecision::DENY, $this->check(EligibilityGuard::NEW_CREDIT)->outcome);
        $this->assertSame(GuardDecision::ALLOW, $this->check(EligibilityGuard::SERVICING)->outcome);
        $this->assertNull($this->store->find(Tokens::TENANT, self::SUBJECT));
    }

    public function test_a_rejected_snapshot_counts_as_down_and_never_allows_new_credit(): void
    {
        $this->fakeSignatureApi(fn () => $this->signedSnapshot(['aud' => 'transaction-api']));
        $this->assertSame(GuardDecision::DENY, $this->check(EligibilityGuard::NEW_CREDIT)->outcome);
    }

    public function test_deadline_scheduler_lag_denies_once_valid_until_passes(): void
    {
        $this->signatureApiDown();
        $now = Carbon::now()->getTimestamp();
        $this->seedProjection(5, true, $now + 30);
        $this->assertTrue($this->check(EligibilityGuard::NEW_CREDIT)->allowed());
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $this->assertFalse($this->check(EligibilityGuard::NEW_CREDIT)->allowed());
    }

    public function test_unknown_class_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->check('other');
    }
}

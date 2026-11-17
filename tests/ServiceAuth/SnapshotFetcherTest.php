<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Shekel\ShekelLib\ServiceAuth\Consumer\FetchResult;
use Shekel\ShekelLib\ServiceAuth\Consumer\SnapshotFetcher;
use Shekel\ShekelLib\ServiceAuth\Events\EligibilitySnapshotRejected;
use Shekel\ShekelLib\ServiceAuth\Snapshot\ApplyResult;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;

class SnapshotFetcherTest extends ConsumerTestCase
{
    private function fetch(): FetchResult
    {
        return $this->app->make(SnapshotFetcher::class)->fetch(Tokens::TENANT, self::SUBJECT);
    }

    public function test_it_fetches_with_its_own_token_verifies_and_applies(): void
    {
        $this->fakeSignatureApi(fn () => $this->signedSnapshot());

        $result = $this->fetch();

        $this->assertTrue($result->verified());
        $this->assertSame(ApplyResult::APPLIED, $result->apply->outcome);
        $this->assertSame(5, $this->store->find(Tokens::TENANT, self::SUBJECT)->subjectRevision);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::TOKEN_ENDPOINT
            && $r['scope'] === 'signature-api:eligibility.read' && $r['tenant_id'] === Tokens::TENANT);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SIGNATURE_API.'/v1/internal/eligibility/'.self::SUBJECT
            && $r->hasHeader('Authorization', 'Bearer tok-1') && !$r->hasHeader('s2s'));
    }

    public function test_a_raw_application_jwt_body_is_accepted(): void
    {
        $this->fakeSignatureApi(fn () => Http::response($this->signedSnapshot(), 200, ['Content-Type' => 'application/jwt']));
        $this->assertTrue($this->fetch()->verified());
    }

    public function test_a_401_refreshes_the_token_once(): void
    {
        $this->fakeSignatureApi(fn () => $this->snapshotCalls === 1 ? Http::response(['message' => 'Unauthenticated.'], 401) : $this->signedSnapshot());
        $this->assertTrue($this->fetch()->verified());
        $this->assertSame(2, $this->tokenCalls);
        $this->assertSame(2, $this->snapshotCalls);
    }

    public function test_a_bad_snapshot_is_discarded_and_alerted(): void
    {
        Event::fake([EligibilitySnapshotRejected::class]);
        $cases = [
            'bad signature' => Tokens::es256($this->snapshotClaims(), self::SNAPSHOT_KID, TestKeys::ec('attacker')),
            'wrong aud' => $this->signedSnapshot(['aud' => 'transaction-api']),
            'wrong sub' => $this->signedSnapshot(['sub' => 'other']),
            'wrong client_id' => $this->signedSnapshot(['client_id' => 'other']),
        ];
        foreach ($cases as $name => $jws) {
            $this->fakeSignatureApi(fn () => $jws);
            $this->assertSame(FetchResult::REJECTED, $this->fetch()->status, $name);
        }
        $this->assertNull($this->store->find(Tokens::TENANT, self::SUBJECT));
        Event::assertDispatchedTimes(EligibilitySnapshotRejected::class, 4);
    }

    public function test_404_and_outages(): void
    {
        $this->fakeSignatureApi(fn () => Http::response(['message' => 'Not found'], 404));
        $this->assertSame(FetchResult::NOT_FOUND, $this->fetch()->status);

        $fetcher = $this->app->make(SnapshotFetcher::class);
        $this->assertFalse($fetcher->recentlyUnavailable());
        $this->fakeSignatureApi(fn () => Http::response('down', 503));
        $this->assertSame(FetchResult::UNAVAILABLE, $this->fetch()->status);
        $this->assertTrue($fetcher->recentlyUnavailable());

        $this->fakeSignatureApi(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });
        $this->assertSame(FetchResult::UNAVAILABLE, $this->fetch()->status);
    }
}

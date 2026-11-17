<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Shekel\ShekelLib\ServiceAuth\Client\ServiceTokenException;
use Shekel\ShekelLib\ServiceAuth\Client\ServiceTokenProvider;
use Shekel\ShekelLib\Tests\TestCase;

class ServiceTokenProviderTest extends TestCase
{
    private int $issued = 0;

    private function fakeOp(int $expiresIn = 600): void
    {
        Http::fake([self::TOKEN_ENDPOINT => function () use ($expiresIn) {
            $this->issued++;

            return Http::response(['token_type' => 'Bearer', 'expires_in' => $expiresIn, 'access_token' => 'token-'.$this->issued]);
        }]);
    }

    private function provider(): ServiceTokenProvider
    {
        return $this->app->make(ServiceTokenProvider::class);
    }

    public function test_it_requests_a_client_credentials_token_for_one_tenant(): void
    {
        $this->fakeOp();
        $this->assertSame('token-1', $this->provider()->token(['signature-api:eligibility.read'], 'tenant-a'));

        Http::assertSent(function (ClientRequest $request) {
            return $request->url() === self::TOKEN_ENDPOINT
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === '66666666-6666-4666-8666-666666666666'
                && $request['client_secret'] === 'test-secret-value'
                && $request['scope'] === 'signature-api:eligibility.read'
                && $request['tenant_id'] === 'tenant-a';
        });
    }

    public function test_tokens_are_cached_per_sorted_scope_set_and_tenant(): void
    {
        $this->fakeOp();
        $provider = $this->provider();

        $a = $provider->token(['signature-api:trigger.fire', 'signature-api:eligibility.read'], 'tenant-a');
        $this->assertSame($a, $provider->token(['signature-api:eligibility.read', 'signature-api:trigger.fire'], 'tenant-a'));
        $this->assertNotSame($a, $provider->token(['signature-api:eligibility.read', 'signature-api:trigger.fire'], 'tenant-b'));
        $this->assertNotSame($a, $provider->token(['signature-api:eligibility.read'], 'tenant-a'));
        $this->assertSame(3, $this->issued);
    }

    public function test_a_cached_token_is_replaced_60_seconds_before_it_expires(): void
    {
        $this->fakeOp(600);
        $provider = $this->provider();
        $provider->token(['signature-api:eligibility.read'], 'tenant-a');

        Carbon::setTestNow(Carbon::now()->addSeconds(539));
        $this->assertSame('token-1', $provider->token(['signature-api:eligibility.read'], 'tenant-a'));
        Carbon::setTestNow(Carbon::now()->addSeconds(2));
        $this->assertSame('token-2', $provider->token(['signature-api:eligibility.read'], 'tenant-a'));
    }

    public function test_the_cached_token_is_encrypted_at_rest(): void
    {
        $this->fakeOp();
        $this->provider()->token(['signature-api:eligibility.read'], 'tenant-a');

        $store = Cache::store('array')->getStore();
        $values = (fn () => $this->storage)->call($store);
        foreach ($values as $entry) {
            $this->assertStringNotContainsString('token-1', serialize($entry['value'] ?? $entry));
        }
    }

    public function test_with_retry_on_401_refreshes_exactly_once(): void
    {
        $this->fakeOp();
        $provider = $this->provider();
        $seen = [];
        $response = $provider->withRetryOn401(['signature-api:eligibility.read'], 'tenant-a', function (string $token) use (&$seen) {
            $seen[] = $token;

            return new FakeStatus(count($seen) === 1 ? 401 : 200);
        });
        $this->assertSame(200, $response->status());
        $this->assertSame(['token-1', 'token-2'], $seen);

        $seen = [];
        $response = $provider->withRetryOn401(['signature-api:eligibility.read'], 'tenant-a', function (string $token) use (&$seen) {
            $seen[] = $token;

            return new FakeStatus(401);
        });
        $this->assertSame(401, $response->status());
        $this->assertCount(2, $seen, 'one retry, never a loop');
    }

    public function test_scope_rules(): void
    {
        $this->fakeOp();
        foreach ([[], ['*'], ['signature-api:eligibility.read', 'loan-api:signature.hint'], ['no-namespace']] as $scopes) {
            try {
                $this->provider()->token($scopes, 'tenant-a');
                $this->fail('accepted '.json_encode($scopes));
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->provider()->token(['signature-api:eligibility.read'], '');
    }

    public function test_an_op_error_never_leaks_the_secret(): void
    {
        Http::fake([self::TOKEN_ENDPOINT => Http::sequence()
            ->push(['error' => 'invalid_scope', 'echo' => 'test-secret-value'], 400)
            ->push(['error' => 'client_secret=test-secret-value'], 400)]);
        try {
            $this->provider()->token(['signature-api:eligibility.read'], 'tenant-a');
            $this->fail('expected an exception');
        } catch (ServiceTokenException $e) {
            $this->assertStringContainsString('invalid_scope', $e->getMessage());
            $this->assertStringNotContainsString('test-secret-value', $e->getMessage());
        }
        try {
            $this->provider()->token(['signature-api:eligibility.read'], 'tenant-a');
            $this->fail('expected an exception');
        } catch (ServiceTokenException $e) {
            $this->assertSame('Token endpoint answered 400.', $e->getMessage(), 'unknown error text is never echoed');
        }
    }
}

class FakeStatus
{
    public function __construct(private int $status)
    {
    }

    public function status(): int
    {
        return $this->status;
    }
}

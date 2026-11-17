<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Shekel\ShekelLib\ServiceAuth\Jwks\JwksException;
use Shekel\ShekelLib\ServiceAuth\Jwks\JwksKeySet;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;

class JwksKeySetTest extends TestCase
{
    private function keySet(string $alg = 'RS256', string $url = self::OP_JWKS, int $maxKeys = 16): JwksKeySet
    {
        return new JwksKeySet($url, $alg, Cache::store('array'), $this->app->make(HttpFactory::class), 'test', 2, $maxKeys);
    }

    public function test_known_kid_is_fetched_once_and_cached(): void
    {
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);
        $keys = $this->keySet();

        $this->assertSame('RS256', $keys->getKey(Tokens::KID)->getAlgorithm());
        $this->assertSame('RS256', $keys->getKey(Tokens::KID)->getAlgorithm());
        Http::assertSentCount(1);
    }

    public function test_a_flood_of_random_kids_causes_at_most_one_fetch_per_minute(): void
    {
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);
        $keys = $this->keySet();
        $keys->getKey(Tokens::KID); // warm: fetch #1, starts the cooldown

        $rejected = 0;
        for ($minute = 0; $minute < 3; $minute++) {
            for ($i = 0; $i < 200; $i++) {
                try {
                    $keys->getKey('random-'.bin2hex(random_bytes(8)));
                } catch (JwksException $e) {
                    $rejected++;
                }
            }
            Carbon::setTestNow(Carbon::now()->addSeconds(61));
        }

        $this->assertSame(600, $rejected);
        // One warm fetch, then at most one per minute of flood (the first minute is inside the cooldown).
        $this->assertLessThanOrEqual(3, count(Http::recorded()));
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(self::OP_JWKS, (string) $request->url());
        }
    }

    public function test_an_unknown_kid_is_negatively_cached_for_five_minutes(): void
    {
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);
        $keys = $this->keySet();
        $keys->getKey(Tokens::KID);
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        $this->expectJwksFailure(fn () => $keys->getKey('ghost'));          // refresh #2, then negative
        Carbon::setTestNow(Carbon::now()->addSeconds(120));
        $this->expectJwksFailure(fn () => $keys->getKey('ghost'));          // negative: no fetch
        $this->assertCount(2, Http::recorded());

        Carbon::setTestNow(Carbon::now()->addSeconds(200));            // > 5 min after marking
        $this->expectJwksFailure(fn () => $keys->getKey('ghost'));
        $this->assertCount(3, Http::recorded());
    }

    public function test_a_rotated_in_key_is_found_after_the_cooldown(): void
    {
        $second = TestKeys::jwk(TestKeys::rsa('second'), 'op-key-2', false);
        Http::fake([self::OP_JWKS => Http::sequence()
            ->push($this->opJwks())
            ->push($this->opJwks([$second]))]);
        $keys = $this->keySet();
        $keys->getKey(Tokens::KID);
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        $this->assertSame('RS256', $keys->getKey('op-key-2')->getAlgorithm());
    }

    public function test_keys_of_another_algorithm_or_type_are_never_returned(): void
    {
        Http::fake([self::OP_JWKS => Http::response(['keys' => [
            ['kty' => 'oct', 'kid' => 'hmac', 'k' => 'c2VjcmV0', 'alg' => 'HS256'],
            TestKeys::jwk(TestKeys::ec(), 'ec-in-rsa-set'),
            TestKeys::jwk(TestKeys::rsa(), 'rsa-with-wrong-alg', false) + ['alg' => 'RS512'],
            array_merge(TestKeys::jwk(TestKeys::rsa(), 'enc-key', false), ['use' => 'enc']),
            TestKeys::jwk(TestKeys::rsa(), Tokens::KID, false),
        ]])]);
        $keys = $this->keySet();

        $this->assertSame('RS256', $keys->getKey(Tokens::KID)->getAlgorithm());
        foreach (['hmac', 'ec-in-rsa-set', 'rsa-with-wrong-alg', 'enc-key'] as $kid) {
            $this->expectJwksFailure(fn () => $keys->getKey($kid), $kid);
        }
    }

    public function test_es256_set_accepts_only_p256_keys(): void
    {
        Http::fake([self::SNAPSHOT_JWKS => Http::response(['keys' => [
            TestKeys::jwk(TestKeys::rsa(), 'rsa-in-ec-set'),
            TestKeys::jwk(TestKeys::ec(), self::SNAPSHOT_KID),
        ]])]);
        $keys = $this->keySet('ES256', self::SNAPSHOT_JWKS);

        $this->assertSame('ES256', $keys->getKey(self::SNAPSHOT_KID)->getAlgorithm());
        $this->expectJwksFailure(fn () => $keys->getKey('rsa-in-ec-set'));
    }

    public function test_the_number_of_keys_is_bounded(): void
    {
        $jwks = [];
        for ($i = 0; $i < 5; $i++) {
            $jwks[] = TestKeys::jwk(TestKeys::rsa(), "k$i", false);
        }
        Http::fake([self::OP_JWKS => Http::response(['keys' => $jwks])]);
        $keys = $this->keySet('RS256', self::OP_JWKS, 3);

        $keys->getKey('k2');
        $this->expectJwksFailure(fn () => $keys->getKey('k3'));
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake([self::OP_JWKS => Http::response('', 302, ['Location' => 'https://evil.example.test/jwks'])]);

        $this->expectJwksFailure(fn () => $this->keySet()->getKey(Tokens::KID));
        foreach (Http::recorded() as [$request]) {
            $this->assertSame(self::OP_JWKS, (string) $request->url());
        }
    }

    public function test_cached_keys_survive_a_failed_periodic_refresh(): void
    {
        Http::fake([self::OP_JWKS => Http::sequence()->push($this->opJwks())->push('down', 500)]);
        $keys = $this->keySet();
        $keys->getKey(Tokens::KID);
        Carbon::setTestNow(Carbon::now()->addSeconds(3700)); // past the soft TTL

        $this->assertSame('RS256', $keys->getKey(Tokens::KID)->getAlgorithm());
        $this->assertCount(2, Http::recorded());
    }

    public function test_invalid_kids_are_rejected_without_a_fetch(): void
    {
        Http::fake();
        $this->expectJwksFailure(fn () => $this->keySet()->getKey(str_repeat('a', 200)));
        $this->expectJwksFailure(fn () => $this->keySet()->getKey('../../etc'));
        Http::assertNothingSent();
    }

    private function expectJwksFailure(callable $fn, string $message = ''): void
    {
        try {
            $fn();
        } catch (JwksException $e) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Expected JwksException '.$message);
    }
}

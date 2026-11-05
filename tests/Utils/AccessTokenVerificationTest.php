<?php

namespace Shekel\ShekelLib\Tests\Utils;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\Middleware\CheckAnyServiceAuthentication;
use Shekel\ShekelLib\Middleware\v3\ServiceAuthentication as V3ServiceAuthentication;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthConfigurationException;
use Shekel\ShekelLib\ServiceAuth\Token\AccessTokenVerifier;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;
use Shekel\ShekelLib\Utils\PassportToken;
use Shekel\ShekelLib\Utils\ShekelAuth;

/**
 * User access tokens (Passport, RS256, no kid/iss/tenant_id) are verified against the OP JWKS
 * before any claim is read.
 */
class AccessTokenVerificationTest extends TestCase
{
    private const USER_ID = '88888888-8888-4888-8888-888888888888';

    /** What the OP JWKS endpoint answers: a JWKS array, or a callable. */
    private $jwks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwks = $this->opJwks();
        Http::fake([self::OP_JWKS => fn () => is_callable($this->jwks) ? ($this->jwks)() : Http::response($this->jwks)]);
    }

    private function userClaims(array $overrides = []): array
    {
        $now = Carbon::now()->getTimestamp();

        return array_merge([
            'aud' => '99999999-9999-4999-8999-999999999999',
            'jti' => 'user-token-'.bin2hex(random_bytes(4)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'sub' => self::USER_ID,
            'scopes' => ['loan.create', 'loan.read'],
        ], $overrides);
    }

    /** A Passport-style access token: RS256, no kid. */
    private function userToken(array $overrides = [], ?array $pair = null, ?string $kid = null): string
    {
        return Tokens::rs256($this->userClaims($overrides), $kid, $pair);
    }

    private function assertRejected(string $token, string $case): void
    {
        try {
            PassportToken::getUserFromToken($token);
            $this->fail("$case: accepted");
        } catch (ShekelInvalidArgumentException $e) {
            $this->assertSame(401, $e->getCode(), $case);
        }
        $this->assertNull(PassportToken::findUserFromToken($token), $case);
    }

    public function test_a_valid_token_without_kid_is_accepted(): void
    {
        $user = PassportToken::getUserFromToken($this->userToken(['jti' => 'jti-1']));

        $this->assertSame(self::USER_ID, $user['user_id']);
        $this->assertSame('jti-1', $user['token_id']);
        $this->assertSame('99999999-9999-4999-8999-999999999999', $user['client_id']);
        $this->assertSame(['loan.create', 'loan.read'], $user['scopes']);
        $this->assertSame(Carbon::now()->getTimestamp() + 3600, $user['expiry']->getTimestamp());
    }

    public function test_a_valid_token_with_the_published_kid_is_accepted(): void
    {
        $user = PassportToken::getUserFromToken($this->userToken([], null, Tokens::KID));

        $this->assertSame(self::USER_ID, $user['user_id']);
    }

    public function test_a_token_signed_by_the_previous_jwks_key_is_accepted(): void
    {
        $this->jwks = $this->opJwks([TestKeys::jwk(TestKeys::rsa('previous'), 'op-key-0', false)]);

        $user = PassportToken::getUserFromToken($this->userToken([], TestKeys::rsa('previous')));

        $this->assertSame(self::USER_ID, $user['user_id']);
    }

    public function test_a_token_signed_by_a_newly_published_key_is_accepted_after_one_refresh(): void
    {
        $this->assertNotNull(PassportToken::findUserFromToken($this->userToken())); // warms the cache
        $this->jwks = $this->opJwks([TestKeys::jwk(TestKeys::rsa('next'), 'op-key-2', false)]);
        Carbon::setTestNow(Carbon::now()->addSeconds(61)); // past the refresh cooldown

        $this->assertNotNull(PassportToken::findUserFromToken($this->userToken([], TestKeys::rsa('next'))));
        Http::assertSentCount(2);
    }

    public function test_without_a_kid_only_the_first_few_published_keys_are_tried(): void
    {
        $extra = [];
        for ($i = 1; $i <= AccessTokenVerifier::MAX_CANDIDATE_KEYS; $i++) {
            $extra[] = TestKeys::jwk(TestKeys::rsa("extra-$i"), "op-key-extra-$i", false);
        }
        $this->jwks = $this->opJwks($extra);

        $last = AccessTokenVerifier::MAX_CANDIDATE_KEYS - 1;
        $this->assertNotNull(PassportToken::findUserFromToken($this->userToken([], TestKeys::rsa("extra-$last"))));
        $this->assertNull(PassportToken::findUserFromToken($this->userToken([], TestKeys::rsa('extra-'.AccessTokenVerifier::MAX_CANDIDATE_KEYS))));
        // With its kid, any published key is usable.
        $this->assertNotNull(PassportToken::findUserFromToken($this->userToken([], TestKeys::rsa('extra-'.AccessTokenVerifier::MAX_CANDIDATE_KEYS), 'op-key-extra-'.AccessTokenVerifier::MAX_CANDIDATE_KEYS)));
    }

    public function test_forged_and_malformed_tokens_are_rejected(): void
    {
        $valid = $this->userToken();
        [$head, , $signature] = explode('.', $valid);

        $cases = [
            'signed by an unknown key, no kid' => $this->userToken([], TestKeys::rsa('attacker')),
            'signed by an unknown key, known kid' => $this->userToken([], TestKeys::rsa('attacker'), Tokens::KID),
            'unknown kid' => $this->userToken([], null, 'not-published'),
            'payload swapped under a valid signature' => $head.'.'.JWT::urlsafeB64Encode(json_encode($this->userClaims(['scopes' => ['*']]))).'.'.$signature,
            'empty signature' => $head.'.'.explode('.', $valid)[1].'.',
            'alg none' => Tokens::unsigned(['alg' => 'none', 'typ' => 'JWT'], $this->userClaims()),
            'alg None' => Tokens::unsigned(['alg' => 'None', 'typ' => 'JWT'], $this->userClaims()),
            'HS256 keyed with the RSA public key' => Tokens::hs256($this->userClaims(), TestKeys::rsa()['public'], null),
            'HS256 keyed with the RSA public key and kid' => Tokens::hs256($this->userClaims(), TestKeys::rsa()['public']),
            'RS512 header' => Tokens::unsigned(['alg' => 'RS512'], $this->userClaims(), 'x'),
            'ES256 token' => Tokens::es256($this->userClaims(), Tokens::KID, null, ['typ' => 'JWT']),
            'expired beyond the leeway' => $this->userToken(['exp' => Carbon::now()->getTimestamp() - 61, 'iat' => Carbon::now()->getTimestamp() - 3600, 'nbf' => Carbon::now()->getTimestamp() - 3600]),
            'not yet valid' => $this->userToken(['nbf' => Carbon::now()->getTimestamp() + 120]),
            'no exp' => $this->userToken(['exp' => null]),
            'no sub' => $this->userToken(['sub' => null]),
            'scopes not a list of strings' => $this->userToken(['scopes' => 'loan.create']),
            'two segments' => 'abc.def',
            'garbage' => 'not-a-jwt',
            'empty' => '',
            'oversized' => str_repeat('a', 9000),
        ];
        foreach ($cases as $case => $token) {
            $this->assertRejected($token, $case);
        }
    }

    public function test_claims_cached_by_earlier_versions_under_the_raw_token_are_ignored(): void
    {
        $forged = Tokens::unsigned(['alg' => 'none', 'typ' => 'JWT'], $this->userClaims());
        Cache::put($forged, ['token_id' => 'x', 'client_id' => 'x', 'user_id' => self::USER_ID, 'scopes' => ['*'], 'expiry' => Carbon::now()->addDay()], 600);

        $this->assertRejected($forged, 'pre-cached forged claims');
    }

    public function test_an_unreachable_jwks_fails_closed(): void
    {
        $this->jwks = fn () => throw new ConnectionException('down');
        $this->assertRejected($this->userToken(), 'connection error');

        Cache::flush();
        $this->jwks = fn () => Http::response('down', 503);
        $this->assertRejected($this->userToken(), '503');

        Cache::flush();
        $this->jwks = fn () => Http::response(['keys' => []]);
        $this->assertRejected($this->userToken(), 'empty key set');
    }

    public function test_jwks_refreshes_for_unverifiable_tokens_are_rate_limited(): void
    {
        $this->assertNotNull(PassportToken::findUserFromToken($this->userToken()));
        for ($minute = 0; $minute < 3; $minute++) {
            Carbon::setTestNow(Carbon::now()->addSeconds(61));
            for ($i = 0; $i < 50; $i++) {
                $this->assertNull(PassportToken::findUserFromToken($this->userToken([], TestKeys::rsa('attacker'))));
            }
        }

        // The warm-up fetch plus at most one refresh per cooldown window.
        Http::assertSentCount(4);
    }

    public function test_an_unconfigured_jwks_url_is_a_configuration_error(): void
    {
        config(['shekel.service_auth.jwks_url' => null]);

        $this->expectException(ServiceAuthConfigurationException::class);
        $this->expectExceptionMessage('SERVICE_AUTH_JWKS_URL');
        PassportToken::findUserFromToken($this->userToken());
    }

    public function test_shekel_auth_verify_token_checks_the_signature_before_the_scopes(): void
    {
        $this->assertTrue(ShekelAuth::verifyToken($this->userToken(), ['loan.create']));
        $this->assertTrue(ShekelAuth::verifyAnyScope($this->userToken(), ['nope', 'loan.read']));

        foreach (['verifyToken', 'verifyAnyScope'] as $method) {
            try {
                ShekelAuth::$method($this->userToken(['scopes' => ['loan.create', 'loan.read']], TestKeys::rsa('attacker')), ['loan.create']);
                $this->fail("$method accepted a forged token");
            } catch (ShekelInvalidArgumentException $e) {
                $this->assertSame(401, $e->getCode());
            }
            try {
                ShekelAuth::$method($this->userToken(), ['admin.everything']);
                $this->fail("$method ignored a missing scope");
            } catch (ShekelInvalidArgumentException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }
    }

    public function test_the_scope_middlewares_answer_401_for_a_forged_token_and_500_when_unconfigured(): void
    {
        Route::get('/v3-scoped', fn () => 'ok')->middleware(V3ServiceAuthentication::class.':loan.create');
        Route::get('/any-scoped', fn () => 'ok')->middleware(CheckAnyServiceAuthentication::class.':loan.create');

        $this->get('/v3-scoped', ['Authorization' => 'Bearer '.$this->userToken()])->assertOk();
        $this->get('/v3-scoped', ['Authorization' => 'Bearer '.$this->userToken([], TestKeys::rsa('attacker'))])
            ->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated']);
        $this->get('/any-scoped', ['Authorization' => 'Bearer '.$this->userToken([], TestKeys::rsa('attacker'))])
            ->assertStatus(401);

        config(['shekel.service_auth.jwks_url' => '']);
        $this->get('/v3-scoped', ['Authorization' => 'Bearer '.$this->userToken()])
            ->assertStatus(500)->assertExactJson(['message' => 'Authentication is not configured']);
    }
}

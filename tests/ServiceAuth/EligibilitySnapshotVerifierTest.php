<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilitySnapshotVerifier;
use Shekel\ShekelLib\ServiceAuth\Snapshot\SemanticDigest;
use Shekel\ShekelLib\ServiceAuth\Snapshot\SnapshotVerificationException;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;

class EligibilitySnapshotVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([self::SNAPSHOT_JWKS => Http::response($this->snapshotJwks())]);
    }

    private function verify(string $jws, string $clientId = Tokens::TENANT, string $subject = self::SUBJECT)
    {
        return $this->app->make(EligibilitySnapshotVerifier::class)->verify($jws, $clientId, $subject);
    }

    public function test_a_valid_snapshot_verifies_into_the_dto(): void
    {
        $claims = $this->snapshotClaims();
        $jws = Tokens::es256($claims, self::SNAPSHOT_KID);
        $snapshot = $this->verify($jws);

        $this->assertSame($jws, $snapshot->jws);
        $this->assertSame(5, $snapshot->subjectRevision);
        $this->assertSame(self::SUBJECT, $snapshot->subjectId);
        $this->assertSame(Tokens::TENANT, $snapshot->clientId);
        $this->assertSame($claims['evaluated_at'], $snapshot->evaluatedAt);
        $this->assertTrue($snapshot->snapshot['actions']['loan.create']['eligible']);
        $this->assertSame(SemanticDigest::of($claims['snapshot']), $snapshot->semanticDigest);
    }

    public function test_interop_es256_signed_by_openssl_with_manual_der_to_r_s_conversion(): void
    {
        // Several signatures so r or s needing left-padding / sign-byte removal are exercised.
        for ($i = 0; $i < 20; $i++) {
            $jws = Tokens::es256ViaOpensslDer($this->snapshotClaims(['jti' => "interop-$i"]), self::SNAPSHOT_KID);
            $this->assertSame(64, strlen(\Firebase\JWT\JWT::urlsafeB64Decode(explode('.', $jws)[2])));
            $this->assertSame(5, $this->verify($jws)->subjectRevision);
        }
    }

    public function test_rejections(): void
    {
        $now = Carbon::now()->getTimestamp();
        $valid = $this->snapshotClaims();
        $cases = [
            'bad signature' => $this->tamper(Tokens::es256($valid, self::SNAPSHOT_KID)),
            'signed by another EC key' => Tokens::es256($valid, self::SNAPSHOT_KID, TestKeys::ec('attacker')),
            'alg none' => Tokens::unsigned(['alg' => 'none', 'kid' => self::SNAPSHOT_KID, 'typ' => 'eligibility+jwt'], $valid),
            'RS256' => Tokens::rs256($valid, self::SNAPSHOT_KID, null, ['typ' => 'eligibility+jwt']),
            'HS256 with the public key' => Tokens::hs256($valid, TestKeys::ec()['public'], self::SNAPSHOT_KID),
            'wrong typ' => Tokens::es256($valid, self::SNAPSHOT_KID, null, ['typ' => 'JWT']),
            'no typ' => Tokens::es256($valid, self::SNAPSHOT_KID, null, []),
            'unknown kid' => Tokens::es256($valid, 'other'),
            'wrong iss' => Tokens::es256($this->snapshotClaims(['iss' => 'someone-else']), self::SNAPSHOT_KID),
            'wrong aud' => Tokens::es256($this->snapshotClaims(['aud' => 'transaction-api']), self::SNAPSHOT_KID),
            'wrong sub' => Tokens::es256($this->snapshotClaims(['sub' => 'another-subject']), self::SNAPSHOT_KID),
            'wrong client_id' => Tokens::es256($this->snapshotClaims(['client_id' => 'another-tenant']), self::SNAPSHOT_KID),
            'expired' => Tokens::es256($this->snapshotClaims(['evaluated_at' => $now - 90000, 'exp' => $now - 3600]), self::SNAPSHOT_KID),
            'exp shorter than evaluated_at + 24h' => Tokens::es256($this->snapshotClaims(['exp' => $now + 5]), self::SNAPSHOT_KID),
            'exp longer than evaluated_at + 24h' => Tokens::es256($this->snapshotClaims(['exp' => $now + 86400 * 30]), self::SNAPSHOT_KID),
            'no exp' => Tokens::es256(array_diff_key($valid, ['exp' => 1]), self::SNAPSHOT_KID),
            'revision not an int' => Tokens::es256($this->snapshotClaims(['subject_revision' => '5']), self::SNAPSHOT_KID),
            'no snapshot' => Tokens::es256(array_diff_key($valid, ['snapshot' => 1]), self::SNAPSHOT_KID),
        ];

        foreach ($cases as $name => $jws) {
            try {
                $this->verify($jws);
                $this->fail("accepted: $name");
            } catch (SnapshotVerificationException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_semantic_digest_ignores_key_order_but_not_content(): void
    {
        $a = ['actions' => ['x' => ['eligible' => true, 'valid_until' => null, 'reasons' => [], 'basis' => []]], 'role' => 'agent'];
        $b = ['role' => 'agent', 'actions' => ['x' => ['basis' => [], 'reasons' => [], 'valid_until' => null, 'eligible' => true]]];
        $c = ['role' => 'agent', 'actions' => ['x' => ['basis' => [], 'reasons' => [], 'valid_until' => 1900000000, 'eligible' => true]]];

        $this->assertSame(SemanticDigest::of($a), SemanticDigest::of($b));
        $this->assertNotSame(SemanticDigest::of($a), SemanticDigest::of($c));
    }

    private function tamper(string $jws): string
    {
        [$h, $p, $s] = explode('.', $jws);
        $claims = json_decode(\Firebase\JWT\JWT::urlsafeB64Decode($p), true);
        $claims['snapshot']['actions']['loan.create']['eligible'] = false;

        return $h.'.'.\Firebase\JWT\JWT::urlsafeB64Encode(json_encode($claims)).'.'.$s;
    }
}

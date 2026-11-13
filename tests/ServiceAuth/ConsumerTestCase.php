<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection;
use Shekel\ShekelLib\ServiceAuth\Snapshot\SemanticDigest;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;

abstract class ConsumerTestCase extends TestCase
{
    protected int $snapshotCalls = 0;
    protected int $tokenCalls = 0;

    /**
     * Fakes the OP token endpoint, signature-api's JWKS and its eligibility endpoint. $snapshot
     * returns the JWS (string) or a response for each call.
     */
    protected function fakeSignatureApi(callable $snapshot): void
    {
        // A fresh factory: Http::fake() stubs accumulate and the first match wins.
        Http::swap(new HttpFactory());
        Http::fake([
            self::TOKEN_ENDPOINT => function () {
                $this->tokenCalls++;

                return Http::response(['access_token' => 'tok-'.$this->tokenCalls, 'expires_in' => 600]);
            },
            self::SNAPSHOT_JWKS => Http::response($this->snapshotJwks()),
            self::SIGNATURE_API.'/v1/internal/eligibility/*' => function ($request) use ($snapshot) {
                $this->snapshotCalls++;
                $result = $snapshot($request);

                return is_string($result) ? Http::response(['jws' => $result]) : $result;
            },
        ]);
    }

    protected function signedSnapshot(array $overrides = [], ?array $actions = null): string
    {
        return Tokens::es256($this->snapshotClaims($overrides, $actions), self::SNAPSHOT_KID);
    }

    protected function seedProjection(int $revision, bool $eligible, ?int $validUntil = null, int $age = 60, bool $withAction = true): EligibilityProjection
    {
        $now = Carbon::now()->getTimestamp();
        $actions = $withAction ? ['loan.create' => ['eligible' => $eligible, 'valid_until' => $validUntil, 'reasons' => $eligible ? [] : ['master_agreement_unsigned'], 'basis' => []]] : [];
        $snapshot = ['actions' => $actions, 'role' => 'local_dealer', 'enforcement_state' => 'enforce', 'enforcement_revision' => 1];
        $projection = new EligibilityProjection(Tokens::TENANT, self::SUBJECT, $revision, $snapshot, SemanticDigest::of($snapshot), $now - $age, $now - $age, "stored-jws-$revision");
        $this->store->rows[$projection->clientId.'|'.$projection->subjectId] = $projection;

        return $projection;
    }
}

<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

use Shekel\ShekelLib\ServiceAuth\Jwks\JwksKeySet;
use Shekel\ShekelLib\ServiceAuth\Support\Jwt;
use Throwable;

/**
 * Verifies a signature-api eligibility snapshot (compact JWS, spec §10.1): ES256 pinned,
 * typ = eligibility+jwt, kid looked up in signature-api's JWKS only, iss = signature-api,
 * aud = this service, sub and client_id = what was requested, exp (with leeway) and
 * exp = evaluated_at + 24h.
 */
class EligibilitySnapshotVerifier
{
    public const ALGORITHM = 'ES256';
    public const TYPE = 'eligibility+jwt';
    public const LIFETIME = 86400;

    public function __construct(
        private JwksKeySet $keys,
        private string $issuer,
        private string $audience,
        private int $leeway = 60,
    ) {
        if ($keys->algorithm() !== self::ALGORITHM) {
            throw new \InvalidArgumentException('The snapshot key set must be ES256.');
        }
    }

    /**
     * @throws SnapshotVerificationException
     */
    public function verify(string $jws, string $expectedClientId, string $expectedSubjectId): VerifiedSnapshot
    {
        if ($this->issuer === '' || $this->audience === '') {
            throw new SnapshotVerificationException('verifier_not_configured');
        }
        if ($jws === '' || strlen($jws) > 65536) {
            throw new SnapshotVerificationException('malformed_jws');
        }
        try {
            $header = Jwt::header($jws);
        } catch (Throwable $e) {
            throw new SnapshotVerificationException('malformed_jws');
        }
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw new SnapshotVerificationException('alg_not_allowed');
        }
        if (($header['typ'] ?? null) !== self::TYPE) {
            throw new SnapshotVerificationException('wrong_typ');
        }
        if (!isset($header['kid']) || !is_string($header['kid']) || $header['kid'] === '') {
            throw new SnapshotVerificationException('missing_kid');
        }
        try {
            $key = $this->keys->getKey($header['kid']);
        } catch (Throwable $e) {
            throw new SnapshotVerificationException('unknown_kid');
        }
        try {
            $claims = Jwt::decode($jws, $key, $this->leeway);
        } catch (Throwable $e) {
            throw new SnapshotVerificationException('invalid_signature_or_time');
        }

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new SnapshotVerificationException('wrong_iss');
        }
        $aud = $claims['aud'] ?? null;
        if (!(is_string($aud) && $aud === $this->audience) && !(is_array($aud) && in_array($this->audience, $aud, true))) {
            throw new SnapshotVerificationException('wrong_aud');
        }
        if (($claims['sub'] ?? null) !== $expectedSubjectId) {
            throw new SnapshotVerificationException('wrong_sub');
        }
        if (($claims['client_id'] ?? null) !== $expectedClientId) {
            throw new SnapshotVerificationException('wrong_client_id');
        }
        foreach (['exp', 'iat', 'evaluated_at', 'subject_revision'] as $name) {
            if (!isset($claims[$name]) || !is_int($claims[$name])) {
                throw new SnapshotVerificationException("missing_$name");
            }
        }
        if ($claims['exp'] !== $claims['evaluated_at'] + self::LIFETIME) {
            // The guard treats a projection as valid for 24h after evaluated_at; a shorter-lived
            // snapshot must not outlive its own exp.
            throw new SnapshotVerificationException('unexpected_exp');
        }
        if ($claims['subject_revision'] < 0) {
            throw new SnapshotVerificationException('invalid_subject_revision');
        }
        $snapshot = $claims['snapshot'] ?? null;
        if (!is_array($snapshot) || !isset($snapshot['actions']) || !is_array($snapshot['actions'])) {
            throw new SnapshotVerificationException('missing_snapshot');
        }

        return new VerifiedSnapshot(
            $jws,
            $header['kid'],
            $expectedClientId,
            $expectedSubjectId,
            $claims['subject_revision'],
            $claims['evaluated_at'],
            $claims['iat'],
            $claims['exp'],
            isset($claims['jti']) && is_string($claims['jti']) ? $claims['jti'] : null,
            $snapshot,
            SemanticDigest::of($snapshot),
        );
    }
}

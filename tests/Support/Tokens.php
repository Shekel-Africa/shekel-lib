<?php

namespace Shekel\ShekelLib\Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;

/**
 * Builds signed test tokens. Hand-rolled segments where the library would refuse to make the
 * malicious token (alg=none, HS256 with a public key).
 */
final class Tokens
{
    public const ISSUER = 'https://auth.example.test';
    public const KID = 'op-key-1';
    public const CALLER_CLIENT_ID = '11111111-1111-4111-8111-111111111111';
    public const TENANT = '22222222-2222-4222-8222-222222222222';

    public static function serviceClaims(array $overrides = []): array
    {
        $now = Carbon::now()->getTimestamp();

        return array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CALLER_CLIENT_ID,
            'jti' => 'jti-'.bin2hex(random_bytes(4)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 600,
            'sub' => '',
            'scopes' => ['loan-api:signature.hint'],
            'tenant_id' => self::TENANT,
            'client_kind' => 'service',
        ], $overrides);
    }

    public static function rs256(array $claims, ?string $kid = self::KID, ?array $pair = null, array $header = []): string
    {
        $pair ??= TestKeys::rsa();
        $head = $header + ['typ' => 'JWT'];

        return JWT::encode(array_filter($claims, fn ($v) => $v !== null), $pair['private'], 'RS256', $kid, $head);
    }

    public static function es256(array $claims, string $kid, ?array $pair = null, array $header = ['typ' => 'eligibility+jwt']): string
    {
        $pair ??= TestKeys::ec();

        return JWT::encode($claims, $pair['private'], 'ES256', $kid, $header);
    }

    public static function unsigned(array $header, array $claims, string $signature = ''): string
    {
        return JWT::urlsafeB64Encode(json_encode($header)).'.'.JWT::urlsafeB64Encode(json_encode($claims)).'.'.JWT::urlsafeB64Encode($signature);
    }

    public static function hs256(array $claims, string $secret, ?string $kid = self::KID): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'] + ($kid !== null ? ['kid' => $kid] : []);
        $input = JWT::urlsafeB64Encode(json_encode($header)).'.'.JWT::urlsafeB64Encode(json_encode($claims));

        return $input.'.'.JWT::urlsafeB64Encode(hash_hmac('sha256', $input, $secret, true));
    }

    /**
     * ES256 the way a KMS-style signer does it: openssl produces DER, converted here by hand to
     * the 64-byte r||s JOSE form (left-padded, sign byte dropped).
     */
    public static function es256ViaOpensslDer(array $claims, string $kid, ?array $pair = null): string
    {
        $pair ??= TestKeys::ec();
        $input = JWT::urlsafeB64Encode(json_encode(['alg' => 'ES256', 'kid' => $kid, 'typ' => 'eligibility+jwt']))
            .'.'.JWT::urlsafeB64Encode(json_encode($claims));
        openssl_sign($input, $der, $pair['private'], OPENSSL_ALGO_SHA256);

        return $input.'.'.JWT::urlsafeB64Encode(self::derToRawSignature($der));
    }

    public static function derToRawSignature(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('not a DER sequence');
        }
        $length = ord($der[$offset++]);
        if ($length & 0x80) {
            $offset += $length & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$offset++]) !== 0x02) {
                throw new \RuntimeException('not a DER integer');
            }
            $len = ord($der[$offset++]);
            $int = substr($der, $offset, $len);
            $offset += $len;
            $int = ltrim($int, "\0");
            $parts[] = str_pad($int, 32, "\0", STR_PAD_LEFT);
        }

        return $parts[0].$parts[1];
    }
}

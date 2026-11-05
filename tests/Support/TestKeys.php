<?php

namespace Shekel\ShekelLib\Tests\Support;

use Firebase\JWT\JWT;

/**
 * RSA and EC P-256 key pairs generated with openssl for the test run, as PEM and public JWK.
 */
final class TestKeys
{
    private static array $keys = [];

    public static function rsa(string $name = 'op'): array
    {
        return self::$keys['rsa:'.$name] ??= self::generate(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048], 'RS256');
    }

    public static function ec(string $name = 'snapshot'): array
    {
        return self::$keys['ec:'.$name] ??= self::generate(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'], 'ES256');
    }

    public static function jwk(array $pair, string $kid, bool $withAlg = true): array
    {
        $jwk = $pair['jwk'] + ['kid' => $kid, 'use' => 'sig'];
        if ($withAlg) {
            $jwk['alg'] = $pair['alg'];
        }

        return $jwk;
    }

    private static function generate(array $options, string $alg): array
    {
        $resource = openssl_pkey_new($options);
        if ($resource === false) {
            throw new \RuntimeException('openssl_pkey_new failed: '.openssl_error_string());
        }
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);
        if ($alg === 'RS256') {
            $jwk = ['kty' => 'RSA', 'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])];
        } else {
            $jwk = [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => JWT::urlsafeB64Encode(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
                'y' => JWT::urlsafeB64Encode(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
            ];
        }

        return ['private' => $privatePem, 'public' => $details['key'], 'jwk' => $jwk, 'alg' => $alg];
    }
}

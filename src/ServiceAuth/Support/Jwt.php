<?php

namespace Shekel\ShekelLib\ServiceAuth\Support;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use UnexpectedValueException;

/**
 * Thin wrapper over firebase/php-jwt: reads the protected header without trusting it, and decodes
 * with a pinned key, a leeway and Carbon's clock without leaking either into the library's static
 * state for other callers.
 */
final class Jwt
{
    /**
     * The decoded protected header of a compact JWS. Throws if the token is not three segments or
     * the header is not a JSON object.
     */
    public static function header(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }
        $json = FirebaseJwt::urlsafeB64Decode($parts[0]);
        $header = json_decode($json, true);
        if (!is_array($header) || self::isList($header)) {
            throw new UnexpectedValueException('Invalid header encoding');
        }

        return $header;
    }

    /**
     * Verify the signature with $key (its algorithm must equal the header's) and the time claims
     * with $leeway seconds; returns the claims as an array.
     */
    public static function decode(string $jwt, Key $key, int $leeway): array
    {
        $previousLeeway = FirebaseJwt::$leeway;
        $previousTimestamp = FirebaseJwt::$timestamp;
        FirebaseJwt::$leeway = $leeway;
        FirebaseJwt::$timestamp = Clock::now();
        try {
            $claims = FirebaseJwt::decode($jwt, $key);
        } finally {
            FirebaseJwt::$leeway = $previousLeeway;
            FirebaseJwt::$timestamp = $previousTimestamp;
        }

        $array = json_decode((string) json_encode($claims), true);
        if (!is_array($array)) {
            throw new UnexpectedValueException('Invalid claims encoding');
        }
        // firebase/php-jwt skips the iat check when nbf is present; a token issued in the future
        // is refused either way.
        if (isset($array['iat']) && is_numeric($array['iat']) && $array['iat'] > Clock::now() + $leeway) {
            throw new UnexpectedValueException('Token issued in the future');
        }

        return $array;
    }

    /**
     * array_is_list() is PHP 8.1+; the library supports 8.0.
     */
    public static function isList(array $array): bool
    {
        return $array !== [] && array_keys($array) === range(0, count($array) - 1);
    }
}

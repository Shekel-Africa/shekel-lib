<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

use Shekel\ShekelLib\ServiceAuth\Support\Jwt;

/**
 * sha256 hex of the canonical JSON of a snapshot claim (spec §10.1): object keys sorted
 * recursively, list order kept, no whitespace, unescaped slashes and unicode. The snapshot claim
 * carries no computation timestamps (evaluated_at is outside it), so valid_until is the only
 * timestamp the digest covers.
 */
final class SemanticDigest
{
    public static function of(array $snapshot): string
    {
        return hash('sha256', self::canonicalJson($snapshot));
    }

    public static function canonicalJson(array $value): string
    {
        return (string) json_encode(self::canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return [];
        }
        if (Jwt::isList($value)) {
            return array_map([self::class, 'canonicalize'], $value);
        }
        ksort($value, SORT_STRING);

        return (object) array_map([self::class, 'canonicalize'], $value);
    }
}

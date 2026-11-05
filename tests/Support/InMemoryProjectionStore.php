<?php

namespace Shekel\ShekelLib\Tests\Support;

use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore;

class InMemoryProjectionStore implements EligibilityProjectionStore
{
    /** @var array<string, EligibilityProjection> */
    public array $rows = [];
    public int $saves = 0;
    public int $transactions = 0;

    public function find(string $clientId, string $subjectId): ?EligibilityProjection
    {
        return isset($this->rows[$clientId.'|'.$subjectId]) ? clone $this->rows[$clientId.'|'.$subjectId] : null;
    }

    public function findForUpdate(string $clientId, string $subjectId): ?EligibilityProjection
    {
        return $this->find($clientId, $subjectId);
    }

    public function save(EligibilityProjection $projection): void
    {
        $this->saves++;
        // Conditional upsert, as the interface requires.
        $current = $this->rows[$projection->clientId.'|'.$projection->subjectId] ?? null;
        if ($current !== null && !($current->subjectRevision < $projection->subjectRevision
            || ($current->subjectRevision === $projection->subjectRevision
                && $current->semanticDigest === $projection->semanticDigest
                && $current->evaluatedAt < $projection->evaluatedAt))) {
            return;
        }
        $this->rows[$projection->clientId.'|'.$projection->subjectId] = clone $projection;
    }

    public function transaction(callable $callback)
    {
        $this->transactions++;
        $before = $this->rows;
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->rows = $before;
            throw $e;
        }
    }
}

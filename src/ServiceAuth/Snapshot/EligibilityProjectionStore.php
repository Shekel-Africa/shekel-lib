<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

/**
 * Persistence for the eligibility projection; the consumer implements it (e.g. one table keyed by
 * (client_id, subject_id) on the tenant's connection). ProjectionApplier only calls
 * findForUpdate() and save() inside transaction().
 */
interface EligibilityProjectionStore
{
    public function find(string $clientId, string $subjectId): ?EligibilityProjection;

    /**
     * Like find(), but locks the row for the rest of the transaction (SELECT ... FOR UPDATE).
     */
    public function findForUpdate(string $clientId, string $subjectId): ?EligibilityProjection;

    /**
     * Insert or replace the row: subject_revision, snapshot, semantic_digest, evaluated_at,
     * last_verified_at and jws are written together.
     *
     * findForUpdate() can't lock a row that doesn't exist yet, so save() must be a conditional
     * upsert: replace only a lower revision, or an equal revision with the same semantic_digest
     * and an earlier evaluated_at; otherwise leave the row alone (ProjectionApplier re-reads it).
     * With a unique key on (client_id, subject_id), for example:
     *   INSERT ... ON CONFLICT (client_id, subject_id) DO UPDATE SET ...
     *   WHERE t.subject_revision < EXCLUDED.subject_revision
     *      OR (t.subject_revision = EXCLUDED.subject_revision
     *          AND t.semantic_digest = EXCLUDED.semantic_digest AND t.evaluated_at < EXCLUDED.evaluated_at)
     */
    public function save(EligibilityProjection $projection): void;

    /**
     * Run $callback in one database transaction and return its result.
     */
    public function transaction(callable $callback);
}

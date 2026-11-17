<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Shekel\ShekelLib\ServiceAuth\Events\EligibilitySnapshotConflict;
use Shekel\ShekelLib\ServiceAuth\Snapshot\ApplyResult;
use Shekel\ShekelLib\ServiceAuth\Snapshot\ProjectionApplier;
use Shekel\ShekelLib\ServiceAuth\Snapshot\SemanticDigest;
use Shekel\ShekelLib\ServiceAuth\Snapshot\VerifiedSnapshot;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;

class ProjectionApplierTest extends TestCase
{
    private function snapshot(int $revision, int $evaluatedAt, bool $eligible = true): VerifiedSnapshot
    {
        $content = ['actions' => ['loan.create' => ['eligible' => $eligible, 'valid_until' => null, 'reasons' => [], 'basis' => []]], 'role' => 'local_dealer'];

        return new VerifiedSnapshot("jws-$revision-$evaluatedAt", 'k', Tokens::TENANT, self::SUBJECT, $revision, $evaluatedAt, $evaluatedAt, $evaluatedAt + 86400, null, $content, SemanticDigest::of($content));
    }

    private function apply(VerifiedSnapshot $snapshot): string
    {
        return $this->app->make(ProjectionApplier::class)->apply($snapshot)->outcome;
    }

    private function stored()
    {
        return $this->store->find(Tokens::TENANT, self::SUBJECT);
    }

    public function test_first_snapshot_is_stored_with_its_jws_in_a_transaction(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->assertSame(ApplyResult::APPLIED, $this->apply($this->snapshot(3, $now)));
        $this->assertSame("jws-3-$now", $this->stored()->jws);
        $this->assertSame($now, $this->stored()->lastVerifiedAt);
        $this->assertSame(1, $this->store->transactions);
    }

    public function test_higher_revision_replaces(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(3, $now - 100));
        $this->assertSame(ApplyResult::APPLIED, $this->apply($this->snapshot(4, $now - 200, false)));
        $this->assertSame(4, $this->stored()->subjectRevision);
        $this->assertFalse($this->stored()->snapshot['actions']['loan.create']['eligible']);
    }

    public function test_lower_revision_is_ignored(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(4, $now - 100));
        $this->assertSame(ApplyResult::IGNORED_LOWER, $this->apply($this->snapshot(3, $now, false)));
        $this->assertSame(4, $this->stored()->subjectRevision);
        $this->assertSame(1, $this->store->saves);
    }

    public function test_equal_revision_same_content_later_evaluation_refreshes(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(4, $now - 100));
        $this->assertSame(ApplyResult::REFRESHED, $this->apply($this->snapshot(4, $now - 10)));
        $this->assertSame($now - 10, $this->stored()->lastVerifiedAt);
        $this->assertSame("jws-4-".($now - 10), $this->stored()->jws);
    }

    public function test_equal_revision_with_older_or_same_evaluated_at_is_rejected(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(4, $now - 100));
        $this->assertSame(ApplyResult::REJECTED_STALE, $this->apply($this->snapshot(4, $now - 200)));
        $this->assertSame(ApplyResult::REJECTED_STALE, $this->apply($this->snapshot(4, $now - 100)));
        $this->assertSame($now - 100, $this->stored()->lastVerifiedAt);
    }

    public function test_equal_revision_with_conflicting_content_is_rejected_and_alerted(): void
    {
        Event::fake([EligibilitySnapshotConflict::class]);
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(4, $now - 100));

        $this->assertSame(ApplyResult::REJECTED_CONFLICT, $this->apply($this->snapshot(4, $now, false)));
        $this->assertTrue($this->stored()->snapshot['actions']['loan.create']['eligible']);
        Event::assertDispatched(EligibilitySnapshotConflict::class, fn ($e) => $e->subjectRevision === 4 && $e->clientId === Tokens::TENANT);
    }

    public function test_evaluated_at_more_than_60s_in_the_future_is_rejected(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(4, $now - 100));
        $this->assertSame(ApplyResult::REJECTED_FUTURE, $this->apply($this->snapshot(4, $now + 61)));
        $this->assertSame(ApplyResult::REFRESHED, $this->apply($this->snapshot(4, $now + 60)));
        $this->assertSame(ApplyResult::REJECTED_FUTURE, $this->apply($this->snapshot(9, $now + 3600)));
    }

    public function test_out_of_order_replays_never_regress_the_projection(): void
    {
        $now = Carbon::now()->getTimestamp();
        $sequence = [$this->snapshot(2, $now - 50), $this->snapshot(5, $now - 40), $this->snapshot(3, $now - 30), $this->snapshot(5, $now - 45), $this->snapshot(1, $now)];
        foreach ($sequence as $snapshot) {
            $this->apply($snapshot);
        }
        $this->assertSame(5, $this->stored()->subjectRevision);
        $this->assertSame($now - 40, $this->stored()->evaluatedAt);
    }

    public function test_a_late_first_insert_cannot_overwrite_a_higher_revision(): void
    {
        // Worker A read "absent" and stalls past its lock; worker B inserts revision 2 meanwhile.
        $now = Carbon::now()->getTimestamp();
        $this->apply($this->snapshot(2, $now - 10));
        $this->store->save(\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection::fromSnapshot($this->snapshot(1, $now - 5)));

        $this->assertSame(2, $this->stored()->subjectRevision);
    }

    public function test_a_racing_first_insert_with_conflicting_content_is_detected(): void
    {
        Event::fake([EligibilitySnapshotConflict::class]);
        $now = Carbon::now()->getTimestamp();
        // Simulate the other worker's insert landing between our read and our save.
        $racing = new class($this->store, $this->snapshot(2, $now - 20, false)) extends \Shekel\ShekelLib\Tests\Support\InMemoryProjectionStore {
            private bool $raced = false;
            public function __construct(private $inner, private $other) {}
            public function findForUpdate(string $c, string $s): ?\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection
            {
                $row = $this->inner->findForUpdate($c, $s);
                if (!$this->raced) {
                    $this->raced = true;
                    $this->inner->save(\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection::fromSnapshot($this->other));
                }
                return $row;
            }
            public function find(string $c, string $s): ?\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection { return $this->inner->find($c, $s); }
            public function save(\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection $p): void { $this->inner->save($p); }
            public function transaction(callable $cb) { return $this->inner->transaction($cb); }
        };
        $this->app->instance(\Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore::class, $racing);

        $this->assertSame(ApplyResult::REJECTED_CONFLICT, $this->apply($this->snapshot(2, $now - 10, true)));
        $this->assertFalse($this->stored()->snapshot['actions']['loan.create']['eligible'], 'the first insert is kept');
        Event::assertDispatched(EligibilitySnapshotConflict::class);
    }
}

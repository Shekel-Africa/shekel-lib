<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Queued by HintReceiver: pull and apply the subject's signed snapshot.
 */
class FetchEligibilitySnapshot implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /** Deferrals for the per-subject interval don't count against a try limit; time bounds retries. */
    public int $tries = 0;
    public array $backoff = [10, 60];

    public function __construct(public string $clientId, public string $subjectId, public ?string $pendingToken = null)
    {
    }

    public function retryUntil(): \DateTimeInterface
    {
        return Carbon::now()->addMinutes(15);
    }

    /**
     * Given up: free the pending slot so the next hint can queue a fetch again.
     */
    public function failed(?Throwable $e = null): void
    {
        app(HintReceiver::class)->clearPending($this->clientId, $this->subjectId, $this->pendingToken);
    }

    public function handle(HintReceiver $hints, SnapshotFetcher $fetcher): void
    {
        // At most one fetch per subject per interval; an early job goes back to the queue. Once it
        // starts, hints arriving from now on queue a new fetch (this one may be too early for them).
        $wait = $hints->fetchStarting($this->clientId, $this->subjectId, $this->pendingToken);
        if ($wait > 0) {
            if ($this->job !== null) {
                $this->release($wait);
            }

            return;
        }
        $result = $fetcher->fetch($this->clientId, $this->subjectId);
        if ($result->status === FetchResult::UNAVAILABLE) {
            $this->release($this->attempts() > 1 ? $this->backoff[1] : $this->backoff[0]);
        }
    }
}

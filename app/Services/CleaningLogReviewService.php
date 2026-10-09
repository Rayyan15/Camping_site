<?php

namespace App\Services;

use App\Enums\CleaningLogStatus;
use App\Exceptions\InvalidCleaningLogReviewException;
use App\Models\CleaningLog;
use Illuminate\Support\Facades\DB;

/**
 * A supervisor checks each cleaning report once: it is approved, or sent back to be cleaned again.
 */
class CleaningLogReviewService
{
    /**
     * @throws InvalidCleaningLogReviewException
     */
    public function approve(CleaningLog $log): CleaningLog
    {
        return $this->decide($log, CleaningLogStatus::Approved, null);
    }

    /**
     * The reason is kept on the report so the cleaner sees what to redo.
     *
     * @throws InvalidCleaningLogReviewException
     */
    public function reject(CleaningLog $log, string $reason): CleaningLog
    {
        return $this->decide($log, CleaningLogStatus::Rejected, trim($reason));
    }

    /**
     * @throws InvalidCleaningLogReviewException
     */
    private function decide(CleaningLog $log, CleaningLogStatus $status, ?string $reason): CleaningLog
    {
        return DB::transaction(function () use ($log, $status, $reason) {
            $locked = CleaningLog::whereKey($log->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== CleaningLogStatus::Pending) {
                throw InvalidCleaningLogReviewException::alreadyReviewed();
            }

            $locked->status = $status;

            if ($reason !== null && $reason !== '') {
                $locked->notes = trim(($locked->notes ? $locked->notes."\n\n" : '').'Catatan pemeriksa: '.$reason);
            }

            $locked->save();
            $log->refresh();

            return $log;
        });
    }
}

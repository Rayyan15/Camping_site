<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->created($model);
    }

    public function updated(Model $model): void
    {
        $this->logger->updated($model);
    }

    public function deleted(Model $model): void
    {
        $this->logger->deleted($model);
    }
}

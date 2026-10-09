<?php

namespace App\Observers;

use App\Models\UnitTypePhoto;
use Illuminate\Support\Facades\Storage;

/** Keeps the public disk free of orphan files once a gallery row is removed. */
class UnitTypePhotoObserver
{
    public function deleted(UnitTypePhoto $photo): void
    {
        Storage::disk(UnitTypePhoto::DISK)->delete($photo->path);
    }
}

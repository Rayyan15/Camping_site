<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies a bundled seed photo onto the public disk when it is not there yet.
 */
class SeedImage
{
    private const SOURCE_DIRECTORY = __DIR__.'/../images';

    /**
     * @param  string  $source  path under database/seeders/images, for example "tents/exterior-dome-deck.webp"
     * @param  string  $target  path relative to the public disk
     * @return string the target path, ready to store in the database
     */
    public static function publish(string $source, string $target): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($target)) {
            $sourcePath = self::SOURCE_DIRECTORY.'/'.$source;

            if (! is_file($sourcePath)) {
                throw new RuntimeException("Seed image not found: {$source}");
            }

            $disk->put($target, file_get_contents($sourcePath));
        }

        return $target;
    }
}

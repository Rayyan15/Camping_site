<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Same initials avatar as Filament's default, painted in the brand forest instead of near-black,
 * so the avatar uses the panel's single accent color.
 */
class BrandAvatarProvider extends UiAvatarsProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $forest = ltrim((string) config('brand.forest.700'), '#');

        return (string) preg_replace('/background=[^&]*/', 'background='.$forest, parent::get($record));
    }
}

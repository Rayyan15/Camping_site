<?php

namespace App\Policies;

class UnitTypePhotoPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'unit_type_photo';
    }
}

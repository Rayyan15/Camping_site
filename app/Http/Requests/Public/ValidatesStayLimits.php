<?php

namespace App\Http\Requests\Public;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesStayLimits
{
    private function validateStayLimits(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['check_in', 'check_out'])) {
            return;
        }

        $checkIn = CarbonImmutable::parse($this->input('check_in'))->startOfDay();
        $checkOut = CarbonImmutable::parse($this->input('check_out'))->startOfDay();
        $maxNights = (int) config('booking.max_nights');
        $maxAdvanceDays = (int) config('booking.max_advance_days');

        if ($checkIn->gt(CarbonImmutable::today()->addDays($maxAdvanceDays))) {
            $validator->errors()->add('check_in', "Check-in maksimal {$maxAdvanceDays} hari dari sekarang.");
        }

        if ($checkIn->diffInDays($checkOut) > $maxNights) {
            $validator->errors()->add('check_out', "Lama menginap maksimal {$maxNights} malam.");
        }
    }
}

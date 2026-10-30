<?php

namespace App\Http\Requests\Public;

use App\Support\BookingWindow;
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

        $lastCheckIn = BookingWindow::lastCheckIn();

        if ($checkIn->gt($lastCheckIn)) {
            $validator->errors()->add('check_in', __('Check-in paling lambat :date.', ['date' => $lastCheckIn->locale(app()->getLocale())->translatedFormat('j F Y')]));
        }

        if ($checkIn->diffInDays($checkOut) > $maxNights) {
            $validator->errors()->add('check_out', __('Lama menginap maksimal :count malam.', ['count' => $maxNights]));
        }
    }
}

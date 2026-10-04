<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use App\Models\Customer;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'unit_type_id' => 'required|exists:unit_types,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1'
        ]);

        $unitType = UnitType::findOrFail($request->unit_type_id);
        
        $availableUnits = $this->bookingService->checkAvailability(
            $request->unit_type_id, 
            $request->check_in, 
            $request->check_out, 
            $request->guests
        );

        $isAvailable = $availableUnits->count() > 0;

        return view('public.booking-cek', compact('unitType', 'request', 'availableUnits', 'isAvailable'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'unit_type_id' => 'required|exists:unit_types,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'guests' => 'required|integer',
            'unit_ids' => 'required|array',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
        ]);

        try {
            DB::beginTransaction();

            // 1. Cari atau buat customer
            $customer = Customer::firstOrCreate(
                ['email' => $request->customer_email],
                [
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                ]
            );

            // 2. Buat Booking menggunakan Service
            $booking = $this->bookingService->createBooking(
                $customer->id,
                $request->check_in,
                $request->check_out,
                $request->guests,
                $request->unit_ids
            );

            DB::commit();

            // Redirect ke halaman sukses / pembayaran
            return redirect()->route('booking.success', ['code' => $booking->code]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function success($code)
    {
        $booking = \App\Models\Booking::where('code', $code)->firstOrFail();
        return view('public.booking-success', compact('booking'));
    }
}

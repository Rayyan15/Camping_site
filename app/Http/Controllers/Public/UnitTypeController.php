<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    public function show($slug)
    {
        $unitType = UnitType::with('photos')->where('slug', $slug)->firstOrFail();
        
        // Data dummy untuk ketersediaan tanggal
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        return view('public.tenda-detail', compact('unitType', 'today', 'tomorrow'));
    }
}

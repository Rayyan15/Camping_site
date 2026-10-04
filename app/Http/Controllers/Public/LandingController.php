<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        $query = UnitType::with('photos')->where('capacity', '>', 0);
        
        if ($request->has('guests') && $request->guests > 0) {
            $query->where('capacity', '>=', $request->guests);
        }
        
        $unitTypes = $query->get();
        return view('welcome', compact('unitTypes'));
    }
}

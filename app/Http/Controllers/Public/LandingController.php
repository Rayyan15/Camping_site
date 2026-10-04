<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        $unitTypes = UnitType::with('photos')->where('capacity', '>', 0)->get();
        return view('welcome', compact('unitTypes'));
    }
}

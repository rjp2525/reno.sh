<?php

namespace App\Http\Controllers;

use App\Models\Screenshot;
use Illuminate\Http\Request;

class ScreenshotController extends Controller
{
    public function __invoke(Screenshot $screenshot, Request $request)
    {
        return view('screenshot', compact('screenshot'));

        return response()->json($screenshot);
    }
}

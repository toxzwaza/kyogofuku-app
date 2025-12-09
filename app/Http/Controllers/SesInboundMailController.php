<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SesInboundMailController extends Controller
{
    //
    public function handle(Request $request)
    {
        Log::info('Inbound mail received:', $request->all());

        return response()->json(['status' => 'ok']);
    }
}

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

        // SubscriptionConfirmation の場合
        if ($request->Type === 'SubscriptionConfirmation') {
            Log::info('SNS Confirmation URL: ' . $request->SubscribeURL);
        }

        return response()->json(['status' => 'ok']);
    }
}

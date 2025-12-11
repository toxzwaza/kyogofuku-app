<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SesInboundMailController extends Controller
{
    //
    public function handle(Request $request)
    {
        // 生の JSON を取得
        $raw = $request->getContent();
        Log::info('RAW request:', ['raw' => $raw]);
    
        // JSON を配列として取得
        $data = json_decode($raw, true);
        Log::info('Decoded JSON:', $data);
    
        // SNSの確認
        if (!empty($data['Type']) && $data['Type'] === 'SubscriptionConfirmation') {
            Log::info('SNS Confirmation URL: ' . $data['SubscribeURL']);
        }
    
        return response()->json(['status' => 'ok']);
    }
    
}

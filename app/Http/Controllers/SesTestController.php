<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SesTestMail;

class SesTestController extends Controller
{
    public function send(Request $request)
    {
        $to = $request->input('to') ?: config('mail.from.address');
        $messageText = 'これはAmazon SESから送信されたテストメールです。';
        Mail::to($to)->send(new SesTestMail($messageText));

        return response()->json([
            'message' => 'メール送信を試みました（to: ' . $to . '）',
        ]);
    }
}

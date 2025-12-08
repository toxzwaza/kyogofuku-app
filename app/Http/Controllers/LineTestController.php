<?php

namespace App\Http\Controllers;

use App\Http\Controllers\LineWebhookController;

class LineTestController extends Controller
{
    public function test()
    {
        $lineWebhookController = new LineWebhookController();
        $lineWebhookController->pushToLineGroup("テスト通知：LINE連携成功しました！");

        return "OK";
    }
}

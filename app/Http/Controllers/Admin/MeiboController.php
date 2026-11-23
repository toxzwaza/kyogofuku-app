<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 名簿（成人式 DM 名簿）の画面。
 *
 * いまは UI だけを kyogofuku-app に載せた段階で、データは画面側のダミー（resources/js/services/meibo/dummyAdapter.js）。
 * 本物のデータ（名簿管理システムの persons・label_templates など）につなぐのは次の段階。
 * そのとき dataSource を 'http' にし、resources/js/services/meibo/httpAdapter.js が呼ぶ API をここに足す。
 *
 * 名簿は顧客になる前の方の個人情報（氏名・住所・電話）なので、店舗管理者以上に限る。
 */
class MeiboController extends Controller
{
    /** 画面が使うデータの取り方。'dummy'（いま）／'http'（次の段階） */
    public const DATA_SOURCE = 'dummy';

    public function index(Request $request)
    {
        return $this->render($request, 'Admin/Meibo/Index');
    }

    public function labels(Request $request)
    {
        return $this->render($request, 'Admin/Meibo/Labels');
    }

    public function dashboard(Request $request)
    {
        return $this->render($request, 'Admin/Meibo/Dashboard');
    }

    private function render(Request $request, string $component)
    {
        abort_unless($request->user()?->isManager(), 403);

        return Inertia::render($component, [
            'dataSource' => self::DATA_SOURCE,
        ]);
    }
}

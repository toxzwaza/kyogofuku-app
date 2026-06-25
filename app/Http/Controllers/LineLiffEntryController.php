<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * 友達紹介系LIFFの統一エントリ（エンドポイント = /line/liff）。
 *
 * LIFFのパス付加（liff.line.me/{id}/xxx → endpoint/xxx）は実機で安定しない（エンドポイント直下
 * /line/liff が読まれ 404 になる）ため、1つのLIFFアプリ（Endpoint=/line/liff）に集約し、
 * クエリパラメータで画面を振り分ける。
 *   - 紹介（被紹介者）   : /line/liff?ref=XXXX        （?screen=referral でも可）
 *   - マイステージ        : /line/liff?screen=my-stage
 *   - マイポイント／ギフト : /line/liff?screen=my-points
 *   - 顧客詳細（マイページ）: /line/liff?screen=mypage（既定）
 */
class LineLiffEntryController extends Controller
{
    private const MYPAGE_SCREENS = ['my-stage', 'my-points', 'mypage'];

    public function entry(Request $request)
    {
        $screen = (string) $request->query('screen', '');
        $ref = (string) $request->query('ref', '');

        // 紹介（?ref= で踏む / 明示的に screen=referral）
        if ($ref !== '' || $screen === 'referral') {
            return response()->view('line.liff-referral', [
                'liffId' => config('line.liff.referral_id'),
                'ref' => $ref,
                'addFriendUrl' => config('line.line_official_add_friend_url'),
            ]);
        }

        // マイページ系（未知の screen は mypage に寄せる）
        $target = in_array($screen, self::MYPAGE_SCREENS, true) ? $screen : 'mypage';

        return response()->view('line.liff-mypage', [
            'liffId' => config('line.liff.referral_id'),
            'screen' => $target,
        ]);
    }
}

<?php

namespace Tests\Feature\Referral;

use Tests\TestCase;

/**
 * 統一LIFFエントリ（/line/liff）のクエリ振り分け。
 * LIFFパス付加が効かないため、?ref= / ?screen= で画面を出し分ける（LineLiffEntryController）。
 */
class LiffEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['line.liff.referral_id' => '2009633621-Wh0yxjfu']);
    }

    public function test_default_shows_mypage(): void
    {
        $res = $this->get('/line/liff');
        $res->assertOk();
        $res->assertSee('SCREEN = "mypage"', false);
        $res->assertSee('2009633621-Wh0yxjfu', false);
    }

    public function test_screen_my_stage(): void
    {
        $this->get('/line/liff?screen=my-stage')
            ->assertOk()
            ->assertSee('SCREEN = "my-stage"', false);
    }

    public function test_screen_my_points(): void
    {
        $this->get('/line/liff?screen=my-points')
            ->assertOk()
            ->assertSee('SCREEN = "my-points"', false);
    }

    public function test_ref_shows_referral_screen(): void
    {
        $this->get('/line/liff?ref=ABC12345')
            ->assertOk()
            ->assertSee('const REF = "ABC12345"', false)
            ->assertSee('友達紹介', false);
    }

    public function test_unknown_screen_falls_back_to_mypage(): void
    {
        $this->get('/line/liff?screen=bogus')
            ->assertOk()
            ->assertSee('SCREEN = "mypage"', false);
    }

    public function test_screen_link_shows_welcome_form(): void
    {
        $this->get('/line/liff?screen=link')
            ->assertOk()
            ->assertSee('LINE連携', false)
            ->assertSee('id="lookup_key"', false);
    }
}

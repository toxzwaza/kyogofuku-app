<?php

namespace Tests\Unit;

use App\Services\GoogleCalendarSyncService;
use PHPUnit\Framework\TestCase;

/**
 * Google カレンダーの色分け（振袖＝状態で色・袴＝緑・前撮り＝予約済みは青）
 */
class GoogleCalendarColorTest extends TestCase
{
    private GoogleCalendarSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoogleCalendarSyncService();
    }

    public function test_振袖の予約は今までどおり状態で色が変わる(): void
    {
        $this->assertSame('6', $this->service->calendarColorIdForReservation('確認中', false));
        $this->assertSame('6', $this->service->calendarColorIdForReservation('返信待ち', false));
        $this->assertSame('11', $this->service->calendarColorIdForReservation('対応完了済み', false));
        $this->assertSame('8', $this->service->calendarColorIdForReservation('未対応', false));
        $this->assertSame('8', $this->service->calendarColorIdForReservation(null, false));
    }

    public function test_袴の予約は状態にかかわらず緑(): void
    {
        foreach (['未対応', '確認中', '返信待ち', '対応完了済み', null] as $status) {
            $this->assertSame('10', $this->service->calendarColorIdForReservation($status, true), (string) $status);
        }
    }

    public function test_袴の予約は件名の先頭に状態を付ける(): void
    {
        $this->assertSame('【確認中】', $this->service->calendarSummaryPrefixForReservation('確認中', true));
        $this->assertSame('【返信待ち】', $this->service->calendarSummaryPrefixForReservation('返信待ち', true));
        $this->assertSame('【対応完了済み】', $this->service->calendarSummaryPrefixForReservation('対応完了済み', true));
        $this->assertSame('', $this->service->calendarSummaryPrefixForReservation('未対応', true));
        $this->assertSame('', $this->service->calendarSummaryPrefixForReservation(null, true));
    }

    public function test_振袖の予約は件名に状態を付けない(): void
    {
        $this->assertSame('', $this->service->calendarSummaryPrefixForReservation('確認中', false));
        $this->assertSame('', $this->service->calendarSummaryPrefixForReservation('対応完了済み', false));
    }

    public function test_前撮りの枠は予約済みが青で空きはグレー(): void
    {
        $this->assertSame('9', $this->service->calendarColorIdForPhotoSlot(true));
        $this->assertSame('8', $this->service->calendarColorIdForPhotoSlot(false));
    }
}

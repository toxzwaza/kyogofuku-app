<?php

namespace App\Services;

use App\Models\EventReservation;
use App\Models\StaffSchedule;

class EventReservationCalendarPresentationService
{
    public function buildTitle(EventReservation $reservation): string
    {
        $reservation->loadMissing(['customer.ceremonyArea']);

        return implode('/', [
            (string) ($reservation->name ?? ''),
            $this->formatSeijinYearLabel($reservation->seijin_year),
            $this->formatPlansLabel($reservation->considering_plans),
            $this->formatCeremonyAreaLabel($reservation),
            $this->formatAssigneeLabel($reservation->admin_assignee),
        ]);
    }

    public function buildDescription(EventReservation $reservation): string
    {
        $reservation->loadMissing(['customer.ceremonyArea', 'venue']);

        $lines = [
            '成人年度: ' . $this->formatSeijinYearLabel($reservation->seijin_year),
            'プラン: ' . $this->formatPlansLabel($reservation->considering_plans),
            '成人式エリア: ' . $this->formatCeremonyAreaLabel($reservation),
            '担当: ' . $this->formatAssigneeLabel($reservation->admin_assignee),
            '',
            '予約ID: ' . $reservation->id,
            'お名前: ' . $reservation->name,
            'メール: ' . $reservation->email,
            '電話: ' . $reservation->phone,
        ];

        if ($reservation->reservation_datetime) {
            $lines[] = '予約日時: ' . $reservation->reservation_datetime;
        }

        if ($reservation->venue) {
            $lines[] = '会場: ' . $reservation->venue->name;
        }

        if ($reservation->inquiry_message) {
            $lines[] = 'お問い合わせ内容: ' . $reservation->inquiry_message;
        }

        return implode("\n", $lines);
    }

    /**
     * 予約に紐づくスケジュールのタイトル・説明を最新化し、StaffScheduleObserver 経由で Google に反映する。
     */
    public function syncStaffScheduleFromReservation(EventReservation $reservation): void
    {
        $reservation->loadMissing(['event', 'customer.ceremonyArea']);

        if ($reservation->event?->form_type !== 'reservation') {
            return;
        }

        $schedule = $reservation->relationLoaded('schedule')
            ? $reservation->schedule
            : null;
        if (!$schedule) {
            $schedule = StaffSchedule::where('event_reservation_id', $reservation->id)->first();
        }

        if (!$schedule || !$schedule->sync_to_google_calendar) {
            return;
        }

        $title = $this->buildTitle($reservation);
        $description = $this->buildDescription($reservation);

        if ($schedule->title === $title && $schedule->description === $description) {
            return;
        }

        $schedule->update([
            'title' => $title,
            'description' => $description,
        ]);
    }

    protected function formatSeijinYearLabel(?int $year): string
    {
        if ($year === null) {
            return '未設定';
        }

        return $year . '年';
    }

    protected function formatPlansLabel(mixed $plans): string
    {
        if (!is_array($plans) || $plans === []) {
            return '未設定';
        }

        $filtered = array_values(array_filter($plans, static fn ($p) => $p !== null && $p !== ''));

        return $filtered === [] ? '未設定' : implode('、', $filtered);
    }

    protected function formatCeremonyAreaLabel(EventReservation $reservation): string
    {
        $name = $reservation->customer?->ceremonyArea?->name;

        if ($name === null || trim((string) $name) === '') {
            return '未設定';
        }

        return trim((string) $name);
    }

    protected function formatAssigneeLabel(?string $assignee): string
    {
        if ($assignee === null || trim($assignee) === '') {
            return '未設定';
        }

        return trim($assignee);
    }
}

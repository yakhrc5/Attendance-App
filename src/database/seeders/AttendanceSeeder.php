<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 一般ユーザーを取得する
        $users = User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->get();

        foreach ($users as $index => $user) {
            // ユーザーごとに少しずつ時刻をずらして、見た目で区別しやすくする
            $this->seedUserAttendances($user->id, $index);
        }
    }

    /**
     * ユーザーごとの勤怠を作成する
     */
    private function seedUserAttendances(int $userId, int $index): void
    {
        // 今月の1日を基準日にする
        $baseDate = now()->startOfMonth();

        // 1日分の勤怠を作成する
        $this->createAttendanceWithBreaks(
            userId: $userId,
            workDate: $baseDate->copy()->addDays(0),
            clockIn: sprintf('09:%02d', 0 + $index),
            clockOut: sprintf('18:%02d', 0 + $index),
            breaks: [
                ['12:00', '13:00'],
            ]
        );

        // 休憩2回の日
        $this->createAttendanceWithBreaks(
            userId: $userId,
            workDate: $baseDate->copy()->addDays(1),
            clockIn: sprintf('09:%02d', 5 + $index),
            clockOut: sprintf('18:%02d', 10 + $index),
            breaks: [
                ['12:00', '12:45'],
                ['15:00', '15:15'],
            ]
        );

        // 休憩なしで退勤済みの日
        $this->createAttendanceWithBreaks(
            userId: $userId,
            workDate: $baseDate->copy()->addDays(2),
            clockIn: sprintf('10:%02d', 0 + $index),
            clockOut: sprintf('17:%02d', 0 + $index),
            breaks: []
        );

        // 出勤中・まだ休憩なしの日
        $this->createAttendanceWithBreaks(
            userId: $userId,
            workDate: $baseDate->copy()->addDays(3),
            clockIn: sprintf('10:%02d', 5 + $index),
            clockOut: null,
            breaks: []
        );

        // 出勤中・現在休憩中の日
        $this->createAttendanceWithBreaks(
            userId: $userId,
            workDate: $baseDate->copy()->addDays(4),
            clockIn: sprintf('09:%02d', 15 + $index),
            clockOut: null,
            breaks: [
                ['12:30', null],
            ]
        );

        // 完全に空白の日は勤怠レコード自体を作らない
        // 一覧で空欄表示の確認に使える
    }

    /**
     * 勤怠本体と休憩データをまとめて作成する
     *
     * @param array<int, array{0:string,1:?string}> $breaks
     */
    private function createAttendanceWithBreaks(
        int $userId,
        Carbon $workDate,
        string $clockIn,
        ?string $clockOut,
        array $breaks
    ): void {
        // work_date は date、clock_in_at / clock_out_at は datetime で作成する
        $attendance = Attendance::query()->create([
            'user_id' => $userId,
            'work_date' => $workDate->toDateString(),
            'clock_in_at' => $this->combineDateAndTime($workDate, $clockIn),
            'clock_out_at' => $clockOut !== null
                ? $this->combineDateAndTime($workDate, $clockOut)
                : null,
        ]);

        // 休憩データを作成する
        foreach ($breaks as [$breakStart, $breakEnd]) {
            $attendance->attendanceBreaks()->create([
                'break_start_at' => $this->combineDateAndTime($workDate, $breakStart),
                'break_end_at' => $breakEnd !== null
                    ? $this->combineDateAndTime($workDate, $breakEnd)
                    : null,
            ]);
        }
    }

    /**
     * 日付と時刻文字列を結合して datetime 文字列にする
     */
    private function combineDateAndTime(Carbon $date, string $time): string
    {
        return $date->copy()->format('Y-m-d') . ' ' . $time . ':00';
    }
}

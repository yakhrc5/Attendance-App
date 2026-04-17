<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ユーザー情報取得機能（管理者）
class Case014AdminStaffListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ユーザーデータを作成する
        $this->seed(UserSeeder::class);
    }

    // 管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できることを確認するテスト
    public function test_admin_can_confirm_all_general_user_names_and_email_addresses(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 一般ユーザー一覧を取得する
        $generalUsers = $this->findGeneralUsers();

        // 管理者でスタッフ一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.staff.list')
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 全一般ユーザーの氏名とメールアドレスが表示されていることを確認する
        foreach ($generalUsers as $generalUser) {
            $response->assertSeeText($generalUser->name);
            $response->assertSeeText($generalUser->email);
        }

        // 管理者ユーザーのメールアドレスは表示されていないことを確認する
        $response->assertDontSeeText($admin->email);
    }

    // ユーザーの勤怠情報が正しく表示されることを確認するテスト
    public function test_selected_user_attendance_information_is_displayed_correctly(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 対象ユーザーを取得する
        $targetUser = $this->findFirstGeneralUser();

        // 比較用の別ユーザーを取得する
        $otherUser = $this->findSecondGeneralUser();

        // 表示対象月を作成する
        $targetMonth = now()->copy()->startOfMonth();

        // 対象ユーザーの勤怠を作成する
        $targetAttendance = $this->createAttendanceWithBreak(
            userId: $targetUser->id,
            workDate: $targetMonth->copy()->addDays(9),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 別ユーザーの勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $otherUser->id,
            workDate: $targetMonth->copy()->addDays(9),
            clockInAt: '07:00',
            clockOutAt: '16:00',
            breakStartAt: '11:00',
            breakEndAt: '12:00'
        );

        // 管理者で対象ユーザーの勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.staff', ['id' => $targetUser->id])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 対象ユーザーの勤怠情報が正しく表示されていることを確認する
        $response->assertSeeText('09:00');
        $response->assertSeeText('18:00');
        $response->assertSeeText('1:00');
        $response->assertSeeText('8:00');

        // 対象ユーザーの詳細リンクが表示されていることを確認する
        $response->assertSee(
            route('admin.attendance.detail', ['id' => $targetAttendance->id]),
            false
        );

        // 別ユーザーの勤怠情報は表示されていないことを確認する
        $response->assertDontSeeText('07:00');
        $response->assertDontSeeText('16:00');
    }

    // 「前月」を押下した時に表示月の前月の情報が表示されることを確認するテスト
    public function test_previous_month_information_is_displayed_when_previous_month_is_selected(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 対象ユーザーを取得する
        $user = $this->findFirstGeneralUser();

        // 前月を作成する
        $previousMonth = now()->copy()->startOfMonth()->subMonthNoOverflow();

        // 当月を作成する
        $currentMonth = now()->copy()->startOfMonth();

        // 前月の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $previousMonth->copy()->addDays(9),
            clockInAt: '08:10',
            clockOutAt: '17:10',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 当月の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $currentMonth->copy()->addDays(9),
            clockInAt: '09:20',
            clockOutAt: '18:20',
            breakStartAt: '13:00',
            breakEndAt: '14:00'
        );

        // 管理者で前月の勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.staff', [
                'id' => $user->id,
                'month' => $previousMonth->format('Y-m'),
            ])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 前月の情報が表示されていることを確認する
        $response->assertSeeText('08:10');
        $response->assertSeeText('17:10');
        $response->assertSeeText('1:00');
        $response->assertSeeText('8:00');

        // 当月の情報が表示されていないことを確認する
        $response->assertDontSeeText('09:20');
        $response->assertDontSeeText('18:20');
    }

    // 「翌月」を押下した時に表示月の翌月の情報が表示されることを確認するテスト
    public function test_next_month_information_is_displayed_when_next_month_is_selected(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 対象ユーザーを取得する
        $user = $this->findFirstGeneralUser();

        // 当月を作成する
        $currentMonth = now()->copy()->startOfMonth();

        // 翌月を作成する
        $nextMonth = now()->copy()->startOfMonth()->addMonthNoOverflow();

        // 当月の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $currentMonth->copy()->addDays(9),
            clockInAt: '08:40',
            clockOutAt: '17:40',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 翌月の勤怠を作成する
        $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $nextMonth->copy()->addDays(9),
            clockInAt: '10:30',
            clockOutAt: '19:30',
            breakStartAt: '14:00',
            breakEndAt: '15:00'
        );

        // 管理者で翌月の勤怠一覧画面を開く
        $response = $this->actingAs($admin)->get(
            route('admin.attendance.staff', [
                'id' => $user->id,
                'month' => $nextMonth->format('Y-m'),
            ])
        );

        // 画面が正常に表示されることを確認する
        $response->assertOk();

        // 翌月の情報が表示されていることを確認する
        $response->assertSeeText('10:30');
        $response->assertSeeText('19:30');
        $response->assertSeeText('1:00');
        $response->assertSeeText('8:00');

        // 当月の情報が表示されていないことを確認する
        $response->assertDontSeeText('08:40');
        $response->assertDontSeeText('17:40');
    }

    // 「詳細」を押下すると、その日の勤怠詳細画面に遷移することを確認するテスト
    public function test_detail_button_redirects_to_admin_attendance_detail_page(): void
    {
        // 管理者ユーザーを取得する
        $admin = $this->findAdminUser();

        // 対象ユーザーを取得する
        $user = $this->findFirstGeneralUser();

        // 対象月を作成する
        $targetMonth = now()->copy()->startOfMonth();

        // 対象の勤怠を作成する
        $attendance = $this->createAttendanceWithBreak(
            userId: $user->id,
            workDate: $targetMonth->copy()->addDays(4),
            clockInAt: '09:00',
            clockOutAt: '18:00',
            breakStartAt: '12:00',
            breakEndAt: '13:00'
        );

        // 管理者で対象ユーザーの勤怠一覧画面を開く
        $listResponse = $this->actingAs($admin)->get(
            route('admin.attendance.staff', ['id' => $user->id])
        );

        // 一覧画面に勤怠詳細画面へのリンクが表示されていることを確認する
        $listResponse->assertSee(
            route('admin.attendance.detail', ['id' => $attendance->id]),
            false
        );

        // 勤怠詳細画面を開く
        $detailResponse = $this->actingAs($admin)->get(
            route('admin.attendance.detail', ['id' => $attendance->id])
        );

        // 勤怠詳細画面が正常に表示されることを確認する
        $detailResponse->assertOk();
    }

    // シーダーで投入した管理者ユーザーを取得する
    private function findAdminUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->orderBy('id')
            ->firstOrFail();
    }

    // シーダーで投入した一般ユーザー一覧を取得する
    private function findGeneralUsers()
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->get();
    }

    // シーダーで投入した1人目の一般ユーザーを取得する
    private function findFirstGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->firstOrFail();
    }

    // シーダーで投入した2人目の一般ユーザーを取得する
    private function findSecondGeneralUser(): User
    {
        return User::query()
            ->where('role', User::ROLE_USER)
            ->orderBy('id')
            ->skip(1)
            ->firstOrFail();
    }

    // 勤怠と休憩を1件まとめて作成する
    private function createAttendanceWithBreak(
        int $userId,
        Carbon $workDate,
        string $clockInAt,
        string $clockOutAt,
        string $breakStartAt,
        string $breakEndAt
    ): Attendance {
        $attendance = Attendance::query()->create([
            'user_id' => $userId,
            'work_date' => $workDate->toDateString(),
            'clock_in_at' => $workDate->copy()->format('Y-m-d') . ' ' . $clockInAt . ':00',
            'clock_out_at' => $workDate->copy()->format('Y-m-d') . ' ' . $clockOutAt . ':00',
        ]);

        AttendanceBreak::query()->create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $workDate->copy()->format('Y-m-d') . ' ' . $breakStartAt . ':00',
            'break_end_at' => $workDate->copy()->format('Y-m-d') . ' ' . $breakEndAt . ':00',
        ]);

        return $attendance;
    }
}

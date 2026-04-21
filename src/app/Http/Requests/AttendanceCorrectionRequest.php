<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clock_in_at' => ['required', 'date_format:H:i'],
            'clock_out_at' => ['required', 'date_format:H:i'],

            'breaks' => ['nullable', 'array'],
            'breaks.*.break_start_at' => ['nullable', 'date_format:H:i'],
            'breaks.*.break_end_at' => ['nullable', 'date_format:H:i'],

            'reason' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in_at.required' => '出勤時間を入力してください',
            'clock_in_at.date_format' => '出勤時間は時刻形式で入力してください',

            'clock_out_at.required' => '退勤時間を入力してください',
            'clock_out_at.date_format' => '退勤時間は時刻形式で入力してください',

            'breaks.*.break_start_at.date_format' => '休憩開始時刻は時刻形式で入力してください',
            'breaks.*.break_end_at.date_format' => '休憩終了時刻は時刻形式で入力してください',

            'reason.required' => '備考を記入してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clockInAt = $this->input('clock_in_at');
            $clockOutAt = $this->input('clock_out_at');
            $breaks = $this->input('breaks', []);

            $attendance = Attendance::find($this->route('id'));

            if (
                $attendance !== null
                && Carbon::parse($attendance->work_date)->isToday()
                && empty($attendance->clock_out_at)
            ) {
                $validator->errors()->add(
                    'clock_out_at',
                    '退勤打刻後に修正申請してください'
                );

                return;
            }

            if ($clockInAt >= $clockOutAt) {
                $validator->errors()->add(
                    'clock_in_at',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
            }

            foreach ($breaks as $index => $break) {
                $breakStartAt = $break['break_start_at'] ?? null;
                $breakEndAt = $break['break_end_at'] ?? null;

                if (empty($breakStartAt) && empty($breakEndAt)) {
                    continue;
                }

                if (empty($breakStartAt) || empty($breakEndAt)) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩開始時刻と休憩終了時刻は両方入力してください'
                    );

                    continue;
                }

                if ($breakStartAt >= $breakEndAt) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩開始時間もしくは休憩終了時間が不適切な値です'
                    );

                    continue;
                }

                if (
                    $breakStartAt < $clockInAt
                    || $breakStartAt > $clockOutAt
                ) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩時間が不適切な値です'
                    );

                    continue;
                }

                if ($breakEndAt > $clockOutAt) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }
}

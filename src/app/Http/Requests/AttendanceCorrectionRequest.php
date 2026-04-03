<?php

namespace App\Http\Requests;

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
            $clockInAt = $this->input('clock_in_at');
            $clockOutAt = $this->input('clock_out_at');
            $breaks = $this->input('breaks', []);

            // 1. 出勤・退勤の前後関係
            if (!empty($clockInAt) && !empty($clockOutAt) && $clockInAt >= $clockOutAt) {
                $validator->errors()->add(
                    'clock_in_at',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
            }

            foreach ($breaks as $index => $break) {
                $breakStartAt = $break['break_start_at'] ?? null;
                $breakEndAt = $break['break_end_at'] ?? null;

                // 両方空なら未入力行としてスキップ
                if (empty($breakStartAt) && empty($breakEndAt)) {
                    continue;
                }

                // 片方だけ入力
                if (empty($breakStartAt) || empty($breakEndAt)) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩開始時刻と休憩終了時刻は両方入力してください'
                    );

                    continue;
                }

                // 休憩の前後関係
                if ($breakStartAt >= $breakEndAt) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩開始時間もしくは休憩終了時間が不適切な値です'
                    );

                    continue;
                }

                // 2. 休憩開始時間が出勤時間より前 / 退勤時間より後
                if (
                    !empty($clockInAt)
                    && !empty($clockOutAt)
                    && ($breakStartAt < $clockInAt || $breakStartAt > $clockOutAt)
                ) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩時間が不適切な値です'
                    );

                    continue;
                }

                // 3. 休憩終了時間が退勤時間より後
                if (!empty($clockOutAt) && $breakEndAt > $clockOutAt) {
                    $validator->errors()->add(
                        "breaks.{$index}.break_time",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests\Concerns;

trait HasAttendanceCorrectionRules
{
    protected function attendanceCorrectionRules(): array
    {
        return [
            'new_clock_in' => [
                'nullable',
                'date_format:H:i:s',
                'before:new_clock_out',
            ],
            'new_clock_out' => [
                'nullable',
                'date_format:H:i:s',
                'after:new_clock_in',
            ],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => [
                'nullable',
                'required_with:new_break_out.*',
                'date_format:H:i:s',
                'after:new_clock_in',
                'before:new_clock_out',
            ],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => [
                'nullable',
                'required_with:new_break_in.*',
                'date_format:H:i:s',
                'after:new_break_in.*',
                'before:new_clock_out',
            ],
            'comment' => ['required', 'string'],
        ];
    }

    protected function attendanceCorrectionMessages(): array
    {
        return [
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.required_with' => '休憩時間が不適切な値です',
            'new_break_out.*.required_with' => '休憩時間が不適切な値です',
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です',
            'new_break_in.*.after' => '休憩時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',
            'new_break_in.*.before' => '休憩時間が不適切な値です',
            'new_break_out.*.before' => '休憩時間もしくは退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
        ];
    }
}

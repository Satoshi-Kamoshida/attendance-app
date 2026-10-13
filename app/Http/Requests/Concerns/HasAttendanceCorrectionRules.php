<?php

namespace App\Http\Requests\Concerns;

trait HasAttendanceCorrectionRules
{
    protected function attendanceCorrectionRules(): array
    {
        $rules = [
            'new_clock_in' => [
                'bail',
                'nullable',
                'date_format:H:i',
            ],
            'new_clock_out' => [
                'bail',
                'nullable',
                'date_format:H:i',
            ],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => [
                'bail',
                'nullable',
                'required_with:new_break_out.*',
                'date_format:H:i',
            ],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => [
                'bail',
                'nullable',
                'required_with:new_break_in.*',
                'date_format:H:i',
                'after:new_break_in.*',
            ],
            'comment' => ['required', 'string'],
        ];

        $clockIn = $this->input('new_clock_in');
        $clockOut = $this->input('new_clock_out');

        $isValidTime = static function ($time): bool {
            return is_string($time)
                && preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $time
                ) === 1;
        };

        if (
            $isValidTime($clockIn)
            && $isValidTime($clockOut)
        ) {
            $rules['new_clock_out'][] = 'after:new_clock_in';

            if ($clockIn < $clockOut) {
                array_push(
                    $rules['new_break_in.*'],
                    'after:new_clock_in',
                    'before:new_clock_out'
                );

                $rules['new_break_out.*'][] = 'before:new_clock_out';
            }
        }

        return $rules;
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

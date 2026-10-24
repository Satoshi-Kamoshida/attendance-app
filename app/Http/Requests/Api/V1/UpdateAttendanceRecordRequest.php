<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attendanceId = $this->route('attendance');

        /** @var Attendance $attendance */
        $attendance = Attendance::findOrFail($attendanceId);

        $userId = $attendance->user_id;

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances', 'date')
                    ->where('user_id', $userId)
                    ->ignore($attendance->id),
            ],
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
                'after:'.($this->input('clock_in')
                    ?? $attendance->clock_in),
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * バリデーションエラーメッセージを日本語にする。
     */
    public function messages(): array
    {
        return [
            'date.required' => '日付は必須です。',
            'date.date_format' => '日付はYYYY-MM-DD形式で入力してください。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻はHH:MM:SS形式で入力してください。',
            'clock_out.date_format' => '退勤時刻はHH:MM:SS形式で入力してください。',
            'clock_out.after' => '退勤時刻は出勤時刻より後にしてください。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは255文字以内で入力してください。',
        ];
    }
}

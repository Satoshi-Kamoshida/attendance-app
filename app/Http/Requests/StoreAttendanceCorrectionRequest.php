<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasAttendanceCorrectionRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    use HasAttendanceCorrectionRules;

    public function authorize(): bool
    {
        return ! $this->user()?->admin_status;
    }

    /**
     * 勤怠修正申請の共通バリデーションルールを取得する。
     */
    public function rules(): array
    {
        return $this->attendanceCorrectionRules();
    }

    public function messages(): array
    {
        return $this->attendanceCorrectionMessages();
    }
}

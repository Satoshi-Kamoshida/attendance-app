<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasAttendanceCorrectionRules;
use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    use HasAttendanceCorrectionRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->admin_status;
    }

    public function rules(): array
    {
        return $this->attendanceCorrectionRules();
    }

    public function messages(): array
    {
        return $this->attendanceCorrectionMessages();
    }
}

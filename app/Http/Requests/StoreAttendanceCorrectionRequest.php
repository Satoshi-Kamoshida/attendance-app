<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasAttendanceCorrectionRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    use HasAttendanceCorrectionRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => 'create',
        ]);
    }

    public function rules(): array
    {
        $rules = $this->attendanceCorrectionRules();

        $rules['action'] = ['required', 'in:create,update,delete'];

        array_unshift($rules['new_break_in.*'], 'exclude_if:action,delete');
        array_unshift($rules['new_break_out.*'], 'exclude_if:action,delete');

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(
            $this->attendanceCorrectionMessages(),
            [
                'action.required' => '申請の種類を指定してください',
                'action.in' => '申請の種類が不正です',
                'comment.string' => '備考は文字列で入力してください',
            ]
        );
    }
}

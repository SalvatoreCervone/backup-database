<?php

namespace SalvatoreCervone\BackupDatabase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gate
    }

    public function rules(): array
    {
        return [
            'recipient' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient.email' => 'L\'indirizzo email di test non è valido.',
        ];
    }
}

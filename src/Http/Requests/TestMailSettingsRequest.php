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
            'recipient' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient.required' => 'Inserisci un indirizzo email per inviare il test.',
            'recipient.email' => 'L\'indirizzo email di test non è valido.',
        ];
    }
}

<?php

namespace SalvatoreCervone\BackupDatabase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gate
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'string', 'max:500'],
            'connection' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Il nome del file è obbligatorio.',
            'connection.required' => 'Il nome della connessione è obbligatorio.',
        ];
    }
}

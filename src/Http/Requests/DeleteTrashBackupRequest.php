<?php

namespace SalvatoreCervone\BackupDatabase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTrashBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gate
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'string', 'max:255'],
            'connection' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Il nome del file nel cestino è obbligatorio.',
            'connection.required' => 'Il nome della connessione è obbligatorio.',
        ];
    }
}

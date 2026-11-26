<?php

namespace SalvatoreCervone\BackupDatabase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveTrashSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gate
    }

    public function rules(): array
    {
        return [
            'auto_delete' => ['required', 'boolean'],
            'keep_copies' => ['required', 'integer', 'min:0', 'max:1000'],
            'days_for_delete' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'auto_delete.required' => 'Specifica se abilitare la cancellazione automatica del cestino.',
            'keep_copies.required' => 'Inserisci il numero minimo di copie da mantenere nel cestino.',
            'keep_copies.integer' => 'Il numero minimo di copie deve essere un intero.',
            'keep_copies.min' => 'Il numero minimo di copie non può essere negativo.',
            'days_for_delete.integer' => 'I giorni di permanenza nel cestino devono essere un numero intero.',
            'days_for_delete.min' => 'I giorni di permanenza nel cestino non possono essere negativi.',
        ];
    }
}

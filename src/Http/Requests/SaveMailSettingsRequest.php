<?php

namespace SalvatoreCervone\BackupDatabase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/gate
    }

    public function rules(): array
    {
        return [
            'notify_on_success' => ['sometimes', 'boolean'],
            'notify_on_failure' => ['sometimes', 'boolean'],
            'notify_summary' => ['sometimes', 'boolean'],
            'recipients' => [
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }

                    $items = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $invalid = [];

                    foreach ($items as $item) {
                        $email = trim($item);
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $invalid[] = $email;
                        }
                    }

                    if (! empty($invalid)) {
                        $fail('Indirizzi email non validi: '.implode(', ', $invalid));
                    }
                },
            ],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'clear_smtp_password' => ['sometimes', 'boolean'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'smtp_port.integer' => 'La porta SMTP deve essere un numero intero.',
            'smtp_port.min' => 'La porta SMTP deve essere compresa tra 1 e 65535.',
            'smtp_port.max' => 'La porta SMTP deve essere compresa tra 1 e 65535.',
            'smtp_encryption.in' => 'La crittografia SMTP deve essere tls, ssl oppure none.',
            'from_address.email' => 'L\'indirizzo mittente non è un\'email valida.',
        ];
    }
}

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
            'notification_type' => ['required', 'string', 'in:success,failure,summary,none'],
            'recipients' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '' || $value === []) {
                        return;
                    }

                    if (is_array($value)) {
                        $items = $value;
                    } elseif (is_string($value)) {
                        $items = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    } else {
                        $fail('Il formato dei destinatari non è valido.');

                        return;
                    }

                    $invalid = [];
                    foreach ($items as $item) {
                        $email = trim((string) $item);
                        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $invalid[] = $email;
                        }
                    }

                    if (! empty($invalid)) {
                        $fail('Indirizzi email non validi: '.implode(', ', $invalid));
                    }
                },
            ],
            'smtp_mode' => ['required', 'string', 'in:env,manual'],
            'smtp_host' => ['required_if:smtp_mode,manual', 'nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'clear_smtp_password' => ['sometimes', 'boolean'],
            'smtp_encryption' => ['required_if:smtp_mode,manual', 'nullable', 'string', 'in:tls,ssl,none'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'notification_type.required' => 'Seleziona una tipologia di notifica email.',
            'notification_type.in' => 'La tipologia di notifica selezionata non è valida.',
            'smtp_mode.required' => 'Seleziona la modalità SMTP (.env o immissione manuale).',
            'smtp_mode.in' => 'La modalità SMTP deve essere env oppure manual.',
            'smtp_host.required_if' => 'L\'host SMTP è obbligatorio in modalità manuale.',
            'smtp_port.integer' => 'La porta SMTP deve essere un numero intero.',
            'smtp_port.min' => 'La porta SMTP deve essere compresa tra 1 e 65535.',
            'smtp_port.max' => 'La porta SMTP deve essere compresa tra 1 e 65535.',
            'smtp_encryption.required_if' => 'Seleziona la crittografia SMTP (TLS, SSL oppure Nessuna).',
            'smtp_encryption.in' => 'La crittografia SMTP deve essere tls, ssl oppure none.',
            'from_address.email' => 'L\'indirizzo mittente non è un\'email valida.',
        ];
    }
}

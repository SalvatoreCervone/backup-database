<?php

namespace SalvatoreCervone\BackupDatabase\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MailSettingsManager
{
    /**
     * Get the path where runtime mail settings are stored.
     */
    public function getSettingsPath(): string
    {
        $configured = config('backup-database.notifications.settings_path');

        return $configured ?: storage_path('app/backup-database-settings.json');
    }

    /**
     * Load persisted settings merged with config defaults.
     *
     * @return array{
     *     notify_on_success: bool,
     *     notify_on_failure: bool,
     *     notify_summary: bool,
     *     recipients: string,
     *     smtp_host: string,
     *     smtp_port: ?int,
     *     smtp_username: string,
     *     smtp_password: ?string,
     *     smtp_encryption: string,
     *     from_address: string,
     *     from_name: string
     * }
     */
    public function get(): array
    {
        $defaults = [
            'notify_on_success' => (bool) config('backup-database.notifications.on_success', false),
            'notify_on_failure' => (bool) config('backup-database.notifications.on_failure', true),
            'notify_summary' => (bool) config('backup-database.notifications.summary', false),
            'recipients' => '',
            'smtp_host' => '',
            'smtp_port' => null,
            'smtp_username' => '',
            'smtp_password' => null,
            'smtp_encryption' => '',
            'from_address' => '',
            'from_name' => '',
        ];

        $stored = $this->readRawFile();

        $merged = array_merge($defaults, $stored);

        // Decrypt password if stored encrypted
        $decryptedPassword = null;
        if (! empty($stored['smtp_password_encrypted'])) {
            try {
                $decryptedPassword = Crypt::decryptString($stored['smtp_password_encrypted']);
            } catch (\Exception $e) {
                Log::warning('BackupDatabase: Impossibile decifrare la password SMTP salvata: '.$e->getMessage());
            }
        }

        return [
            'notify_on_success' => (bool) $merged['notify_on_success'],
            'notify_on_failure' => (bool) $merged['notify_on_failure'],
            'notify_summary' => (bool) $merged['notify_summary'],
            'recipients' => trim((string) ($merged['recipients'] ?? '')),
            'smtp_host' => trim((string) ($merged['smtp_host'] ?? '')),
            'smtp_port' => isset($merged['smtp_port']) && $merged['smtp_port'] !== '' ? (int) $merged['smtp_port'] : null,
            'smtp_username' => trim((string) ($merged['smtp_username'] ?? '')),
            'smtp_password' => $decryptedPassword,
            'smtp_encryption' => trim((string) ($merged['smtp_encryption'] ?? '')),
            'from_address' => trim((string) ($merged['from_address'] ?? '')),
            'from_name' => trim((string) ($merged['from_name'] ?? '')),
        ];
    }

    /**
     * Save settings from the panel.
     * Password is encrypted at rest and preserved if omitted unless clear_smtp_password is true.
     */
    public function save(array $data): array
    {
        $existingRaw = $this->readRawFile();
        $encryptedPassword = $existingRaw['smtp_password_encrypted'] ?? null;

        if (! empty($data['clear_smtp_password'])) {
            $encryptedPassword = null;
        } elseif (isset($data['smtp_password']) && $data['smtp_password'] !== '') {
            $encryptedPassword = Crypt::encryptString((string) $data['smtp_password']);
        }

        $port = isset($data['smtp_port']) && $data['smtp_port'] !== '' && $data['smtp_port'] !== null
            ? (int) $data['smtp_port']
            : null;

        $payload = [
            'notify_on_success' => (bool) ($data['notify_on_success'] ?? false),
            'notify_on_failure' => (bool) ($data['notify_on_failure'] ?? false),
            'notify_summary' => (bool) ($data['notify_summary'] ?? false),
            'recipients' => trim((string) ($data['recipients'] ?? '')),
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
            'smtp_port' => $port,
            'smtp_username' => trim((string) ($data['smtp_username'] ?? '')),
            'smtp_password_encrypted' => $encryptedPassword,
            'smtp_encryption' => trim((string) ($data['smtp_encryption'] ?? '')),
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
            'updated_at' => now()->toIso8601String(),
        ];

        $path = $this->getSettingsPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->getForUi();
    }

    /**
     * Return UI-safe settings (without exposing raw passwords) together with .env fallback metadata.
     */
    public function getForUi(): array
    {
        $settings = $this->get();
        $envFallback = $this->getEnvFallbackInfo();
        $effective = $this->getEffectiveSmtpConfig();

        return [
            'notify_on_success' => $settings['notify_on_success'],
            'notify_on_failure' => $settings['notify_on_failure'],
            'notify_summary' => $settings['notify_summary'],
            'recipients' => $settings['recipients'],
            'effective_recipients' => $this->getRecipients(),
            'smtp_host' => $settings['smtp_host'],
            'smtp_port' => $settings['smtp_port'],
            'smtp_username' => $settings['smtp_username'],
            'has_smtp_password' => $settings['smtp_password'] !== null && $settings['smtp_password'] !== '',
            'smtp_encryption' => $settings['smtp_encryption'],
            'from_address' => $settings['from_address'],
            'from_name' => $settings['from_name'],
            'smtp_source' => $effective['source'],
            'smtp_configured' => $effective['is_configured'],
            'env_fallback' => $envFallback,
        ];
    }

    /**
     * Parse a string or array of email addresses (comma, semicolon, or newline separated).
     *
     * @return array<int, string>
     */
    public static function parseRecipients(string|array|null $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        $items = is_array($raw)
            ? $raw
            : (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $valid = [];
        foreach ($items as $item) {
            $email = trim((string) $item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $email;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Get the effective list of recipient email addresses.
     * Falls back to config('backup-database.notification_email') when panel recipients are empty.
     *
     * @return array<int, string>
     */
    public function getRecipients(): array
    {
        $settings = $this->get();
        $panelRecipients = self::parseRecipients($settings['recipients']);

        if (! empty($panelRecipients)) {
            return $panelRecipients;
        }

        return self::parseRecipients(config('backup-database.notification_email'));
    }

    public function shouldNotifyOnSuccess(): bool
    {
        return $this->get()['notify_on_success'];
    }

    public function shouldNotifyOnFailure(): bool
    {
        return $this->get()['notify_on_failure'];
    }

    public function shouldNotifySummary(): bool
    {
        return $this->get()['notify_summary'];
    }

    /**
     * Check whether any SMTP transport field has been explicitly overridden in the panel.
     */
    public function hasCustomSmtpSettings(): bool
    {
        $settings = $this->get();

        return $settings['smtp_host'] !== ''
            || $settings['smtp_port'] !== null
            || $settings['smtp_username'] !== ''
            || ($settings['smtp_password'] !== null && $settings['smtp_password'] !== '')
            || $settings['smtp_encryption'] !== '';
    }

    /**
     * Resolve effective SMTP settings with field-by-field fallback to Laravel config / .env.
     *
     * @return array{
     *     source: string,
     *     is_configured: bool,
     *     mailer: string,
     *     host: ?string,
     *     port: ?int,
     *     username: ?string,
     *     password: ?string,
     *     encryption: ?string,
     *     from_address: ?string,
     *     from_name: ?string
     * }
     */
    public function getEffectiveSmtpConfig(): array
    {
        $settings = $this->get();
        $hasCustomSmtp = $this->hasCustomSmtpSettings();

        $defaultMailer = (string) config('mail.default', 'log');
        $envHost = config('mail.mailers.smtp.host');
        $envPort = config('mail.mailers.smtp.port');
        $envUsername = config('mail.mailers.smtp.username');
        $envPassword = config('mail.mailers.smtp.password');
        $envEncryption = config('mail.mailers.smtp.encryption') ?? config('mail.mailers.smtp.scheme');
        $envFromAddress = config('mail.from.address');
        $envFromName = config('mail.from.name');

        $host = $settings['smtp_host'] !== '' ? $settings['smtp_host'] : ($envHost ? (string) $envHost : null);
        $port = $settings['smtp_port'] !== null ? $settings['smtp_port'] : ($envPort !== null && $envPort !== '' ? (int) $envPort : null);
        $username = $settings['smtp_username'] !== '' ? $settings['smtp_username'] : ($envUsername !== null && $envUsername !== '' ? (string) $envUsername : null);
        $password = ($settings['smtp_password'] !== null && $settings['smtp_password'] !== '')
            ? $settings['smtp_password']
            : ($envPassword !== null && $envPassword !== '' ? (string) $envPassword : null);

        if ($settings['smtp_encryption'] === 'none') {
            $encryption = null;
        } elseif ($settings['smtp_encryption'] !== '') {
            $encryption = $settings['smtp_encryption'];
        } else {
            $encryption = $envEncryption ? (string) $envEncryption : null;
        }

        $fromAddress = $settings['from_address'] !== '' ? $settings['from_address'] : ($envFromAddress ? (string) $envFromAddress : null);
        $fromName = $settings['from_name'] !== '' ? $settings['from_name'] : ($envFromName ? (string) $envFromName : null);

        if ($hasCustomSmtp) {
            $isConfigured = ! empty($host);
            $source = $isConfigured ? 'panel' : 'unconfigured';
        } elseif ($defaultMailer === 'smtp') {
            $isConfigured = ! empty($envHost);
            $source = $isConfigured ? 'env' : 'unconfigured';
        } elseif ($defaultMailer !== '') {
            // Another Laravel mailer is configured in .env (e.g. sendmail, ses, postmark, log, array)
            $isConfigured = true;
            $source = 'env';
        } else {
            $isConfigured = false;
            $source = 'unconfigured';
        }

        return [
            'source' => $source,
            'is_configured' => $isConfigured,
            'mailer' => $hasCustomSmtp ? 'smtp' : $defaultMailer,
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'encryption' => $encryption,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
        ];
    }

    /**
     * Collect non-secret .env / Laravel config fallback info to display as hints in the panel.
     */
    protected function getEnvFallbackInfo(): array
    {
        $envPassword = config('mail.mailers.smtp.password');
        $envRecipients = self::parseRecipients(config('backup-database.notification_email'));

        return [
            'mailer' => (string) config('mail.default', ''),
            'host' => (string) (config('mail.mailers.smtp.host') ?? ''),
            'port' => config('mail.mailers.smtp.port') !== null ? (int) config('mail.mailers.smtp.port') : null,
            'username' => (string) (config('mail.mailers.smtp.username') ?? ''),
            'has_password' => $envPassword !== null && $envPassword !== '',
            'encryption' => (string) (config('mail.mailers.smtp.encryption') ?? config('mail.mailers.smtp.scheme') ?? ''),
            'from_address' => (string) (config('mail.from.address') ?? ''),
            'from_name' => (string) (config('mail.from.name') ?? ''),
            'recipients' => implode(', ', $envRecipients),
        ];
    }

    /**
     * Read raw JSON file if it exists.
     */
    protected function readRawFile(): array
    {
        $path = $this->getSettingsPath();

        if (! File::exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            Log::warning('BackupDatabase: Impossibile leggere il file impostazioni mail: '.$e->getMessage());

            return [];
        }
    }
}

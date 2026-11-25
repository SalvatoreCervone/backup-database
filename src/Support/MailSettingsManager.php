<?php

namespace SalvatoreCervone\BackupDatabase\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MailSettingsManager
{
    public const VALID_NOTIFICATION_TYPES = ['success', 'failure', 'summary', 'none'];

    public const VALID_SMTP_MODES = ['env', 'manual'];

    public const VALID_ENCRYPTIONS = ['tls', 'ssl', 'none'];

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
     *     notification_type: string,
     *     recipients: array<int, string>,
     *     smtp_mode: string,
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
        $stored = $this->readRawFile();

        // Decrypt password if stored encrypted
        $decryptedPassword = null;
        if (! empty($stored['smtp_password_encrypted'])) {
            try {
                $decryptedPassword = Crypt::decryptString($stored['smtp_password_encrypted']);
            } catch (\Exception $e) {
                Log::warning('BackupDatabase: Impossibile decifrare la password SMTP salvata: '.$e->getMessage());
            }
        }

        $notificationType = $this->resolveNotificationType($stored);
        $smtpMode = $this->resolveSmtpMode($stored, $decryptedPassword);

        $rawEncryption = trim((string) ($stored['smtp_encryption'] ?? ''));
        $smtpEncryption = in_array($rawEncryption, self::VALID_ENCRYPTIONS, true) ? $rawEncryption : 'tls';

        return [
            'notification_type' => $notificationType,
            'recipients' => self::parseRecipients($stored['recipients'] ?? []),
            'smtp_mode' => $smtpMode,
            'smtp_host' => trim((string) ($stored['smtp_host'] ?? '')),
            'smtp_port' => isset($stored['smtp_port']) && $stored['smtp_port'] !== '' ? (int) $stored['smtp_port'] : null,
            'smtp_username' => trim((string) ($stored['smtp_username'] ?? '')),
            'smtp_password' => $decryptedPassword,
            'smtp_encryption' => $smtpEncryption,
            'from_address' => trim((string) ($stored['from_address'] ?? '')),
            'from_name' => trim((string) ($stored['from_name'] ?? '')),
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

        $notificationType = $this->resolveNotificationTypeFromInput($data);
        $smtpMode = $this->resolveSmtpModeFromInput($data, $encryptedPassword);

        $rawEncryption = trim((string) ($data['smtp_encryption'] ?? ''));
        $smtpEncryption = in_array($rawEncryption, self::VALID_ENCRYPTIONS, true)
            ? $rawEncryption
            : (in_array((string) ($existingRaw['smtp_encryption'] ?? ''), self::VALID_ENCRYPTIONS, true)
                ? (string) $existingRaw['smtp_encryption']
                : 'tls');

        $recipients = self::parseRecipients($data['recipients'] ?? []);

        $payload = [
            'notification_type' => $notificationType,
            'recipients' => $recipients,
            'smtp_mode' => $smtpMode,
            'smtp_host' => trim((string) ($data['smtp_host'] ?? ($existingRaw['smtp_host'] ?? ''))),
            'smtp_port' => array_key_exists('smtp_port', $data) ? $port : ($existingRaw['smtp_port'] ?? null),
            'smtp_username' => array_key_exists('smtp_username', $data)
                ? trim((string) ($data['smtp_username'] ?? ''))
                : trim((string) ($existingRaw['smtp_username'] ?? '')),
            'smtp_password_encrypted' => $encryptedPassword,
            'smtp_encryption' => $smtpEncryption,
            'from_address' => array_key_exists('from_address', $data)
                ? trim((string) ($data['from_address'] ?? ''))
                : trim((string) ($existingRaw['from_address'] ?? '')),
            'from_name' => array_key_exists('from_name', $data)
                ? trim((string) ($data['from_name'] ?? ''))
                : trim((string) ($existingRaw['from_name'] ?? '')),
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
            'notification_type' => $settings['notification_type'],
            'recipients' => $settings['recipients'],
            'effective_recipients' => $this->getRecipients(),
            'smtp_mode' => $settings['smtp_mode'],
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
        $panelRecipients = $settings['recipients'];

        if (! empty($panelRecipients)) {
            return $panelRecipients;
        }

        return self::parseRecipients(config('backup-database.notification_email'));
    }

    public function getNotificationType(): string
    {
        return $this->get()['notification_type'];
    }

    public function shouldNotifyOnSuccess(): bool
    {
        return $this->get()['notification_type'] === 'success';
    }

    public function shouldNotifyOnFailure(): bool
    {
        return $this->get()['notification_type'] === 'failure';
    }

    public function shouldNotifySummary(): bool
    {
        return $this->get()['notification_type'] === 'summary';
    }

    /**
     * Check whether manual SMTP mode is selected in the panel.
     */
    public function hasCustomSmtpSettings(): bool
    {
        return $this->get()['smtp_mode'] === 'manual';
    }

    /**
     * Resolve effective SMTP settings strictly according to smtp_mode ('env' or 'manual').
     * All SMTP parameters come either completely from .env or completely from manual panel input.
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

        if ($settings['smtp_mode'] === 'manual') {
            $host = $settings['smtp_host'] !== '' ? $settings['smtp_host'] : null;
            $port = $settings['smtp_port'];
            $username = $settings['smtp_username'] !== '' ? $settings['smtp_username'] : null;
            $password = ($settings['smtp_password'] !== null && $settings['smtp_password'] !== '')
                ? $settings['smtp_password']
                : null;
            $encryption = $settings['smtp_encryption'] === 'none' ? null : $settings['smtp_encryption'];
            $fromAddress = $settings['from_address'] !== '' ? $settings['from_address'] : null;
            $fromName = $settings['from_name'] !== '' ? $settings['from_name'] : null;

            $isConfigured = ! empty($host);

            return [
                'source' => $isConfigured ? 'panel' : 'unconfigured',
                'is_configured' => $isConfigured,
                'mailer' => 'smtp',
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'encryption' => $encryption,
                'from_address' => $fromAddress,
                'from_name' => $fromName,
            ];
        }

        // smtp_mode === 'env': all parameters come from Laravel .env / config
        $defaultMailer = (string) config('mail.default', 'log');
        $envHost = config('mail.mailers.smtp.host');
        $envPort = config('mail.mailers.smtp.port');
        $envUsername = config('mail.mailers.smtp.username');
        $envPassword = config('mail.mailers.smtp.password');
        $envEncryption = config('mail.mailers.smtp.encryption') ?? config('mail.mailers.smtp.scheme');
        $envFromAddress = config('mail.from.address');
        $envFromName = config('mail.from.name');

        if ($defaultMailer === 'smtp') {
            $isConfigured = ! empty($envHost);
            $source = $isConfigured ? 'env' : 'unconfigured';
        } elseif ($defaultMailer !== '') {
            $isConfigured = true;
            $source = 'env';
        } else {
            $isConfigured = false;
            $source = 'unconfigured';
        }

        return [
            'source' => $source,
            'is_configured' => $isConfigured,
            'mailer' => $defaultMailer,
            'host' => $envHost ? (string) $envHost : null,
            'port' => $envPort !== null && $envPort !== '' ? (int) $envPort : null,
            'username' => $envUsername !== null && $envUsername !== '' ? (string) $envUsername : null,
            'password' => $envPassword !== null && $envPassword !== '' ? (string) $envPassword : null,
            'encryption' => $envEncryption ? (string) $envEncryption : null,
            'from_address' => $envFromAddress ? (string) $envFromAddress : null,
            'from_name' => $envFromName ? (string) $envFromName : null,
        ];
    }

    /**
     * Collect non-secret .env / Laravel config fallback info to display in the panel.
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
            'recipients' => $envRecipients,
        ];
    }

    /**
     * Resolve notification_type from stored file or config defaults (with legacy fallback).
     */
    protected function resolveNotificationType(array $stored): string
    {
        if (isset($stored['notification_type']) && in_array($stored['notification_type'], self::VALID_NOTIFICATION_TYPES, true)) {
            return $stored['notification_type'];
        }

        // Legacy JSON keys compatibility
        if (array_key_exists('notify_summary', $stored) || array_key_exists('notify_on_success', $stored) || array_key_exists('notify_on_failure', $stored)) {
            if (! empty($stored['notify_summary'])) {
                return 'summary';
            }
            if (! empty($stored['notify_on_success'])) {
                return 'success';
            }
            if (! empty($stored['notify_on_failure'])) {
                return 'failure';
            }

            return 'none';
        }

        // Config defaults
        if (config('backup-database.notifications.summary', false)) {
            return 'summary';
        }
        if (config('backup-database.notifications.on_success', false)) {
            return 'success';
        }
        if (config('backup-database.notifications.on_failure', true)) {
            return 'failure';
        }

        return 'none';
    }

    /**
     * Resolve notification_type from save() input.
     */
    protected function resolveNotificationTypeFromInput(array $data): string
    {
        if (isset($data['notification_type']) && in_array($data['notification_type'], self::VALID_NOTIFICATION_TYPES, true)) {
            return $data['notification_type'];
        }

        // Legacy input compatibility
        if (array_key_exists('notify_summary', $data) || array_key_exists('notify_on_success', $data) || array_key_exists('notify_on_failure', $data)) {
            if (! empty($data['notify_summary'])) {
                return 'summary';
            }
            if (! empty($data['notify_on_success'])) {
                return 'success';
            }
            if (! empty($data['notify_on_failure'])) {
                return 'failure';
            }

            return 'none';
        }

        return 'failure';
    }

    /**
     * Resolve smtp_mode from stored JSON (with legacy fallback).
     */
    protected function resolveSmtpMode(array $stored, ?string $decryptedPassword): string
    {
        if (isset($stored['smtp_mode']) && in_array($stored['smtp_mode'], self::VALID_SMTP_MODES, true)) {
            return $stored['smtp_mode'];
        }

        $hasLegacyCustom = trim((string) ($stored['smtp_host'] ?? '')) !== ''
            || (isset($stored['smtp_port']) && $stored['smtp_port'] !== '')
            || trim((string) ($stored['smtp_username'] ?? '')) !== ''
            || ($decryptedPassword !== null && $decryptedPassword !== '')
            || trim((string) ($stored['smtp_encryption'] ?? '')) !== '';

        return $hasLegacyCustom ? 'manual' : 'env';
    }

    /**
     * Resolve smtp_mode from save() input.
     */
    protected function resolveSmtpModeFromInput(array $data, ?string $encryptedPassword): string
    {
        if (isset($data['smtp_mode']) && in_array($data['smtp_mode'], self::VALID_SMTP_MODES, true)) {
            return $data['smtp_mode'];
        }

        $hasCustomInput = trim((string) ($data['smtp_host'] ?? '')) !== ''
            || (isset($data['smtp_port']) && $data['smtp_port'] !== '' && $data['smtp_port'] !== null)
            || trim((string) ($data['smtp_username'] ?? '')) !== ''
            || (isset($data['smtp_password']) && $data['smtp_password'] !== '')
            || trim((string) ($data['smtp_encryption'] ?? '')) !== '';

        return $hasCustomInput ? 'manual' : 'env';
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

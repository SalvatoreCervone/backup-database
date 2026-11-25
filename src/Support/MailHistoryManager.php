<?php

namespace SalvatoreCervone\BackupDatabase\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class MailHistoryManager
{
    public const MAX_ENTRIES = 200;

    /**
     * Get the path where sent email history is stored.
     */
    public function getHistoryPath(): string
    {
        $configured = config('backup-database.notifications.history_path');

        return $configured ?: storage_path('app/backup-database-mail-history.json');
    }

    /**
     * Record a sent (or failed) email event in the history log.
     *
     * @param  array{
     *     type?: string,
     *     subject?: string,
     *     recipients?: array<int, string>,
     *     smtp_source?: string,
     *     status?: string,
     *     error?: ?string,
     *     sent_at?: string
     * }  $entry
     * @return array<string, mixed>
     */
    public function record(array $entry): array
    {
        $type = (string) ($entry['type'] ?? 'other');
        $normalized = [
            'id' => uniqid('mail_', true),
            'sent_at' => (string) ($entry['sent_at'] ?? now()->toIso8601String()),
            'type' => $type,
            'type_label' => self::labelForType($type),
            'subject' => (string) ($entry['subject'] ?? ''),
            'recipients' => array_values(array_map('strval', $entry['recipients'] ?? [])),
            'smtp_source' => (string) ($entry['smtp_source'] ?? 'env'),
            'status' => ($entry['status'] ?? 'sent') === 'failed' ? 'failed' : 'sent',
            'error' => isset($entry['error']) && $entry['error'] !== '' ? (string) $entry['error'] : null,
        ];

        $entries = $this->readRawFile();
        array_unshift($entries, $normalized);
        $entries = array_slice($entries, 0, self::MAX_ENTRIES);

        try {
            $path = $this->getHistoryPath();
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            Log::warning('BackupDatabase: Impossibile salvare lo storico email: '.$e->getMessage());
        }

        return $normalized;
    }

    /**
     * Retrieve the sent email history (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(int $limit = 100): array
    {
        $entries = $this->readRawFile();

        return array_slice($entries, 0, max(1, $limit));
    }

    /**
     * Map an internal email type to a human-readable Italian label.
     */
    public static function labelForType(string $type): string
    {
        return match ($type) {
            'success' => 'Successo',
            'failure' => 'Errore',
            'summary' => 'Riepilogo',
            'test' => 'Test',
            default => 'Notifica',
        };
    }

    /**
     * Read raw JSON history file if it exists.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readRawFile(): array
    {
        $path = $this->getHistoryPath();

        if (! File::exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode(File::get($path), true);

            return is_array($decoded) ? array_values($decoded) : [];
        } catch (\Exception $e) {
            Log::warning('BackupDatabase: Impossibile leggere lo storico email: '.$e->getMessage());

            return [];
        }
    }
}

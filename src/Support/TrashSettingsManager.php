<?php

namespace SalvatoreCervone\BackupDatabase\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TrashSettingsManager
{
    /**
     * Get the path where runtime trash retention settings are stored.
     */
    public function getSettingsPath(): string
    {
        $configured = config('backup-database.trash.settings_path');

        return $configured ?: storage_path('app/backup-database-trash-settings.json');
    }

    /**
     * Load persisted trash settings merged with config defaults.
     *
     * @return array{
     *     auto_delete: bool,
     *     keep_copies: int,
     *     days_for_delete: ?int
     * }
     */
    public function get(): array
    {
        $defaultKeep = config('backup-database.trash.keep_copies', 2);
        $defaultDays = config('backup-database.trash.days_for_delete');

        $defaults = [
            'auto_delete' => (bool) config('backup-database.trash.auto_delete', false),
            'keep_copies' => ($defaultKeep !== null && $defaultKeep !== '') ? max(0, (int) $defaultKeep) : 2,
            'days_for_delete' => ($defaultDays !== null && $defaultDays !== '') ? max(0, (int) $defaultDays) : null,
        ];

        $stored = $this->readRawFile();
        $merged = array_merge($defaults, $stored);

        $keep = $merged['keep_copies'] ?? null;
        $days = $merged['days_for_delete'] ?? null;

        return [
            'auto_delete' => (bool) ($merged['auto_delete'] ?? false),
            'keep_copies' => ($keep !== null && $keep !== '') ? max(0, (int) $keep) : 2,
            'days_for_delete' => ($days !== null && $days !== '') ? max(0, (int) $days) : null,
        ];
    }

    /**
     * Save trash retention settings from the dashboard panel.
     *
     * @return array{
     *     auto_delete: bool,
     *     keep_copies: int,
     *     days_for_delete: ?int
     * }
     */
    public function save(array $data): array
    {
        $days = array_key_exists('days_for_delete', $data) && $data['days_for_delete'] !== '' && $data['days_for_delete'] !== null
            ? max(0, (int) $data['days_for_delete'])
            : null;

        $payload = [
            'auto_delete' => (bool) ($data['auto_delete'] ?? false),
            'keep_copies' => max(0, (int) ($data['keep_copies'] ?? 2)),
            'days_for_delete' => $days,
            'updated_at' => now()->toIso8601String(),
        ];

        $path = $this->getSettingsPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->getForUi();
    }

    /**
     * Return UI-ready trash settings.
     *
     * @return array{
     *     auto_delete: bool,
     *     keep_copies: int,
     *     days_for_delete: ?int
     * }
     */
    public function getForUi(): array
    {
        return $this->get();
    }

    /**
     * Resolve effective trash settings for a specific connection, allowing optional per-connection overrides.
     *
     * @param  array<string, mixed>  $connection
     * @return array{
     *     auto_delete: bool,
     *     keep_copies: int,
     *     days_for_delete: ?int
     * }
     */
    public function resolveForConnection(array $connection): array
    {
        $global = $this->get();

        $autoDelete = array_key_exists('trash_auto_delete', $connection) && $connection['trash_auto_delete'] !== null
            ? (bool) $connection['trash_auto_delete']
            : $global['auto_delete'];

        $keepCopies = array_key_exists('trash_keep_copies', $connection) && $connection['trash_keep_copies'] !== null && $connection['trash_keep_copies'] !== ''
            ? max(0, (int) $connection['trash_keep_copies'])
            : $global['keep_copies'];

        $daysForDelete = array_key_exists('trash_days_for_delete', $connection)
            ? (($connection['trash_days_for_delete'] !== null && $connection['trash_days_for_delete'] !== '')
                ? max(0, (int) $connection['trash_days_for_delete'])
                : null)
            : $global['days_for_delete'];

        return [
            'auto_delete' => $autoDelete,
            'keep_copies' => $keepCopies,
            'days_for_delete' => $daysForDelete,
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
            Log::warning('BackupDatabase: Impossibile leggere il file impostazioni trash: '.$e->getMessage());

            return [];
        }
    }
}

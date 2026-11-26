<?php

namespace SalvatoreCervone\BackupDatabase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Http\Requests\DeleteBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\DeleteTrashBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\RestoreBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\SaveMailSettingsRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\SaveTrashSettingsRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\TestMailSettingsRequest;
use SalvatoreCervone\BackupDatabase\Support\BackupNotifier;
use SalvatoreCervone\BackupDatabase\Support\MailHistoryManager;
use SalvatoreCervone\BackupDatabase\Support\MailSettingsManager;
use SalvatoreCervone\BackupDatabase\Support\TrashSettingsManager;

class BackupController extends Controller
{
    protected MailSettingsManager $mailSettings;

    protected MailHistoryManager $mailHistory;

    protected TrashSettingsManager $trashSettings;

    protected BackupNotifier $notifier;

    public function __construct(
        protected BackupDatabase $backupService,
        ?MailSettingsManager $mailSettings = null,
        ?BackupNotifier $notifier = null,
        ?MailHistoryManager $mailHistory = null,
        ?TrashSettingsManager $trashSettings = null
    ) {
        $this->mailSettings = $mailSettings ?? new MailSettingsManager;
        $this->mailHistory = $mailHistory ?? new MailHistoryManager;
        $this->trashSettings = $trashSettings ?? new TrashSettingsManager;
        $this->notifier = $notifier ?? new BackupNotifier($this->mailSettings, $this->mailHistory);
    }

    /**
     * Display the backup dashboard.
     */
    public function index()
    {
        return view('backup-database::backups', [
            'listBackups' => $this->backupService->getStatus(),
            'trashBackups' => $this->backupService->getTrashStatus(),
            'mailSettings' => $this->mailSettings->getForUi(),
            'trashSettings' => $this->trashSettings->getForUi(),
            'mailHistory' => $this->mailHistory->all(),
        ]);
    }

    /**
     * Run backup for all configured connections.
     */
    public function create(): JsonResponse
    {
        $results = $this->backupService->backup();

        return response()->json($results);
    }

    /**
     * Delete a specific backup file.
     *
     * The client must provide both 'file' (basename only) and 'connection'
     * so that the full path is resolved server-side against the configured
     * destination path, preventing arbitrary file deletion.
     */
    public function delete(DeleteBackupRequest $request): JsonResponse
    {
        $fileName = $request->validated('file');
        $connectionName = $request->validated('connection');

        $result = $this->backupService->delete($connectionName, $fileName);

        return response()->json($result);
    }

    /**
     * Restore a database from a backup file.
     *
     * The client must provide both 'file' (basename only) and 'connection'.
     */
    public function restore(RestoreBackupRequest $request): JsonResponse
    {
        $fileName = $request->validated('file');
        $connectionName = $request->validated('connection');

        $result = $this->backupService->restore($connectionName, $fileName);

        return response()->json($result);
    }

    /**
     * Display the daily activity logs.
     */
    public function logs(): string
    {
        return $this->backupService->getLogs();
    }

    /**
     * Get current email notification and SMTP settings (with .env fallback info).
     */
    public function getMailSettings(): JsonResponse
    {
        return response()->json($this->mailSettings->getForUi());
    }

    /**
     * Save email notification and SMTP settings.
     */
    public function saveMailSettings(SaveMailSettingsRequest $request): JsonResponse
    {
        $saved = $this->mailSettings->save($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Impostazioni email salvate correttamente.',
            'settings' => $saved,
        ]);
    }

    /**
     * Send a test notification email to the single specified email address.
     */
    public function testMailSettings(TestMailSettingsRequest $request): JsonResponse
    {
        $result = $this->notifier->sendTest($request->validated('recipient'));

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    /**
     * Return the sent email history log.
     */
    public function getMailHistory(): JsonResponse
    {
        return response()->json([
            'data' => $this->mailHistory->all(),
        ]);
    }

    /**
     * Get current trash retention settings.
     */
    public function getTrashSettings(): JsonResponse
    {
        return response()->json($this->trashSettings->getForUi());
    }

    /**
     * Save trash retention settings.
     */
    public function saveTrashSettings(SaveTrashSettingsRequest $request): JsonResponse
    {
        $saved = $this->trashSettings->save($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Impostazioni cestino salvate correttamente.',
            'settings' => $saved,
        ]);
    }

    /**
     * Return all trashed backup files grouped by connection.
     */
    public function getTrash(): JsonResponse
    {
        return response()->json([
            'data' => $this->backupService->getTrashStatus(),
        ]);
    }

    /**
     * Permanently delete a single file from the trash folder.
     */
    public function deleteTrash(DeleteTrashBackupRequest $request): JsonResponse
    {
        $result = $this->backupService->deleteFromTrash(
            $request->validated('connection'),
            $request->validated('file')
        );

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    /**
     * Manually trigger trash retention cleanup (keeping at least keep_copies newest files per DB).
     */
    public function cleanupTrash(): JsonResponse
    {
        $results = $this->backupService->cleanupAllTrash(true);
        $deletedCount = count(array_filter($results, fn ($r) => ! empty($r['status'])));
        $failedCount = count(array_filter($results, fn ($r) => empty($r['status'])));

        if ($failedCount > 0) {
            $message = $deletedCount > 0
                ? "Pulizia cestino parziale: {$deletedCount} file eliminati, {$failedCount} errori."
                : "Errore durante la pulizia del cestino ({$failedCount} fallimenti). Controlla i log.";
        } elseif ($deletedCount > 0) {
            $message = "Pulizia cestino completata: {$deletedCount} file eliminati definitivamente.";
        } else {
            $message = 'Nessun file in eccesso da eliminare nel cestino (copie minime rispettate).';
        }

        return response()->json([
            'status' => $failedCount === 0,
            'deleted' => $deletedCount,
            'failed' => $failedCount,
            'results' => $results,
            'message' => $message,
            'trash' => $this->backupService->getTrashStatus(),
        ]);
    }
}

<?php

namespace SalvatoreCervone\BackupDatabase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Http\Requests\DeleteBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\RestoreBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\SaveMailSettingsRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\TestMailSettingsRequest;
use SalvatoreCervone\BackupDatabase\Support\BackupNotifier;
use SalvatoreCervone\BackupDatabase\Support\MailHistoryManager;
use SalvatoreCervone\BackupDatabase\Support\MailSettingsManager;

class BackupController extends Controller
{
    protected MailSettingsManager $mailSettings;

    protected MailHistoryManager $mailHistory;

    protected BackupNotifier $notifier;

    public function __construct(
        protected BackupDatabase $backupService,
        ?MailSettingsManager $mailSettings = null,
        ?BackupNotifier $notifier = null,
        ?MailHistoryManager $mailHistory = null
    ) {
        $this->mailSettings = $mailSettings ?? new MailSettingsManager;
        $this->mailHistory = $mailHistory ?? new MailHistoryManager;
        $this->notifier = $notifier ?? new BackupNotifier($this->mailSettings, $this->mailHistory);
    }

    /**
     * Display the backup dashboard.
     */
    public function index()
    {
        return view('backup-database::backups', [
            'listBackups' => $this->backupService->getStatus(),
            'mailSettings' => $this->mailSettings->getForUi(),
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
}

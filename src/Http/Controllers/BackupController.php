<?php

namespace SalvatoreCervone\BackupDatabase\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SalvatoreCervone\BackupDatabase\BackupDatabase;
use SalvatoreCervone\BackupDatabase\Http\Requests\DeleteBackupRequest;
use SalvatoreCervone\BackupDatabase\Http\Requests\RestoreBackupRequest;
use SalvatoreCervone\BackupDatabase\Security\PathValidator;

class BackupController extends Controller
{
    public function __construct(
        protected BackupDatabase $backupService
    ) {}

    /**
     * Display the backup dashboard.
     */
    public function index()
    {
        return view('backup-database::backups', [
            'listBackups' => $this->backupService->getStatus(),
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
}

<?php

namespace SalvatoreCervone\BackupDatabase\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $connectionResults;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $cleanupResults;

    public string $connectionName;

    public string $errorMessage;

    /**
     * @param  array<int, array<string, mixed>>|string  $connectionResultsOrName
     * @param  array<int, array<string, mixed>>|string  $cleanupResultsOrError
     */
    public function __construct(array|string $connectionResultsOrName, array|string $cleanupResultsOrError = [])
    {
        if (is_string($connectionResultsOrName)) {
            $this->connectionName = $connectionResultsOrName;
            $this->errorMessage = is_string($cleanupResultsOrError) ? $cleanupResultsOrError : '';
            $this->connectionResults = [[
                'connection' => $this->connectionName,
                'status' => false,
                'file' => null,
                'message' => $this->errorMessage,
            ]];
            $this->cleanupResults = [];

            return;
        }

        $this->connectionResults = $connectionResultsOrName;
        $this->cleanupResults = is_array($cleanupResultsOrError) ? $cleanupResultsOrError : [];

        $firstFailed = collect($this->connectionResults)->first(fn ($item) => empty($item['status']));
        $this->connectionName = (string) ($firstFailed['connection'] ?? ($this->connectionResults[0]['connection'] ?? 'N/A'));
        $this->errorMessage = (string) ($firstFailed['message'] ?? '');
    }

    public function build()
    {
        $timestamp = date('Y-m-d H:i:s');
        $total = count($this->connectionResults);
        $succeeded = count(array_filter($this->connectionResults, fn ($r) => ! empty($r['status'])));
        $failed = $total - $succeeded;
        $failedCleanups = count(array_filter($this->cleanupResults, fn ($r) => empty($r['status'])));

        $rows = '';
        foreach ($this->connectionResults as $item) {
            $ok = ! empty($item['status']);
            $badge = $ok
                ? '<span style="color: #065f46; background: #d1fae5; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">OK</span>'
                : '<span style="color: #991b1b; background: #fee2e2; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 600;">ERRORE</span>';

            $connection = e((string) ($item['connection'] ?? 'N/A'));
            $file = e((string) ($item['file'] ?? '-'));
            $message = e((string) ($item['message'] ?? ''));

            $rows .= <<<HTML
            <tr>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">{$connection}</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">{$badge}</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-family: monospace; font-size: 12px;">{$file}</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-size: 13px; white-space: pre-wrap;">{$message}</td>
            </tr>
            HTML;
        }

        $cleanupHtml = '';
        if (! empty($this->cleanupResults)) {
            $cleanupItems = '';
            foreach ($this->cleanupResults as $cleanup) {
                $ok = ! empty($cleanup['status']);
                $icon = $ok ? '&#10003;' : '&#10007;';
                $color = $ok ? '#065f46' : '#991b1b';
                $msg = e((string) ($cleanup['message'] ?? ''));
                $cleanupItems .= "<li style=\"margin-bottom: 4px; color: {$color};\">{$icon} {$msg}</li>";
            }

            $cleanupHtml = <<<HTML
            <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <h2 style="margin: 0 0 8px 0; font-size: 15px;">Pulizia vecchi backup</h2>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px;">{$cleanupItems}</ul>
            </div>
            HTML;
        }

        $subjectSuffix = $failed > 0
            ? "{$failed}/{$total} falliti"
            : "{$failedCleanups} errori pulizia";

        $html = <<<HTML
        <div style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; max-width: 720px;">
            <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; border-radius: 6px;">
                <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #991b1b;">Errore durante il set di Backup Database</h1>
                <p style="margin: 0; font-size: 13px; color: #4b5563;">
                    Totale: <strong>{$total}</strong> &bull;
                    Riusciti: <strong style="color: #065f46;">{$succeeded}</strong> &bull;
                    Falliti: <strong style="color: #991b1b;">{$failed}</strong> &bull;
                    Data: {$timestamp}
                </p>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 16px; border: 1px solid #e5e7eb;">
                <thead>
                    <tr style="background: #f3f4f6; text-align: left;">
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">Connessione</th>
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">Stato</th>
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">File</th>
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">Dettagli / Errore</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
            {$cleanupHtml}
        </div>
        HTML;

        return $this->subject("FALLIMENTO BACKUP - {$subjectSuffix}")
            ->html($html);
    }
}

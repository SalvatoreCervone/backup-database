<?php

namespace SalvatoreCervone\BackupDatabase\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $backups;

    /**
     * @param  array<int, array<string, mixed>>  $backups
     */
    public function __construct(array $backups)
    {
        $this->backups = $backups;
    }

    public function build()
    {
        $timestamp = date('Y-m-d H:i:s');
        $count = count($this->backups);

        $rows = '';
        foreach ($this->backups as $item) {
            $connection = e((string) ($item['connection'] ?? 'N/A'));
            $file = e((string) ($item['file'] ?? '-'));
            $message = e((string) ($item['message'] ?? 'Completato'));

            $rows .= <<<HTML
            <tr>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600;">{$connection}</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-family: monospace; font-size: 12px;">{$file}</td>
                <td style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-size: 13px;">{$message}</td>
            </tr>
            HTML;
        }

        $html = <<<HTML
        <div style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; max-width: 700px;">
            <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 16px; border-radius: 6px;">
                <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #065f46;">Backup completato con successo</h1>
                <p style="margin: 0; font-size: 13px; color: #047857;">Database salvati: {$count} &mdash; Data: {$timestamp}</p>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-top: 16px; border: 1px solid #e5e7eb;">
                <thead>
                    <tr style="background: #f3f4f6; text-align: left;">
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">Connessione</th>
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">File</th>
                        <th style="padding: 8px 10px; border-bottom: 1px solid #e5e7eb;">Dettagli</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;

        return $this->subject('BACKUP COMPLETATO CON SUCCESSO ('.$count.')')
            ->html($html);
    }
}

<?php

namespace SalvatoreCervone\BackupDatabase\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BackupFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $errorMessage;

    public string $connectionName;

    public function __construct(string $connectionName, string $errorMessage)
    {
        $this->connectionName = $connectionName;
        $this->errorMessage = $errorMessage;
    }

    public function build()
    {
        $connection = e($this->connectionName);
        $error = e($this->errorMessage);
        $timestamp = date('Y-m-d H:i:s');

        $html = <<<HTML
        <div style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; max-width: 640px;">
            <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; border-radius: 6px;">
                <h1 style="margin: 0 0 8px 0; font-size: 20px; color: #991b1b;">Il backup per la connessione {$connection} è fallito</h1>
                <p style="margin: 0; font-size: 13px; color: #6b7280;">Data: {$timestamp}</p>
            </div>
            <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <strong>Errore:</strong>
                <pre style="margin: 8px 0 0 0; white-space: pre-wrap; word-break: break-word; font-family: monospace; font-size: 13px;">{$error}</pre>
            </div>
        </div>
        HTML;

        return $this->subject('FALLIMENTO BACKUP: '.$this->connectionName)
            ->html($html);
    }
}

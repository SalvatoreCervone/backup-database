<?php

namespace SalvatoreCervone\BackupDatabase\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $smtpSource;

    public function __construct(string $smtpSource = 'env')
    {
        $this->smtpSource = $smtpSource;
    }

    public function build()
    {
        $timestamp = date('Y-m-d H:i:s');
        $sourceLabel = $this->smtpSource === 'panel'
            ? 'Impostazioni SMTP del pannello'
            : 'Configurazione .env di Laravel';

        $html = <<<HTML
        <div style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; max-width: 600px;">
            <div style="background: #eef2ff; border-left: 4px solid #4f46e5; padding: 16px; border-radius: 6px;">
                <h1 style="margin: 0 0 6px 0; font-size: 20px; color: #3730a3;">Email di test &mdash; Backup Database</h1>
                <p style="margin: 0; font-size: 14px;">La configurazione delle notifiche email funziona correttamente.</p>
                <p style="margin: 8px 0 0 0; font-size: 13px; color: #4b5563;">
                    Sorgente SMTP: <strong>{$sourceLabel}</strong><br>
                    Data invio: {$timestamp}
                </p>
            </div>
        </div>
        HTML;

        return $this->subject('TEST NOTIFICHE - Backup Database')
            ->html($html);
    }
}

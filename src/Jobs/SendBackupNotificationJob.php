<?php

namespace SalvatoreCervone\BackupDatabase\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SalvatoreCervone\BackupDatabase\Support\BackupNotifier;
use SalvatoreCervone\BackupDatabase\Support\MailSettingsManager;

class SendBackupNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts in case of transient SMTP errors.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public Mailable $mailable,
        public array $recipients
    ) {}

    public function handle(?MailSettingsManager $settings = null): void
    {
        $notifier = new BackupNotifier($settings ?? new MailSettingsManager);

        try {
            $notifier->deliverNow($this->mailable, $this->recipients);
        } catch (\Exception $e) {
            Log::error('BackupDatabase: Errore invio mail da coda: '.$e->getMessage());

            throw $e;
        }
    }
}

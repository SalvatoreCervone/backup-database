<?php

namespace SalvatoreCervone\BackupDatabase\Support;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use SalvatoreCervone\BackupDatabase\Jobs\SendBackupNotificationJob;
use SalvatoreCervone\BackupDatabase\Mail\BackupFailedMail;
use SalvatoreCervone\BackupDatabase\Mail\BackupSuccessMail;
use SalvatoreCervone\BackupDatabase\Mail\BackupSummaryMail;
use SalvatoreCervone\BackupDatabase\Mail\TestNotificationMail;

class BackupNotifier
{
    public const CUSTOM_MAILER = 'backup_database_smtp';

    public function __construct(
        protected ?MailSettingsManager $settings = null
    ) {
        $this->settings = $settings ?? new MailSettingsManager;
    }

    /**
     * Send success notification when all backups completed without errors.
     *
     * @param  array<int, array<string, mixed>>  $connectionResults
     */
    public function notifySuccess(array $connectionResults): void
    {
        if (! $this->settings->shouldNotifyOnSuccess() || empty($connectionResults)) {
            return;
        }

        $this->dispatch(new BackupSuccessMail($connectionResults));
    }

    /**
     * Send failure notification for a failed connection backup.
     */
    public function notifyFailure(string $connectionName, string $errorMessage): void
    {
        if (! $this->settings->shouldNotifyOnFailure()) {
            return;
        }

        $this->dispatch(new BackupFailedMail($connectionName, $errorMessage));
    }

    /**
     * Send summary notification at the end of a backup run.
     *
     * @param  array<int, array<string, mixed>>  $connectionResults
     * @param  array<int, array<string, mixed>>  $cleanupResults
     */
    public function notifySummary(array $connectionResults, array $cleanupResults = []): void
    {
        if (! $this->settings->shouldNotifySummary() || empty($connectionResults)) {
            return;
        }

        $this->dispatch(new BackupSummaryMail($connectionResults, $cleanupResults));
    }

    /**
     * Send a test notification email using the effective (panel or .env) SMTP configuration.
     *
     * @return array{status: bool, message: string, smtp_source: string}
     */
    public function sendTest(?string $recipientOverride = null): array
    {
        $recipients = $recipientOverride !== null && trim($recipientOverride) !== ''
            ? MailSettingsManager::parseRecipients($recipientOverride)
            : $this->settings->getRecipients();

        $effective = $this->settings->getEffectiveSmtpConfig();

        if (empty($recipients)) {
            return [
                'status' => false,
                'message' => 'Nessun indirizzo email destinatario configurato.',
                'smtp_source' => $effective['source'],
            ];
        }

        if (! $effective['is_configured']) {
            return [
                'status' => false,
                'message' => 'Configurazione SMTP mancante sia nel pannello che nel file .env.',
                'smtp_source' => $effective['source'],
            ];
        }

        try {
            $this->sendMailable(new TestNotificationMail($effective['source']), $recipients, $effective);

            return [
                'status' => true,
                'message' => 'Email di test inviata correttamente a: '.implode(', ', $recipients),
                'smtp_source' => $effective['source'],
            ];
        } catch (\Exception $e) {
            Log::error('BackupDatabase: Errore invio email di test: '.$e->getMessage());

            return [
                'status' => false,
                'message' => 'Errore invio email di test: '.$e->getMessage(),
                'smtp_source' => $effective['source'],
            ];
        }
    }

    /**
     * Dispatch a mailable safely via Laravel queue (or synchronously if use_queue is false).
     */
    protected function dispatch(Mailable $mailable): void
    {
        $recipients = $this->settings->getRecipients();

        if (empty($recipients)) {
            return;
        }

        $effective = $this->settings->getEffectiveSmtpConfig();

        if (! $effective['is_configured']) {
            Log::warning('BackupDatabase: Impossibile inviare email di notifica — configurazione SMTP non trovata né nel pannello né nel file .env.');

            return;
        }

        try {
            if (config('backup-database.notifications.use_queue', true)) {
                $job = new SendBackupNotificationJob($mailable, $recipients);

                if ($connection = config('backup-database.notifications.queue_connection')) {
                    $job->onConnection($connection);
                }

                if ($queue = config('backup-database.notifications.queue_name')) {
                    $job->onQueue($queue);
                }

                try {
                    app(Dispatcher::class)->dispatch($job);

                    return;
                } catch (\Exception $queueException) {
                    Log::warning('BackupDatabase: Impossibile accodare la mail, invio sincrono di fallback: '.$queueException->getMessage());
                }
            }

            $this->sendMailable($mailable, $recipients, $effective);
        } catch (\Exception $e) {
            Log::error('Impossibile inviare mail di notifica: '.$e->getMessage());
        }
    }

    /**
     * Immediately deliver a mailable using the effective (panel or .env) SMTP configuration.
     * Called by SendBackupNotificationJob inside the queue worker so custom panel SMTP
     * settings are resolved cleanly in the worker process.
     *
     * @param  array<int, string>  $recipients
     */
    public function deliverNow(Mailable $mailable, array $recipients): void
    {
        $effective = $this->settings->getEffectiveSmtpConfig();

        if (! $effective['is_configured']) {
            Log::warning('BackupDatabase: Configurazione SMTP non trovata durante elaborazione job in coda.');

            return;
        }

        $this->sendMailable($mailable, $recipients, $effective);
    }

    /**
     * Configure dynamic mailer when panel SMTP overrides exist, otherwise use default Laravel mailer (.env).
     *
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $effective
     */
    protected function sendMailable(Mailable $mailable, array $recipients, array $effective): void
    {
        if (! empty($effective['from_address'])) {
            $mailable->from($effective['from_address'], $effective['from_name'] ?: null);
        }

        if ($this->settings->hasCustomSmtpSettings()) {
            config([
                'mail.mailers.'.self::CUSTOM_MAILER => [
                    'transport' => 'smtp',
                    'host' => $effective['host'],
                    'port' => $effective['port'] ?? 587,
                    'encryption' => $effective['encryption'],
                    'username' => $effective['username'],
                    'password' => $effective['password'],
                    'timeout' => null,
                ],
            ]);

            $root = Mail::getFacadeRoot();
            if ($root !== null && method_exists($root, 'purge')) {
                Mail::purge(self::CUSTOM_MAILER);
            }

            Mail::mailer(self::CUSTOM_MAILER)->to($recipients)->send($mailable);

            return;
        }

        Mail::to($recipients)->send($mailable);
    }
}

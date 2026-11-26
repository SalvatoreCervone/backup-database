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

    protected MailHistoryManager $history;

    public function __construct(
        protected ?MailSettingsManager $settings = null,
        ?MailHistoryManager $history = null
    ) {
        $this->settings = $settings ?? new MailSettingsManager;
        $this->history = $history ?? new MailHistoryManager;
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
     * Send a single consolidated failure notification for the entire backup set.
     *
     * @param  array<int, array<string, mixed>>|string  $connectionResultsOrName
     * @param  array<int, array<string, mixed>>|string  $cleanupResultsOrError
     */
    public function notifyFailure(array|string $connectionResultsOrName, array|string $cleanupResultsOrError = []): void
    {
        if (! $this->settings->shouldNotifyOnFailure()) {
            return;
        }

        if (is_array($connectionResultsOrName) && empty($connectionResultsOrName)) {
            return;
        }

        $this->dispatch(new BackupFailedMail($connectionResultsOrName, $cleanupResultsOrError));
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
     * Send a test notification email ONLY to the explicitly specified single recipient.
     * Never broadcasts to all configured notification recipients.
     *
     * @return array{status: bool, message: string, smtp_source: string}
     */
    public function sendTest(?string $recipient = null): array
    {
        $effective = $this->settings->getEffectiveSmtpConfig();
        $email = trim((string) $recipient);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => false,
                'message' => 'Inserisci un indirizzo email valido per inviare il test.',
                'smtp_source' => $effective['source'],
            ];
        }

        if (! $effective['is_configured']) {
            return [
                'status' => false,
                'message' => 'Configurazione SMTP mancante per la modalità selezionata.',
                'smtp_source' => $effective['source'],
            ];
        }

        try {
            $mailable = new TestNotificationMail($effective['source']);

            if (config('backup-database.notifications.use_queue', true)) {
                $job = new SendBackupNotificationJob($mailable, [$email]);

                if ($connection = config('backup-database.notifications.queue_connection')) {
                    $job->onConnection($connection);
                }

                if ($queue = config('backup-database.notifications.queue_name')) {
                    $job->onQueue($queue);
                }

                try {
                    app(Dispatcher::class)->dispatch($job);

                    return [
                        'status' => true,
                        'queued' => true,
                        'message' => 'Email di test accodata correttamente per: '.$email,
                        'smtp_source' => $effective['source'],
                    ];
                } catch (\Exception $queueException) {
                    Log::warning('BackupDatabase: Impossibile accodare la mail di test, invio sincrono di fallback: '.$queueException->getMessage());
                }
            }

            $this->sendMailable($mailable, [$email], $effective, 'test');

            return [
                'status' => true,
                'queued' => false,
                'message' => 'Email di test inviata correttamente a: '.$email,
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
            Log::warning('BackupDatabase: Impossibile inviare email di notifica — configurazione SMTP non trovata.');

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
     * Configure dynamic mailer when manual SMTP mode is active, otherwise use default Laravel mailer (.env).
     * Records every send attempt in MailHistoryManager.
     *
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $effective
     */
    protected function sendMailable(Mailable $mailable, array $recipients, array $effective, ?string $type = null): void
    {
        $resolvedType = $type ?? $this->detectMailableType($mailable);
        $subject = $this->resolveMailableSubject($mailable);

        if (! empty($effective['from_address'])) {
            $mailable->from($effective['from_address'], $effective['from_name'] ?: null);
        }

        try {
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
            } else {
                Mail::to($recipients)->send($mailable);
            }

            $this->history->record([
                'type' => $resolvedType,
                'subject' => $subject,
                'recipients' => $recipients,
                'smtp_source' => $effective['source'],
                'status' => 'sent',
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->history->record([
                'type' => $resolvedType,
                'subject' => $subject,
                'recipients' => $recipients,
                'smtp_source' => $effective['source'],
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function detectMailableType(Mailable $mailable): string
    {
        return match (true) {
            $mailable instanceof BackupSuccessMail => 'success',
            $mailable instanceof BackupFailedMail => 'failure',
            $mailable instanceof BackupSummaryMail => 'summary',
            $mailable instanceof TestNotificationMail => 'test',
            default => 'other',
        };
    }

    protected function resolveMailableSubject(Mailable $mailable): string
    {
        if (method_exists($mailable, 'envelope')) {
            try {
                $envelope = $mailable->envelope();
                if (! empty($envelope->subject)) {
                    return (string) $envelope->subject;
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        if (! empty($mailable->subject)) {
            return (string) $mailable->subject;
        }

        return class_basename($mailable);
    }
}

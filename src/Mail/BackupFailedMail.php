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
        return $this->subject('FALLIMENTO BACKUP: ' . $this->connectionName)
                    ->html("<h1>Il backup per la connessione {$this->connectionName} è fallito</h1><p>Errore: {$this->errorMessage}</p>");
    }
}

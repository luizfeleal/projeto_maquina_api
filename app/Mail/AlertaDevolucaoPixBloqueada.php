<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertaDevolucaoPixBloqueada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $idE2E,
        public ?string $txid,
        public float $valor,
        public string $motivo,
        public ?int $idMaquina
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Devolução Pix bloqueada por limite insuficiente (Efí)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.devolucao_pix_bloqueada',
        );
    }
}

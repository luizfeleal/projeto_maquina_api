<?php

namespace App\Mail;

use App\Models\Mensalidade;
use App\Models\Clientes;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertaMensalidade5Dias extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Mensalidade $mensalidade,
        public Clientes $cliente
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lembrete: sua mensalidade vence em 5 dias',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mensalidade_5dias',
        );
    }
}

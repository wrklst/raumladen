<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class StaffInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public const int VALID_DAYS = 7;

    public function __construct(public User $user, public User $invitedBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invitation to the :shop backend', ['shop' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.staff-invitation',
            with: [
                'user' => $this->user,
                'invitedBy' => $this->invitedBy,
                'url' => URL::temporarySignedRoute('invitation.show', now()->addDays(self::VALID_DAYS), ['user' => $this->user]),
                'days' => self::VALID_DAYS,
            ],
        );
    }
}

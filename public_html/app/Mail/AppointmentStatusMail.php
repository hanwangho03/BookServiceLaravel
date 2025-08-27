<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $newStatus;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, string $newStatus)
    {
        $this->appointment = $appointment;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Thông báo về lịch hẹn của bạn';

        if ($this->newStatus === 'da_xac_nhan') {
            $subject = 'Lịch hẹn của bạn đã được phê duyệt';
        } elseif ($this->newStatus === 'da_huy') {
            $subject = 'Lịch hẹn của bạn đã bị hủy';
        }
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $viewName = ($this->newStatus === 'da_xac_nhan') 
            ? 'emails.appointments.approved' 
            : 'emails.appointments.canceled';

        return new Content(
            view: $viewName,
        );
    }
}

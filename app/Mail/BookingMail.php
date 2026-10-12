<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base for the emails a guest gets about one booking. Queued after commit, so a rolled back
 * transaction never sends mail about a booking that does not exist.
 */
abstract class BookingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    abstract protected function subjectLine(): string;

    abstract protected function markdownView(): string;

    /**
     * Extra view data a specific mail needs.
     *
     * @return array<string, mixed>
     */
    protected function extraData(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine().': '.$this->booking->code);
    }

    public function content(): Content
    {
        $this->booking->loadMissing('customer', 'bookingUnits.unit.unitType');

        return new Content(markdown: $this->markdownView(), with: [
            'booking' => $this->booking,
            'statusUrl' => route('booking.status', $this->booking->access_token),
            'checkoutUrl' => route('checkout.show', $this->booking->access_token),
            ...$this->extraData(),
        ]);
    }
}

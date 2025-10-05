<?php

declare(strict_types=1);

namespace FP_Exp\Booking;

use function array_key_exists;
use function preg_match;
use function strtolower;
use function trim;
use function vsprintf;

final class EmailTranslator
{
    public const LANGUAGE_IT = 'it';
    public const LANGUAGE_EN = 'en';

    /**
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        'customer_confirmation.subject' => [
            'it' => 'La tua prenotazione per %s',
            'en' => 'Your reservation for %s',
        ],
        'customer_confirmation.heading' => [
            'it' => 'Grazie per aver prenotato %s',
            'en' => 'Thank you for booking %s',
        ],
        'customer_confirmation.details_intro' => [
            'it' => 'Di seguito trovi tutti i dettagli della tua esperienza. Presentati con qualche minuto di anticipo e porta con te questa email (o il QR code se disponibile).',
            'en' => 'Here are the details of your experience. Please arrive a few minutes early and bring this email (or the QR code if available).',
        ],
        'customer_confirmation.participant_heading' => [
            'it' => 'Riepilogo partecipanti',
            'en' => 'Participant summary',
        ],
        'customer_confirmation.no_participants' => [
            'it' => 'Nessun partecipante registrato.',
            'en' => 'No participants registered.',
        ],
        'customer_confirmation.extras_heading' => [
            'it' => 'Extra selezionati',
            'en' => 'Selected extras',
        ],
        'customer_confirmation.order_heading' => [
            'it' => 'Informazioni ordine',
            'en' => 'Order information',
        ],
        'customer_confirmation.order_number' => [
            'it' => 'Numero ordine',
            'en' => 'Order number',
        ],
        'customer_confirmation.customer_notes' => [
            'it' => 'Note del cliente',
            'en' => 'Customer notes',
        ],
        'customer_confirmation.calendar_help' => [
            'it' => 'Troverai allegato un file .ics per aggiungere automaticamente la prenotazione al tuo calendario.',
            'en' => 'We attached an .ics file so you can add the reservation to your calendar automatically.',
        ],
        'customer_confirmation.calendar_cta' => [
            'it' => 'Aggiungi a Google Calendar',
            'en' => 'Add to Google Calendar',
        ],
        'customer_confirmation.contact' => [
            'it' => 'Contatto: %s',
            'en' => 'Contact: %s',
        ],
        'customer_confirmation.phone' => [
            'it' => 'Telefono: %s',
            'en' => 'Phone: %s',
        ],
        'customer_confirmation.support' => [
            'it' => 'Per qualsiasi richiesta rispondi a questa email, saremo felici di aiutarti.',
            'en' => 'Reply to this email for any questions — we are happy to help.',
        ],
        'customer_reminder.subject' => [
            'it' => 'Promemoria per %s',
            'en' => 'Reminder for %s',
        ],
        'customer_reminder.heading' => [
            'it' => 'Ci vediamo presto per %s',
            'en' => 'See you soon for %s',
        ],
        'customer_reminder.intro' => [
            'it' => 'Manca poco alla tua esperienza: ecco un promemoria con data, orario e punto di incontro.',
            'en' => 'Your experience is almost here — here is a quick reminder with the date, time, and meeting point.',
        ],
        'customer_reminder.remember' => [
            'it' => 'Ricorda di portare con te un documento valido e di arrivare con qualche minuto di anticipo.',
            'en' => 'Remember to bring a valid ID and arrive a few minutes early.',
        ],
        'customer_reminder.calendar_question' => [
            'it' => 'Hai già aggiunto l’evento al calendario?',
            'en' => 'Have you already added the event to your calendar?',
        ],
        'customer_reminder.calendar_cta' => [
            'it' => 'Aggiungi con un clic',
            'en' => 'Add with one click',
        ],
        'customer_reminder.support' => [
            'it' => 'Per qualsiasi richiesta rispondi a questa email: il nostro team è a tua disposizione.',
            'en' => 'Reply to this email if you need anything — our team is here for you.',
        ],
        'customer_post_experience.subject' => [
            'it' => 'Com’è andata %s?',
            'en' => 'How was %s?',
        ],
        'customer_post_experience.heading' => [
            'it' => 'Com’è andata %s?',
            'en' => 'How was %s?',
        ],
        'customer_post_experience.thanks' => [
            'it' => 'Grazie per aver partecipato! Ci farebbe piacere ricevere un tuo feedback per continuare a migliorare.',
            'en' => 'Thank you for joining us! We would love to hear your feedback so we can keep improving.',
        ],
        'customer_post_experience.review_request' => [
            'it' => 'Raccontaci cosa ti è piaciuto o cosa possiamo migliorare rispondendo a questa email oppure lasciando una recensione.',
            'en' => 'Tell us what you enjoyed or what we can improve by replying to this email or leaving a review.',
        ],
        'customer_post_experience.leave_review' => [
            'it' => 'Lascia una recensione',
            'en' => 'Leave a review',
        ],
        'customer_post_experience.signoff' => [
            'it' => 'Ti aspettiamo presto per una nuova esperienza!',
            'en' => 'We look forward to seeing you again soon!',
        ],
        'staff_notification.subject_new' => [
            'it' => 'Nuova prenotazione – %s',
            'en' => 'New reservation – %s',
        ],
        'staff_notification.subject_cancelled' => [
            'it' => 'Prenotazione annullata – %s',
            'en' => 'Reservation cancelled – %s',
        ],
        'staff_notification.summary' => [
            'it' => 'Riepilogo dettagli della prenotazione:',
            'en' => 'Reservation details summary:',
        ],
        'staff_notification.participants' => [
            'it' => 'Partecipanti',
            'en' => 'Participants',
        ],
        'staff_notification.extras' => [
            'it' => 'Extra',
            'en' => 'Extras',
        ],
        'staff_notification.customer_contact' => [
            'it' => 'Contatto cliente',
            'en' => 'Customer contact',
        ],
        'staff_notification.order' => [
            'it' => 'Ordine',
            'en' => 'Order',
        ],
        'staff_notification.order_number' => [
            'it' => 'Numero',
            'en' => 'Number',
        ],
        'staff_notification.open_order' => [
            'it' => 'Apri ordine in WooCommerce',
            'en' => 'Open order in WooCommerce',
        ],
        'staff_notification.customer_notes' => [
            'it' => 'Note del cliente',
            'en' => 'Customer notes',
        ],
        'common.date' => [
            'it' => 'Data',
            'en' => 'Date',
        ],
        'common.time' => [
            'it' => 'Orario',
            'en' => 'Time',
        ],
        'common.meeting_point' => [
            'it' => 'Punto di incontro',
            'en' => 'Meeting point',
        ],
        'common.total' => [
            'it' => 'Totale',
            'en' => 'Total',
        ],
        'common.status' => [
            'it' => 'Stato',
            'en' => 'Status',
        ],
        'common.phone' => [
            'it' => 'Telefono',
            'en' => 'Phone',
        ],
        'common.email' => [
            'it' => 'Email',
            'en' => 'Email',
        ],
        'common.default_footer' => [
            'it' => 'Ti aspettiamo presto per una nuova esperienza!',
            'en' => 'We look forward to seeing you again soon!',
        ],
        'common.status_cancelled' => [
            'it' => 'Annullata',
            'en' => 'Cancelled',
        ],
    ];

    private function __construct()
    {
    }

    public static function normalize(?string $language): string
    {
        $language = strtolower(trim((string) $language));

        if ('' === $language) {
            return self::LANGUAGE_EN;
        }

        if (preg_match('/^it/', $language)) {
            return self::LANGUAGE_IT;
        }

        return self::LANGUAGE_EN;
    }

    /**
     * @param array<int, string> $args
     */
    public static function text(string $key, string $language, array $args = []): string
    {
        $language = self::normalize($language);
        $map = self::MAP[$key] ?? [];

        if (! array_key_exists($language, $map)) {
            $language = self::LANGUAGE_EN;
        }

        $template = $map[$language] ?? '';

        if ('' === $template) {
            return '';
        }

        if (! $args) {
            return $template;
        }

        return vsprintf($template, $args);
    }
}

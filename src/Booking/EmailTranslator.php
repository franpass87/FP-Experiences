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
    public const LANGUAGE_DE = 'de';

    /**
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        'customer_confirmation.subject' => [
            'it' => 'La tua prenotazione per %s',
            'en' => 'Your reservation for %s',
            'de' => 'Ihre Buchung für %s',
        ],
        'customer_confirmation.heading' => [
            'it' => 'Grazie per aver prenotato %s',
            'en' => 'Thank you for booking %s',
            'de' => 'Vielen Dank für Ihre Buchung von %s',
        ],
        'customer_confirmation.details_intro' => [
            'it' => 'Di seguito trovi tutti i dettagli della tua esperienza. Presentati con qualche minuto di anticipo e porta con te questa email (o il QR code se disponibile).',
            'en' => 'Here are the details of your experience. Please arrive a few minutes early and bring this email (or the QR code if available).',
            'de' => 'Hier sind die Details Ihrer Erlebnis. Bitte kommen Sie einige Minuten früher und bringen Sie diese E-Mail (oder den QR-Code, falls verfügbar) mit.',
        ],
        'customer_confirmation.participant_heading' => [
            'it' => 'Riepilogo partecipanti',
            'en' => 'Participant summary',
            'de' => 'Teilnehmerübersicht',
        ],
        'customer_confirmation.no_participants' => [
            'it' => 'Nessun partecipante registrato.',
            'en' => 'No participants registered.',
            'de' => 'Keine Teilnehmer registriert.',
        ],
        'customer_confirmation.extras_heading' => [
            'it' => 'Extra selezionati',
            'en' => 'Selected extras',
            'de' => 'Ausgewählte Extras',
        ],
        'customer_confirmation.order_heading' => [
            'it' => 'Informazioni ordine',
            'en' => 'Order information',
            'de' => 'Bestellinformationen',
        ],
        'customer_confirmation.order_number' => [
            'it' => 'Numero ordine',
            'en' => 'Order number',
            'de' => 'Bestellnummer',
        ],
        'customer_confirmation.customer_notes' => [
            'it' => 'Note del cliente',
            'en' => 'Customer notes',
            'de' => 'Kundennotizen',
        ],
        'customer_confirmation.calendar_help' => [
            'it' => 'Troverai allegato un file .ics per aggiungere automaticamente la prenotazione al tuo calendario.',
            'en' => 'We attached an .ics file so you can add the reservation to your calendar automatically.',
            'de' => 'Wir haben eine .ics-Datei angehängt, damit Sie die Buchung automatisch zu Ihrem Kalender hinzufügen können.',
        ],
        'customer_confirmation.calendar_cta' => [
            'it' => 'Aggiungi a Google Calendar',
            'en' => 'Add to Google Calendar',
            'de' => 'Zu Google Kalender hinzufügen',
        ],
        'customer_confirmation.contact' => [
            'it' => 'Contatto: %s',
            'en' => 'Contact: %s',
            'de' => 'Kontakt: %s',
        ],
        'customer_confirmation.phone' => [
            'it' => 'Telefono: %s',
            'en' => 'Phone: %s',
            'de' => 'Telefon: %s',
        ],
        'customer_confirmation.support' => [
            'it' => 'Per qualsiasi richiesta rispondi a questa email, saremo felici di aiutarti.',
            'en' => 'Reply to this email for any questions — we are happy to help.',
            'de' => 'Antworten Sie auf diese E-Mail bei Fragen — wir helfen Ihnen gerne.',
        ],
        'customer_reminder.subject' => [
            'it' => 'Promemoria per %s',
            'en' => 'Reminder for %s',
            'de' => 'Erinnerung für %s',
        ],
        'customer_reminder.heading' => [
            'it' => 'Ci vediamo presto per %s',
            'en' => 'See you soon for %s',
            'de' => 'Bis bald für %s',
        ],
        'customer_reminder.intro' => [
            'it' => 'Manca poco alla tua esperienza: ecco un promemoria con data, orario e punto di incontro.',
            'en' => 'Your experience is almost here — here is a quick reminder with the date, time, and meeting point.',
            'de' => 'Ihr Erlebnis steht kurz bevor — hier ist eine kurze Erinnerung mit Datum, Uhrzeit und Treffpunkt.',
        ],
        'customer_reminder.remember' => [
            'it' => 'Ricorda di portare con te un documento valido e di arrivare con qualche minuto di anticipo.',
            'en' => 'Remember to bring a valid ID and arrive a few minutes early.',
            'de' => 'Denken Sie daran, einen gültigen Ausweis mitzubringen und einige Minuten früher zu kommen.',
        ],
        'customer_reminder.calendar_question' => [
            'it' => 'Hai già aggiunto l’evento al calendario?',
            'en' => 'Have you already added the event to your calendar?',
            'de' => 'Haben Sie das Ereignis bereits zu Ihrem Kalender hinzugefügt?',
        ],
        'customer_reminder.calendar_cta' => [
            'it' => 'Aggiungi con un clic',
            'en' => 'Add with one click',
            'de' => 'Mit einem Klick hinzufügen',
        ],
        'customer_reminder.support' => [
            'it' => 'Per qualsiasi richiesta rispondi a questa email: il nostro team è a tua disposizione.',
            'en' => 'Reply to this email if you need anything — our team is here for you.',
            'de' => 'Antworten Sie auf diese E-Mail, wenn Sie etwas brauchen — unser Team ist für Sie da.',
        ],
        'customer_post_experience.subject' => [
            'it' => 'Com’è andata %s?',
            'en' => 'How was %s?',
            'de' => 'Wie war %s?',
        ],
        'customer_post_experience.heading' => [
            'it' => 'Com’è andata %s?',
            'en' => 'How was %s?',
            'de' => 'Wie war %s?',
        ],
        'customer_post_experience.thanks' => [
            'it' => 'Grazie per aver partecipato! Ci farebbe piacere ricevere un tuo feedback per continuare a migliorare.',
            'en' => 'Thank you for joining us! We would love to hear your feedback so we can keep improving.',
            'de' => 'Vielen Dank, dass Sie dabei waren! Wir würden gerne Ihr Feedback hören, damit wir uns weiter verbessern können.',
        ],
        'customer_post_experience.review_request' => [
            'it' => 'Raccontaci cosa ti è piaciuto o cosa possiamo migliorare rispondendo a questa email oppure lasciando una recensione.',
            'en' => 'Tell us what you enjoyed or what we can improve by replying to this email or leaving a review.',
            'de' => 'Erzählen Sie uns, was Ihnen gefallen hat oder was wir verbessern können, indem Sie auf diese E-Mail antworten oder eine Bewertung hinterlassen.',
        ],
        'customer_post_experience.leave_review' => [
            'it' => 'Lascia una recensione',
            'en' => 'Leave a review',
            'de' => 'Bewertung hinterlassen',
        ],
        'customer_post_experience.signoff' => [
            'it' => 'Ti aspettiamo presto per una nuova esperienza!',
            'en' => 'We look forward to seeing you again soon!',
            'de' => 'Wir freuen uns darauf, Sie bald wiederzusehen!',
        ],
        'staff_notification.subject_new' => [
            'it' => 'Nuova prenotazione – %s',
            'en' => 'New reservation – %s',
            'de' => 'Neue Buchung – %s',
        ],
        'staff_notification.subject_cancelled' => [
            'it' => 'Prenotazione annullata – %s',
            'en' => 'Reservation cancelled – %s',
            'de' => 'Buchung storniert – %s',
        ],
        'staff_notification.summary' => [
            'it' => 'Riepilogo dettagli della prenotazione:',
            'en' => 'Reservation details summary:',
            'de' => 'Zusammenfassung der Buchungsdetails:',
        ],
        'staff_notification.participants' => [
            'it' => 'Partecipanti',
            'en' => 'Participants',
            'de' => 'Teilnehmer',
        ],
        'staff_notification.extras' => [
            'it' => 'Extra',
            'en' => 'Extras',
            'de' => 'Extras',
        ],
        'staff_notification.customer_contact' => [
            'it' => 'Contatto cliente',
            'en' => 'Customer contact',
            'de' => 'Kundenkontakt',
        ],
        'staff_notification.order' => [
            'it' => 'Ordine',
            'en' => 'Order',
            'de' => 'Bestellung',
        ],
        'staff_notification.order_number' => [
            'it' => 'Numero',
            'en' => 'Number',
            'de' => 'Nummer',
        ],
        'staff_notification.open_order' => [
            'it' => 'Apri ordine in WooCommerce',
            'en' => 'Open order in WooCommerce',
            'de' => 'Bestellung in WooCommerce öffnen',
        ],
        'staff_notification.customer_notes' => [
            'it' => 'Note del cliente',
            'en' => 'Customer notes',
            'de' => 'Kundennotizen',
        ],
        'common.date' => [
            'it' => 'Data',
            'en' => 'Date',
            'de' => 'Datum',
        ],
        'common.time' => [
            'it' => 'Orario',
            'en' => 'Time',
            'de' => 'Uhrzeit',
        ],
        'common.meeting_point' => [
            'it' => 'Punto di incontro',
            'en' => 'Meeting point',
            'de' => 'Treffpunkt',
        ],
        'common.total' => [
            'it' => 'Totale',
            'en' => 'Total',
            'de' => 'Gesamt',
        ],
        'common.status' => [
            'it' => 'Stato',
            'en' => 'Status',
            'de' => 'Status',
        ],
        'common.phone' => [
            'it' => 'Telefono',
            'en' => 'Phone',
            'de' => 'Telefon',
        ],
        'common.email' => [
            'it' => 'Email',
            'en' => 'Email',
            'de' => 'E-Mail',
        ],
        'common.default_footer' => [
            'it' => 'Ti aspettiamo presto per una nuova esperienza!',
            'en' => 'We look forward to seeing you again soon!',
            'de' => 'Wir freuen uns darauf, Sie bald wiederzusehen!',
        ],
        'common.status_cancelled' => [
            'it' => 'Annullata',
            'en' => 'Cancelled',
            'de' => 'Storniert',
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

        if (preg_match('/^de/', $language)) {
            return self::LANGUAGE_DE;
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

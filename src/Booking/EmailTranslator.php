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
        // RTB Payment Request
        'rtb_payment.heading' => [
            'it' => 'Completa il pagamento per %s',
            'en' => 'Complete your payment for %s',
            'de' => 'Schließen Sie Ihre Zahlung für %s ab',
        ],
        'rtb_payment.intro' => [
            'it' => 'Ottima notizia! La tua richiesta di prenotazione è stata approvata. Completa il pagamento per confermare la tua partecipazione.',
            'en' => 'Great news! Your booking request has been approved. Complete the payment to confirm your participation.',
            'de' => 'Tolle Neuigkeiten! Ihre Buchungsanfrage wurde genehmigt. Schließen Sie die Zahlung ab, um Ihre Teilnahme zu bestätigen.',
        ],
        'rtb_payment.total_to_pay' => [
            'it' => 'Totale da pagare',
            'en' => 'Total to pay',
            'de' => 'Zu zahlender Betrag',
        ],
        'rtb_payment.pay_now_button' => [
            'it' => 'Paga ora',
            'en' => 'Pay now',
            'de' => 'Jetzt bezahlen',
        ],
        'rtb_payment.link_fallback' => [
            'it' => 'Oppure copia questo link nel tuo browser',
            'en' => 'Or copy this link to your browser',
            'de' => 'Oder kopieren Sie diesen Link in Ihren Browser',
        ],
        'rtb_payment.footer_note' => [
            'it' => 'Se hai domande o necessiti di assistenza, rispondi a questa email e saremo felici di aiutarti.',
            'en' => 'If you have any questions or need assistance, reply to this email and we will be happy to help.',
            'de' => 'Bei Fragen oder wenn Sie Hilfe benötigen, antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.',
        ],
        // RTB Request Received
        'rtb_request.heading' => [
            'it' => 'Abbiamo ricevuto la tua richiesta per %s',
            'en' => 'We received your request for %s',
            'de' => 'Wir haben Ihre Anfrage für %s erhalten',
        ],
        'rtb_request.intro' => [
            'it' => 'Grazie per la tua richiesta di prenotazione. Il nostro team la esaminerà e ti risponderà a breve.',
            'en' => 'Thank you for your booking request. Our team will review it and get back to you shortly.',
            'de' => 'Vielen Dank für Ihre Buchungsanfrage. Unser Team wird sie prüfen und sich in Kürze bei Ihnen melden.',
        ],
        'rtb_request.status_pending' => [
            'it' => 'La tua richiesta è in attesa di approvazione. Ti contatteremo presto!',
            'en' => 'Your request is pending approval. We will contact you soon!',
            'de' => 'Ihre Anfrage wartet auf Genehmigung. Wir werden uns bald bei Ihnen melden!',
        ],
        'rtb_request.footer_note' => [
            'it' => 'Se hai domande, rispondi a questa email e saremo felici di aiutarti.',
            'en' => 'If you have any questions, reply to this email and we will be happy to help.',
            'de' => 'Bei Fragen antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.',
        ],
        // RTB Approved
        'rtb_approved.heading' => [
            'it' => 'La tua richiesta per %s è stata approvata!',
            'en' => 'Your request for %s has been approved!',
            'de' => 'Ihre Anfrage für %s wurde genehmigt!',
        ],
        'rtb_approved.intro' => [
            'it' => 'Ottime notizie! La tua richiesta di prenotazione è stata approvata. Non vediamo l\'ora di accoglierti.',
            'en' => 'Great news! Your booking request has been approved. We look forward to welcoming you.',
            'de' => 'Tolle Neuigkeiten! Ihre Buchungsanfrage wurde genehmigt. Wir freuen uns, Sie begrüßen zu dürfen.',
        ],
        'rtb_approved.status_approved' => [
            'it' => 'Prenotazione confermata',
            'en' => 'Booking confirmed',
            'de' => 'Buchung bestätigt',
        ],
        'rtb_approved.footer_note' => [
            'it' => 'Se hai domande, rispondi a questa email e saremo felici di aiutarti.',
            'en' => 'If you have any questions, reply to this email and we will be happy to help.',
            'de' => 'Bei Fragen antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.',
        ],
        // RTB Declined
        'rtb_declined.heading' => [
            'it' => 'Aggiornamento sulla tua richiesta per %s',
            'en' => 'Update on your request for %s',
            'de' => 'Aktualisierung zu Ihrer Anfrage für %s',
        ],
        'rtb_declined.intro' => [
            'it' => 'Purtroppo non siamo in grado di accogliere la tua richiesta di prenotazione in questo momento.',
            'en' => 'We regret to inform you that we are unable to accommodate your booking request at this time.',
            'de' => 'Leider können wir Ihre Buchungsanfrage derzeit nicht annehmen.',
        ],
        'rtb_declined.status_declined' => [
            'it' => 'Richiesta non disponibile',
            'en' => 'Request not available',
            'de' => 'Anfrage nicht verfügbar',
        ],
        'rtb_declined.reason' => [
            'it' => 'Motivo',
            'en' => 'Reason',
            'de' => 'Grund',
        ],
        'rtb_declined.alternative' => [
            'it' => 'Ti invitiamo a verificare altre date disponibili o a contattarci per trovare un\'alternativa.',
            'en' => 'We invite you to check other available dates or contact us to find an alternative.',
            'de' => 'Wir laden Sie ein, andere verfügbare Termine zu prüfen oder uns zu kontaktieren, um eine Alternative zu finden.',
        ],
        'rtb_declined.footer_note' => [
            'it' => 'Se hai domande, rispondi a questa email e saremo felici di aiutarti.',
            'en' => 'If you have any questions, reply to this email and we will be happy to help.',
            'de' => 'Bei Fragen antworten Sie auf diese E-Mail und wir helfen Ihnen gerne.',
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

<?php

declare(strict_types=1);

namespace FP_Exp\Localization;

use FP_Exp\Core\Hook\HookableInterface;

use function add_filter;

final class AutoTranslator implements HookableInterface
{
    /**
     * @var array<string, string>
     */
    private const STRINGS = [
        'Completa i campi obbligatori.' => 'Please complete the required fields.',
        'Impossibile preparare il checkout del regalo. Riprova più tardi.' => 'Unable to prepare the gift checkout. Please try again later.',
        'Non è stato possibile avviare il checkout regalo. Riprova.' => 'We could not start the gift checkout. Please try again.',
        'Non è stato possibile avviare il checkout regalo. Riprova più tardi.' => 'We could not start the gift checkout. Please try again later.',
        'Inserisci un codice voucher per continuare.' => 'Enter a voucher code to continue.',
        'Non abbiamo trovato questo voucher. Controlla il codice e riprova.' => 'We could not find that voucher. Check the code and try again.',
        'Non è stato possibile riscattare il voucher. Prova un altro slot o contatta l’assistenza.' => 'We could not redeem the voucher. Try a different slot or contact support.',
        'Voucher riscattato! Controlla la tua casella di posta per la conferma.' => 'Voucher redeemed! Check your inbox for confirmation.',
        'Vedi esperienza' => 'View experience',
        'Nessuno slot futuro disponibile. Contatta l’operatore per pianificare manualmente.' => 'No upcoming slots are available. Please contact the operator to schedule manually.',
        'Impossibile caricare il voucher in questo momento. Riprova più tardi.' => 'Unable to load the voucher at this time. Please try again later.',
        'Invio della richiesta…' => 'Sending your request…',
        'Richiesta ricevuta! Ti risponderemo al più presto.' => 'Request received! We will reply soon.',
        'Impossibile inviare la richiesta. Riprova.' => 'Unable to submit your request. Please try again.',
        'Checkout regalo avviato. Segui i prossimi passaggi per completare il pagamento.' => 'Gift checkout initialised. Follow the next steps to complete payment.',
        'Grazie! Abbiamo ricevuto la tua richiesta e il team confermerà la disponibilità a breve.' => 'Thank you! Your request was received and our team will confirm availability shortly.',
        'Grazie per la richiesta. Il nostro team ti ricontatterà a breve.' => 'Thank you for your request. Our team will get back to you shortly.',
        'Seleziona una data per vedere le fasce orarie' => 'Select a date to view time slots',
        'Seleziona i biglietti per vedere il riepilogo' => 'Select tickets to see the summary',
        'Seleziona i biglietti' => 'Select tickets',
        'Scegli una data' => 'Choose a date',
        'Scegli un orario per confermare prezzo e disponibilità.' => 'Choose a time to confirm price and availability.',
        'Inserisci il codice voucher per vedere le date disponibili e confermare la prenotazione.' => 'Enter your voucher code to view the available dates and confirm your reservation.',
        'Utilizza il tuo voucher esperienza' => 'Use your experience voucher',
        'Codice voucher' => 'Voucher code',
        'Verifica voucher' => 'Verify voucher',
        'Scegli data e ora' => 'Select a date and time',
        'Inserisci il tuo nome.' => 'Enter your name.',
        'Inserisci il tuo indirizzo email.' => 'Enter your email address.',
        'Inserisci un indirizzo email valido.' => 'Enter a valid email address.',
        'Impossibile aggiornare il prezzo. Riprova.' => 'We could not refresh the price. Please try again.',
        'Controlla i campi evidenziati:' => 'Please review the highlighted fields:',
        'Controlla i campi evidenziati.' => 'Please review the highlighted fields.',
        'Completa il campo %s.' => 'Please complete the %s field.',
        'Riduci %s' => 'Decrease %s',
        'Aumenta %s' => 'Increase %s',
        'Nessuna esperienza disponibile al momento. Torna a trovarci presto.' => 'No experiences are available right now. Please check back soon.',
        'La sessione è scaduta. Aggiorna la pagina e riprova.' => 'Your session has expired. Please refresh and try again.',
        'Attendi prima di inviare un’altra richiesta.' => 'Please wait before sending another request.',
        'Seleziona data e ora prima di inviare la richiesta.' => 'Select a date and time before submitting your request.',
        'Seleziona data e ora prima di proseguire.' => 'Select a date and time before continuing.',
        'Lo slot selezionato non è più disponibile.' => 'The selected slot is no longer available.',
        'Lo slot selezionato non può accettare altri partecipanti.' => 'The selected slot cannot accept more guests.',
        'Fornisci un indirizzo email valido per poterti rispondere.' => 'Please provide a valid email address so we can reply to your request.',
        'Devi accettare l’informativa privacy per inviare la richiesta.' => 'You must accept the privacy policy to send a request.',
        'Impossibile registrare la richiesta. Riprova.' => 'We could not record your request. Please try again.',
        'Attendi prima di richiedere un nuovo preventivo.' => 'Please wait before requesting a new quote.',
        'Impossibile generare l’ordine di pagamento. Riprova.' => 'Unable to generate the payment order. Please try again.',
        'Attendi prima di inviare un nuovo tentativo di checkout.' => 'Please wait before submitting another checkout attempt.',
        'Il carrello esperienze è vuoto.' => 'Your experience cart is empty.',
        'Lo slot selezionato è al completo.' => 'Selected slot is sold out.',
        'Svuota il carrello di WooCommerce prima di prenotare un’esperienza.' => 'Please empty your WooCommerce cart before booking an experience.',
        'Le esperienze non possono essere acquistate insieme ad altri prodotti. Completa prima la prenotazione.' => 'Experiences cannot be purchased together with other products. Please complete your booking first.',
        'Impossibile creare l’ordine. Riprova.' => 'Unable to create the order. Please try again.',
        'Impossibile registrare la prenotazione. Riprova.' => 'Unable to record your reservation. Please try again.',
        'Impossibile aggiornare lo slot. Riprova.' => 'Unable to update the slot. Please try again.',
        'Impossibile caricare il calendario. Riprova.' => 'Unable to load the calendar. Please try again.',
        'Attendi prima di eseguire di nuovo la sincronizzazione Brevo.' => 'Please wait before running the Brevo sync again.',
        'Il replay degli eventi è stato eseguito da poco. Riprova più tardi.' => 'Event replay recently executed. Please retry shortly.',
        'Slot disponibile' => 'Available slot',
        'Cerca un voucher prima di scegliere uno slot.' => 'Look up a voucher before choosing a slot.',
        'Seleziona uno slot disponibile per continuare.' => 'Select an available slot to continue.',
        'Impossibile registrare il riscatto del voucher. Riprova.' => 'Unable to record the voucher redemption. Please try again.',
        'Il voucher collegato alla tua esperienza FP è scaduto. Contatta l’operatore per assistenza.' => 'The voucher linked to your FP Experience gift has expired. Please contact the operator for assistance.',
        'Troppe modifiche al calendario in poco tempo. Riprova tra qualche istante.' => 'Too many calendar changes in a short period. Please retry in a moment.',
        'Attendi prima di modificare nuovamente la capacità.' => 'Please wait before adjusting capacity again.',
        'La sessione OAuth è scaduta. Riprova.' => 'OAuth session expired. Please try again.',
        'Accetta l’informativa privacy per continuare.' => 'Accept the privacy policy to continue.',
        'Accetto di ricevere aggiornamenti marketing sulle prossime esperienze.' => 'I agree to receive marketing updates about future experiences.',
        'Accetto l’informativa privacy e le condizioni di prenotazione.' => 'I agree to the privacy policy and terms of booking.',
        'Aggiornamento del prezzo…' => 'Updating price…',
        'CAP' => 'Postal code',
        'Città' => 'City',
        'Completa la tua prenotazione' => 'Complete your booking',
        'Disponibilità di %s' => '%s availability',
        'Da %s' => 'From %s',
        'Indirizzo' => 'Address',
        'Invia richiesta di prenotazione' => 'Send booking request',
        'I metodi di pagamento saranno caricati qui dai gateway WooCommerce.' => 'Payment methods will load here from WooCommerce gateways.',
        'La tua selezione di esperienze apparirà qui.' => 'Your experience selection will appear here.',
        'Desidero ricevere novità e aggiornamenti marketing.' => 'I would like to receive news and marketing updates.',
        'Nome' => 'First name',
        'Nome e cognome' => 'Name and surname',
        'Note o richieste speciali' => 'Notes or special requests',
        'Numero di telefono' => 'Phone number',
        'Paese' => 'Country',
        'Prezzo' => 'Price',
        'Prezzo base' => 'Base price',
        'Procedi al checkout' => 'Proceed to checkout',
        'Quantità' => 'Quantity',
        'Quantità per %s' => '%s quantity',
        'Riepilogo' => 'Summary',
        'Tasse incluse dove applicabile.' => 'Taxes included where applicable.',
        'Telefono' => 'Phone',
        'Tipo di biglietto' => 'Ticket type',
        'Totale' => 'Total',
        '%d minuti' => '%d minutes',
        '%d posti disponibili' => '%d places left',
        '%d slot' => '%d slots',
        // Pagina riscatto voucher (gift-redeem)
        'Codice' => 'Code',
        'Ospiti' => 'Guests',
        'Valido fino al' => 'Valid until',
        'Valore voucher' => 'Voucher value',
        'Extra inclusi' => 'Extras included',
        'Conferma utilizzo' => 'Confirm use',
        'Verifica in corso...' => 'Verifying…',
        'Conferma in corso...' => 'Confirming…',
        'Voucher utilizzato' => 'Voucher used',
        'Nessuna disponibilita trovata per questo voucher.' => 'No availability found for this voucher.',
        'Voucher utilizzato con successo! La prenotazione e stata confermata.' => 'Voucher used successfully! Your reservation has been confirmed.',
        'Impossibile verificare il voucher. Riprova.' => 'Unable to verify the voucher. Please try again.',
        'Impossibile completare il riscatto. Riprova.' => 'Unable to complete redemption. Please try again.',
        // Widget / pagina esperienza – label e step
        'Controlla disponibilità' => 'Check availability',
        'Da' => 'From',
        'Lingue disponibili' => 'Available languages',
        'Durata' => 'Duration',
        'Tipo biglietto' => 'Ticket type',
        'Quantità %s' => '%s quantity',
        'Caricamento...' => 'Loading…',
        'Seleziona almeno 1 biglietto' => 'Select at least 1 ticket',
        'Seleziona data e orario' => 'Select date and time',
        'Scegli la lingua del tour' => 'Choose tour language',
        'Lingua del tour' => 'Tour language',
        '-- Seleziona lingua --' => '-- Select language --',
        'Seleziona la lingua in cui preferisci svolgere il tour.' => 'Select the language in which you prefer to take the tour.',
        'Extra' => 'Extras',
        'Nessuna immagine disponibile per questo extra' => 'No image available for this add-on',
        'Richieste speciali' => 'Special requests',
        'Richieste alimentari' => 'Dietary requirements',
        'Dieta vegana' => 'Vegan diet',
        'Dieta vegetariana' => 'Vegetarian diet',
        'Celiaco / Intolleranza al glutine' => 'Celiac / Gluten intolerance',
        'Allergie alimentari' => 'Food allergies',
        'Accessibilità' => 'Accessibility',
        'Mobilità ridotta / Accesso facilitato' => 'Reduced mobility / Easy access',
        'Gravidanza' => 'Pregnancy',
        'Celebrazioni' => 'Celebrations',
        'Compleanno / Anniversario / Evento speciale' => 'Birthday / Anniversary / Special occasion',
        'Altre richieste o note' => 'Other requests or notes',
        'Specifica eventuali dettagli aggiuntivi...' => 'Specify any additional details…',
        'Indica eventuali richieste o esigenze particolari che dovremmo conoscere per organizzare al meglio la tua esperienza.' => 'Let us know any special requests or needs we should be aware of to make your experience the best.',
        'Tasse incluse ove applicabile.' => 'Taxes included where applicable.',
        'Aggiornamento prezzo…' => 'Updating price…',
        'Note o richieste particolari' => 'Notes or special requests',
        'Desidero ricevere novità e comunicazioni di marketing.' => 'I would like to receive news and marketing communications.',
        'Accetto l\'informativa privacy e i termini di prenotazione.' => 'I agree to the privacy policy and booking terms.',
        'Email' => 'Email',
        'Vedi galleria' => 'View gallery',
        'Perché prenotare con noi' => 'Why book with us',
        'Caratteristiche esperienza' => 'Experience features',
        'Caratteristiche evento' => 'Event features',
        'Uno sguardo all\'esperienza' => 'A look at the experience',
        'Uno sguardo all\'evento' => 'A look at the event',
        'Regala questa esperienza' => 'Gift this experience',
        'Regala questo evento' => 'Gift this event',
        'Acquista un voucher e invialo con un messaggio personalizzato in pochi clic.' => 'Buy a voucher and send it with a personalised message in a few clicks.',
        'Chiudi modulo regalo' => 'Close gift form',
        'Acquista un voucher, personalizza un messaggio e invialo via email in pochi clic.' => 'Buy a voucher, personalise a message and send it by email in a few clicks.',
        'Chi regala' => 'Your details',
        'Chi riceve il regalo' => 'Recipient details',
        'Il tuo nome' => 'Your name',
        'La tua email' => 'Your email',
        'Nome destinatario' => 'Recipient name',
        'Email destinatario' => 'Recipient email',
        'Data di consegna (opzionale)' => 'Delivery date (optional)',
        'Invieremo il regalo via email alle 9:00 (Europe/Rome). Lascia vuoto per inviarlo subito dopo il pagamento.' => 'We will send the gift by email at 9:00 (Europe/Rome). Leave empty to send it right after payment.',
        'Biglietti regalo' => 'Gift tickets',
        'Messaggio personale (opzionale)' => 'Personal message (optional)',
        'Extra prepagati' => 'Prepaid add-ons',
        'Potrai verificare il totale e completare il pagamento al checkout. Il destinatario riceverà il voucher la mattina programmata o immediatamente dopo il pagamento se non è stata selezionata una data. La data dell\'esperienza verrà scelta dal destinatario in fase di riscatto del voucher.' => 'You can review the total and complete payment at checkout. The recipient will receive the voucher on the scheduled morning or immediately after payment if no date was selected. The experience date will be chosen by the recipient when redeeming the voucher.',
        'Potrai verificare il totale e completare il pagamento al checkout. Il destinatario riceverà il voucher via email la mattina della data di consegna che eventualmente imposti sopra (campo opzionale), oppure subito dopo il pagamento se lo lasci vuoto. Questo evento ha data e orario già definiti: al riscatto il destinatario confermerà i dati per utilizzare il voucher, senza scegliere un\'altra data.' => 'You can review the total and complete payment at checkout. The recipient will receive the voucher by email on the morning of the delivery date you set above (optional), or immediately after payment if you leave it blank. This event\'s date and time are already fixed: when redeeming, the recipient will confirm their details to use the voucher without choosing a different date.',
        'Perché questa esperienza è speciale' => 'Why this experience is special',
        'Perché questo evento è speciale' => 'Why this event is special',
        'Cosa aspettarsi il giorno dell\'esperienza e cosa è a pagamento.' => 'What to expect on the day of the experience and what costs extra.',
        'Cosa aspettarsi il giorno dell\'evento e cosa è a pagamento.' => 'What to expect on the day of the event and what costs extra.',
        // Mesi (per datepicker/calendario)
        'Gennaio' => 'January',
        'Febbraio' => 'February',
        'Marzo' => 'March',
        'Aprile' => 'April',
        'Maggio' => 'May',
        'Giugno' => 'June',
        'Luglio' => 'July',
        'Agosto' => 'August',
        'Settembre' => 'September',
        'Ottobre' => 'October',
        'Novembre' => 'November',
        'Dicembre' => 'December',
        // Stringhe in inglese nel codice (valore = EN, chiave = IT per lookupItalian quando locale IT)
        'Codice voucher non fornito.' => 'Voucher code not provided.',
        'Formato codice voucher non valido.' => 'Invalid voucher code format.',
        'Voucher non trovato.' => 'Voucher not found.',
        'I voucher regalo sono disabilitati.' => 'Gift vouchers are disabled.',
        'Il codice voucher è obbligatorio.' => 'Voucher code is required.',
        'Impossibile creare l\'ordine di riscatto.' => 'Unable to create the redemption order.',
        'Seleziona uno slot orario per riscattare il voucher.' => 'Select a timeslot to redeem the voucher.',
        'Lo slot selezionato non può accogliere la quantità del voucher.' => 'The selected slot cannot accommodate the voucher quantity.',
        'WooCommerce è richiesto per riscattare i voucher.' => 'WooCommerce is required to redeem vouchers.',
        'Impossibile calcolare il prezzo per il voucher.' => 'Unable to calculate a price for the voucher.',
        // Email voucher – stringhe italiane → EN
        'Come usare il tuo regalo:' => 'How to use your gift:',
        'Il tuo codice regalo è anche un coupon sconto da usare al checkout:' => 'Your gift code is also a discount coupon to use at checkout:',
        'Valore: %s %s' => 'Value: %s %s',
        'Istruzioni:' => 'Instructions:',
        'Visita la pagina dell\'esperienza e scegli data e orario' => 'Visit the experience page and choose date and time',
        'Aggiungi al carrello e procedi al checkout' => 'Add to cart and proceed to checkout',
        'Inserisci il codice coupon durante il pagamento' => 'Enter the coupon code during payment',
        'Lo sconto verrà applicato automaticamente!' => 'The discount will be applied automatically!',
        'Prenota ora' => 'Book now',
        // Email voucher – stringhe inglesi nel codice → IT (per lookupItalian)
        'Hai ricevuto un regalo: %s' => 'You received a gift: %s',
        'Hai ricevuto un voucher regalo per un\'esperienza FP!' => 'You have received a gift voucher for an FP Experience!',
        'Il tuo voucher regalo è stato inviato al destinatario.' => 'Your gift voucher was sent to the recipient.',
        'Codice voucher:' => 'Voucher code:',
        'Voucher regalo inviato' => 'Gift voucher dispatched',
        'Promemoria: il tuo regalo esperienza ti aspetta' => 'Reminder: your experience gift is waiting',
        'Il tuo voucher regalo scadrà tra %d giorno/i.' => 'Your gift voucher will expire in %d day(s).',
        'Valido fino al:' => 'Valid until:',
        'Prenota la tua esperienza' => 'Schedule your experience',
        'Il tuo regalo esperienza è scaduto' => 'Your experience gift has expired',
        'La tua esperienza regalo è stata prenotata' => 'Your gift experience is booked',
        'Il tuo voucher regalo è stato riscattato con successo.' => 'Your gift voucher has been successfully redeemed.',
        'Prenotato per:' => 'Scheduled for:',
        'Esperienza FP' => 'FP Experience',
        // JS fallback – calendario/slots/readmore
        'Leggi di più' => 'Read more',
        'Mostra meno' => 'Show less',
        'Nessuna fascia disponibile per questa data' => 'No slots available for this date',
        'Nessuna fascia disponibile' => 'No slots available',
        'Errore caricamento calendario' => 'Calendar loading error',
        'Impossibile caricare gli slot. Riprova.' => 'Unable to load slots. Please try again.',
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const PLURALS = [
        '%d posto disponibile||%d posti disponibili' => ['%d spot left', '%d spots left'],
        '%d posto||%d posti' => ['%d spot', '%d spots'],
        '%d ospite||%d ospiti' => ['%d guest', '%d guests'],
    ];

    public function register_hooks(): void
    {
        add_filter('gettext', [$this, 'translate'], 10, 3);
        add_filter('ngettext', [$this, 'translate_plural'], 10, 5);
    }

    /**
     * @return array<string, string>
     */
    public static function strings(): array
    {
        return self::STRINGS;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function plurals(): array
    {
        return self::PLURALS;
    }

    public function translate(string $translation, string $text, string $domain): string
    {
        if ('fp-experiences' !== $domain) {
            return $translation;
        }

		if ($this->should_translate_to_english()) {
            if (isset(self::STRINGS[$text])) {
                return self::STRINGS[$text];
            }

            $english = self::lookupEnglish($translation);
            if (null !== $english) {
                return $english;
            }

            return $translation;
        }

        $italian = self::lookupItalian($text);
        if (null !== $italian) {
            return $italian;
        }

        $italian = self::lookupItalian($translation);
        if (null !== $italian) {
            return $italian;
        }

        return $translation;
    }

    /**
     * @param mixed $translation
     * @param mixed $single
     * @param mixed $plural
     * @param mixed $number
     * @return mixed
     */
    public function translate_plural($translation, $single, $plural, $number, string $domain)
    {
        if ('fp-experiences' !== $domain) {
            return $translation;
        }

        $single = (string) $single;
        $plural = (string) $plural;
        $number = (int) $number;

		if ($this->should_translate_to_english()) {
            $key = $single . '||' . $plural;
            if (isset(self::PLURALS[$key])) {
                $pair = self::PLURALS[$key];
                return $number === 1 ? ($pair[0] ?: $single) : ($pair[1] ?: $plural);
            }

            return $translation;
        }

        foreach (self::PLURALS as $italianKey => $pair) {
            $italianParts = explode('||', $italianKey);
            if (2 !== count($italianParts)) {
                continue;
            }

            if ($pair[0] === $single && $pair[1] === $plural) {
                return $number === 1 ? $italianParts[0] : $italianParts[1];
            }

            if ($italianParts[0] === $single && $italianParts[1] === $plural) {
                return $number === 1 ? $italianParts[0] : $italianParts[1];
            }
        }

        return $translation;
    }

    private static function lookupEnglish(string $maybeItalian): ?string
    {
        if (isset(self::STRINGS[$maybeItalian])) {
            return self::STRINGS[$maybeItalian];
        }

        return null;
    }

    private static function lookupItalian(string $maybeEnglish): ?string
    {
        foreach (self::STRINGS as $italian => $english) {
            if ($english === $maybeEnglish) {
                return $italian;
            }
        }

        return null;
    }

    private function is_english_browser(): bool
    {
        $header = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        if ('' === $header) {
            return false;
        }

        $languages = explode(',', $header);
        foreach ($languages as $language) {
            $language = strtolower(trim($language));
            if ('' === $language) {
                continue;
            }

            $language = explode(';', $language)[0];
            if ('' === $language) {
                continue;
            }

            if (0 === strpos($language, 'en')) {
                return true;
            }
        }

        return false;
    }

	private function is_site_locale_english(): bool
	{
		$locale = '';
		if (\function_exists('determine_locale')) {
			$locale = (string) \determine_locale();
		} elseif (\function_exists('get_locale')) {
			$locale = (string) \get_locale();
		}

		if ('' === $locale) {
			return false;
		}

		$locale = strtolower($locale);
		return 0 === strpos($locale, 'en');
	}

	private function should_translate_to_english(): bool
	{
		// Usa la classe di compatibilità multilingua unificata
		$current_lang = \FP_Exp\Compatibility\Multilanguage::get_current_language();

		if ($current_lang === 'en') {
			return true;
		}

		// Fallback: se nessun plugin multilingua è attivo, verifica browser + locale sito
		if (!\FP_Exp\Compatibility\Multilanguage::is_multilanguage_active()) {
			return $this->is_english_browser() && $this->is_site_locale_english();
		}

		return false;
	}
}

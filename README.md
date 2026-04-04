# FP Experiences

Plugin WordPress per booking di esperienze turistiche stile GetYourGuide. Shortcode/Elementor, carrello e checkout isolati, email transazionali, tracking marketing centralizzato.

[![Version](https://img.shields.io/badge/version-1.6.30-blue.svg)](https://github.com/franpass87/FP-Experiences)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Per l'utente

### Cosa fa
FP Experiences permette di vendere esperienze (tour, degustazioni, corsi, ecc.) con un sistema di booking completo, separato dal normale shop WooCommerce.

### Funzionalità principali
- **Calendario disponibilità** con gestione capacità per slot
- **Acquisto diretto** o **Request to Book (RTB)** per esperienze su richiesta
- **Gift voucher** acquistabili e riscattabili tramite WooCommerce
- **Email transazionali** personalizzate (conferma, approvazione RTB, voucher) con branding
- **Integrazione Brevo** per email marketing (opzionale)
- **Google Calendar** per sincronizzazione prenotazioni (opzionale)
- **Modalità simulazione locale** per Brevo e Google Calendar senza credenziali reali
- **Tracking marketing**: GA4, Meta Pixel, Google Ads, Clarity tramite FP Marketing Tracking Layer
- **Multilingua**: supporto WPML con metabox e badge traducibili
- **Integrazione FP Restaurant**: ruoli operatori condivisi

### Shortcode disponibili
| Shortcode | Descrizione |
|-----------|-------------|
| `[fp_exp_list]` | Lista esperienze con filtri |
| `[fp_exp_single id="X"]` | Singola esperienza con calendario |
| `[fp_exp_widget id="X"]` | Widget compatto con prezzo e CTA |
| `[fp_exp_checkin]` | Pagina check-in operatore |

### Requisiti
- WordPress 6.2+
- PHP 8.0+
- WooCommerce (per pagamenti e gift voucher)
- FP Marketing Tracking Layer (opzionale, per tracking)

---

## Per lo sviluppatore

### Struttura
```
FP-Experiences/
├── fp-experiences.php              # File principale
├── src/
│   ├── Core/Plugin.php             # Bootstrap e DI container
│   ├── Admin/
│   │   └── SettingsPage.php        # Impostazioni admin (tab: generale, email, tracking)
│   ├── Booking/
│   │   ├── BookingManager.php      # Gestione prenotazioni
│   │   ├── RequestToBook.php       # Flusso RTB
│   │   ├── CalendarManager.php     # Calendario e disponibilità
│   │   └── CheckinPage.php         # Pagina check-in
│   ├── Gift/
│   │   ├── GiftManager.php         # Gestione voucher
│   │   ├── Email/Templates/        # Template email voucher
│   │   └── Integration/WooCommerce/ # Integrazione WC
│   ├── Email/
│   │   ├── Mailer.php              # Servizio email centralizzato
│   │   └── Templates/              # Template HTML email
│   ├── Integrations/
│   │   ├── GA4/                    # Tracking GA4
│   │   ├── MetaPixel.php           # Meta Pixel
│   │   ├── GoogleAds.php           # Google Ads
│   │   ├── Clarity.php             # Microsoft Clarity
│   │   └── Providers/              # Service provider integrazioni
│   ├── Shortcodes/
│   │   ├── BaseShortcode.php       # Classe base shortcode
│   │   ├── ListShortcode.php
│   │   ├── SingleShortcode.php
│   │   └── WidgetShortcode.php
│   └── REST/
│       └── RouteRegistry.php       # Registrazione endpoint REST
├── templates/                      # Template HTML frontend
├── assets/
│   ├── css/
│   └── js/front/tracking.js        # Tracking frontend
└── vendor/
```

### Tracking marketing
Il tracking è delegato a **FP Marketing Tracking Layer**. Il plugin emette `CustomEvent` JavaScript che vengono intercettati da `fp-tracking.js`:

```javascript
// Esempio evento view_item
document.dispatchEvent(new CustomEvent('fpExpViewItem', {
    detail: { experience_id: 123, name: '...', price: 49.00 }
}));
```

Filtro disponibile per personalizzare i dati inviati al dataLayer:
```php
add_filter('fp_exp_datalayer_purchase', function($data, $booking) {
    $data['custom_field'] = 'valore';
    return $data;
}, 10, 2);
```

### Email system
Il servizio `Mailer` centralizzato supporta:
- Provider SMTP configurabile
- Brevo come provider alternativo
- Template HTML con branding personalizzato (logo, colori)
- Filtro `fp_exp_email_branding` per sovrascrivere il branding

### Hooks principali
| Hook | Tipo | Descrizione |
|------|------|-------------|
| `fp_exp_before_booking` | action | Prima di creare una prenotazione |
| `fp_exp_after_booking` | action | Dopo la creazione prenotazione |
| `fp_exp_booking_status_changed` | action | Cambio stato prenotazione |
| `fp_exp_datalayer_purchase` | filter | Dati acquisto per dataLayer |
| `fp_exp_email_branding` | filter | Branding email (logo, colori) |
| `fp_exp_price_from` | filter | Prezzo "a partire da" nel widget |
| `fp_exp_special_requests_checkbox_items` | filter | Elenco voci checkbox step «Richieste speciali» nel widget (`$items`, `$experience_id`) |
| `fp_exp_experience_page_section_icon_html` | filter | Markup icona intestazione sezione su `[fp_exp_page]` (`null` default, `$section` = chiave sezione). Restituire HTML non vuoto per sostituire lo SVG. |
| `fp_exp_participation_info_nudges` | filter | Messaggi sezione «Informazioni utili» (`$nudges`, `$experience_id`, `$slots_snapshot`). Ogni voce può avere `text` + `type` (layout elenco) oppure anche `kicker`, `emphasis`, `unit`, `detail`, `emphasis_approx` (layout card) |
| `fp_exp_participation_scarcity_threshold` | filter | Soglia massima posti residui per mostrare il messaggio scarsità (default `10`, `$experience_id`) |
| `fp_exp_participation_deadline_countdown_max_days` | filter | Oltre questi giorni alla chiusura prenotazioni non si mostra il conteggio giorni, solo data (default `14`; `0` = mai; negativo = sempre) |
| `fp_exp_admin_style_dependencies` | filter | Handle CSS WordPress da caricare prima di `fp-exp-admin` (default `colors`) |

### REST Endpoints
| Endpoint | Metodo | Descrizione |
|----------|--------|-------------|
| `/wp-json/fp-exp/v1/availability` | GET | Disponibilità per data |
| `/wp-json/fp-exp/v1/book` | POST | Crea prenotazione |
| `/wp-json/fp-exp/v1/rtb-quote` | GET | Preventivo RTB |
| `/wp-json/fp-exp/v1/rtb-request` | POST | Invia richiesta RTB |
| `/wp-json/fp-exp/v1/gift/redeem` | POST | Riscatta voucher |

---

## Changelog
Vedi [CHANGELOG.md](CHANGELOG.md)
---

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)

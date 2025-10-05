### Availability on-the-fly

Questa modalità calcola le fasce orarie al volo (senza slot persistiti) a partire dalle meta dell'esperienza.

#### Concetti chiave
- Configurazione in Modifica Esperienza → Disponibilità: frequenza (daily/weekly/custom), set orari multipli, lead time (h), buffer (min), capacità.
- Endpoint pubblico (verifica standard): `GET /wp-json/fp-exp/v1/availability?experience={id}&start=YYYY-MM-DD&end=YYYY-MM-DD`.
- Admin Calendario e frontend calendario/widget consumano l'endpoint per mostrare le fasce.
- Al momento della richiesta (RTB/Checkout) se manca `slot_id`, si inviano `start` e `end` (UTC SQL). Il backend crea/riusa lo slot reale con `Slots::ensure_slot_for_occurrence` e verifica capacità/lead/buffer.

#### API
- Request
  - `experience` (int, required)
  - `start` (YYYY-MM-DD, required)
  - `end` (YYYY-MM-DD, required)
- Response
  - `slots`: array di `{ start, end, capacity_total, duration, experience_id }`

Note: `start`/`end` sono in UTC (formato `Y-m-d H:i:s`).

#### Admin Calendario
- Selettore esperienza in alto.
- Navigazione mese → chiama `/availability` per il range mensile.
- Le azioni di move/capacity restano per slot già persistiti.

#### Frontend
- Calendario Only: clic sul giorno → fetch `/availability` per quella data → render liste.
- Widget: selezione slot popola `slot_id` (se presente) e sempre `start/end` nascosti.
- RTB/Checkout: inviano `experience_id`, `tickets`, `addons` e `slot_id` oppure `start/end`.

#### Lead time e buffer
- Lead time filtra gli slot nel futuro utile.
- `ensure_slot_for_occurrence` rispetta `buffer_before/after`; se esiste conflitto non crea lo slot e la richiesta fallisce con errore di disponibilità.

#### QA rapido
- Admin Calendario: cambio esperienza/mese; nessun slot; errori rete.
- Front: data → fasce → submit RTB/checkout con/ senza `slot_id`.
- Fusi orari: UI locale, dati inviati in UTC.
- Capacità: divieti overbooking, conflitti buffer.

#### Migliori pratiche
- Cache breve (es. 60–120s) lato edge/app per `/availability` in produzione.
- Rate limit pubblico di base.
- Messaggi UX chiari su vuoto/errore; skeleton loading.

#### Integrazione con codice
- Backend: `src/Booking/AvailabilityService.php`, `Slots::ensure_slot_for_occurrence`, REST in `src/Api/RestRoutes.php`.
- Admin: `assets/js/admin.js`, `src/Admin/CalendarAdmin.php`.
- Front: `assets/js/front.js`, `templates/front/calendar.php`, `templates/front/widget.php`.



# Sistema di Backup/Restore delle Impostazioni di Branding

## Problema Risolto

Il problema era che durante gli aggiornamenti o la disinstallazione del plugin, le impostazioni di branding (colori, temi, configurazioni) venivano perse, costringendo l'utente a riconfigurare tutto manualmente ogni volta.

## Soluzione Implementata

È stato implementato un sistema completo di backup e restore delle impostazioni di branding che include:

### 1. Backup Automatico
- **Quando**: Durante l'attivazione del plugin (se non esiste già un backup)
- **Cosa**: Tutte le impostazioni di branding e configurazione del plugin
- **Dove**: Salvato come opzione WordPress `fp_exp_branding_backup`

### 2. Strumenti Manuali
Due nuovi strumenti sono stati aggiunti alla pagina "Strumenti" dell'admin:

#### Backup Impostazioni Branding
- Crea un backup manuale delle impostazioni correnti
- Include metadati (timestamp, versione plugin, URL sito)
- Rate limiting per evitare abusi (max 3 backup al minuto)

#### Ripristina Impostazioni Branding
- Ripristina le impostazioni da un backup precedente
- Mostra dettagli del backup utilizzato
- Gestisce errori parziali con report dettagliato

### 3. Impostazioni Preservate

Il sistema preserva tutte queste impostazioni:

- `fp_exp_branding` - Colori, font, temi
- `fp_exp_email_branding` - Branding email
- `fp_exp_emails` - Configurazione email
- `fp_exp_tracking` - Configurazione tracking (GA4, Meta Pixel, Google Ads, Clarity)
- `fp_exp_brevo` - Integrazione Brevo
- `fp_exp_google_calendar` - Integrazione Google Calendar
- `fp_exp_experience_layout` - Layout esperienze
- `fp_exp_listing` - Impostazioni listing
- `fp_exp_gift` - Impostazioni gift/voucher
- `fp_exp_rtb` - Request to book
- `fp_exp_enable_meeting_points` - Punti di incontro
- `fp_exp_enable_meeting_point_import` - Import punti di incontro
- `fp_exp_structure_email` - Email struttura
- `fp_exp_webmaster_email` - Email webmaster
- `fp_exp_debug_logging` - Debug logging

### 4. Protezione Durante Disinstallazione

Il file `uninstall.php` è stato aggiornato per:
- **Conservare** tutte le impostazioni di branding
- **Conservare** il backup delle impostazioni
- **Eliminare** solo le opzioni temporanee e di sistema

## Come Utilizzare

### Backup Automatico
Il backup automatico si attiva quando:
1. Il plugin viene attivato
2. Non esiste già un backup
3. Ci sono impostazioni da salvare

### Backup Manuale
1. Vai su **FP Experiences > Strumenti**
2. Clicca su **"Crea backup"** nella sezione "Backup impostazioni branding"
3. Il sistema creerà un backup con timestamp

### Restore Manuale
1. Vai su **FP Experiences > Strumenti**
2. Clicca su **"Ripristina backup"** nella sezione "Ripristina impostazioni branding"
3. Il sistema ripristinerà le impostazioni dall'ultimo backup

## Struttura del Backup

```json
{
  "timestamp": "2024-01-15 10:30:00",
  "version": "0.3.7",
  "site_url": "https://example.com",
  "settings": {
    "fp_exp_branding": {...},
    "fp_exp_email_branding": {...},
    // ... altre impostazioni
  },
  "auto_created": true
}
```

## Logging

Tutte le operazioni di backup/restore vengono registrate nei log del plugin per audit e debugging.

## Rate Limiting

- Backup: max 3 operazioni al minuto per utente
- Restore: max 3 operazioni al minuto per utente
- Previene abusi e sovraccarico del sistema

## Compatibilità

Il sistema è completamente retrocompatibile e non interferisce con le funzionalità esistenti del plugin.

## Benefici

1. **Nessuna perdita di configurazione** durante aggiornamenti
2. **Backup automatico** senza intervento manuale
3. **Strumenti manuali** per controllo completo
4. **Logging completo** per audit
5. **Rate limiting** per sicurezza
6. **Gestione errori** robusta

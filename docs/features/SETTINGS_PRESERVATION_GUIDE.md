# Guida alla Preservazione delle Impostazioni

## Panoramica

Il plugin FP Experiences include un sistema completo per preservare le impostazioni durante aggiornamenti, disinstallazioni e reinstallazioni. Questo garantisce che gli utenti non perdano mai le loro configurazioni personalizzate.

## Come Funziona

### 1. Backup Automatico
- **Quando**: Durante l'attivazione del plugin (se non esiste già un backup)
- **Cosa**: Tutte le impostazioni di configurazione del plugin
- **Dove**: Salvato come opzione WordPress `fp_exp_branding_backup`

### 2. Ripristino Automatico
- **Quando**: Durante l'attivazione del plugin
- **Condizioni**: 
  - Esiste un backup delle impostazioni
  - Le impostazioni correnti sono vuote o di default
- **Risultato**: Le impostazioni vengono ripristinate automaticamente

### 3. Preservazione durante Disinstallazione
- **Cosa viene conservato**: Tutte le impostazioni di configurazione
- **Cosa viene eliminato**: Solo le opzioni temporanee e di sistema
- **Risultato**: Le impostazioni rimangono disponibili per future reinstallazioni

## Impostazioni Preservate

Il sistema preserva tutte queste impostazioni:

### Branding e Design
- `fp_exp_branding` - Colori, font, temi
- `fp_exp_email_branding` - Branding email
- `fp_exp_experience_layout` - Layout esperienze

### Configurazione Email
- `fp_exp_emails` - Configurazione email
- `fp_exp_structure_email` - Email struttura
- `fp_exp_webmaster_email` - Email webmaster

### Integrazioni
- `fp_exp_tracking` - Configurazione tracking (GA4, Meta Pixel, Google Ads, Clarity)
- `fp_exp_brevo` - Integrazione Brevo
- `fp_exp_google_calendar` - Integrazione Google Calendar

### Funzionalità
- `fp_exp_listing` - Impostazioni listing
- `fp_exp_gift` - Impostazioni gift/voucher
- `fp_exp_rtb` - Request to book
- `fp_exp_enable_meeting_points` - Punti di incontro
- `fp_exp_enable_meeting_point_import` - Import punti di incontro
- `fp_exp_debug_logging` - Debug logging

## Scenari di Utilizzo

### Scenario 1: Aggiornamento del Plugin
1. L'utente aggiorna il plugin
2. Il sistema rileva un backup esistente
3. Le impostazioni vengono ripristinate automaticamente
4. L'utente non perde nessuna configurazione

### Scenario 2: Disinstallazione e Reinstallazione
1. L'utente disinstalla il plugin
2. Le impostazioni vengono preservate (non eliminate)
3. L'utente reinstalla il plugin
4. Le impostazioni vengono ripristinate automaticamente
5. L'utente ritrova tutte le sue configurazioni

### Scenario 3: Migrazione tra Siti
1. L'utente esporta il backup delle impostazioni
2. L'utente importa il backup nel nuovo sito
3. Le impostazioni vengono applicate automaticamente

## Strumenti Manuali

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
    "fp_exp_emails": {...},
    // ... altre impostazioni
  },
  "auto_created": true
}
```

## Logging e Debug

### Log Automatici
- Tutti i ripristini automatici vengono registrati nei log di WordPress
- I log includono il numero di impostazioni ripristinate e il timestamp del backup

### Esempio di Log
```
[FP Experiences] Ripristino automatico completato: 15 impostazioni ripristinate dal backup del 2024-01-15 10:30:00
```

## Sicurezza

### Rate Limiting
- Backup: max 3 operazioni al minuto per utente
- Restore: max 3 operazioni al minuto per utente
- Previene abusi e sovraccarico del sistema

### Validazione
- Tutti i backup vengono validati prima del ripristino
- Solo le impostazioni valide vengono ripristinate
- Gestione errori robusta con report dettagliato

## Compatibilità

- **Retrocompatibilità**: Il sistema funziona con tutte le versioni del plugin
- **WordPress**: Compatibile con WordPress 5.0+
- **PHP**: Richiede PHP 7.4+

## Risoluzione Problemi

### Le impostazioni non vengono ripristinate
1. Verifica che esista un backup: `get_option('fp_exp_branding_backup')`
2. Controlla i log di WordPress per errori
3. Prova il restore manuale dalla pagina Strumenti

### Il backup non viene creato
1. Verifica che ci siano impostazioni da salvare
2. Controlla i permessi di scrittura del database
3. Verifica che non ci siano errori PHP

### Impostazioni parzialmente ripristinate
1. Controlla i log per errori specifici
2. Verifica che le impostazioni di backup siano valide
3. Prova il restore manuale per le impostazioni mancanti

## Benefici

1. **Zero perdita di configurazione** durante aggiornamenti
2. **Ripristino automatico** senza intervento manuale
3. **Backup intelligente** che preserva solo le impostazioni necessarie
4. **Strumenti manuali** per controllo completo
5. **Logging completo** per audit e debugging
6. **Sicurezza** con rate limiting e validazione
7. **Compatibilità** con tutti gli scenari di utilizzo

## Conclusione

Il sistema di preservazione delle impostazioni garantisce che gli utenti non perdano mai le loro configurazioni personalizzate, indipendentemente da aggiornamenti, disinstallazioni o reinstallazioni del plugin. Questo migliora significativamente l'esperienza utente e riduce il supporto tecnico necessario.

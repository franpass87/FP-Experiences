# Guida Aggiornamento a v0.3.7

**Da:** v0.3.4-0.3.6  
**A:** v0.3.7  
**Data:** 13 Ottobre 2025

---

## 🎯 Panoramica Aggiornamento

Questa è una release di **bug fix e sicurezza** che risolve:
- 🔴 1 bug critico (race condition)
- 🟡 1 bug medio (memory leak)
- 🟢 1 bug minore (console logging)

**Importanza:** 🔴 **ALTA** - Aggiornamento fortemente raccomandato

---

## ✅ Compatibilità

### Backward Compatibility

✅ **100% Compatibile**

- ✅ Nessun breaking change
- ✅ Nessuna modifica database schema
- ✅ Nessuna modifica API pubblica
- ✅ Tutti gli hook esistenti funzionano
- ✅ Shortcode invariati
- ✅ Template compatibili

### Requisiti Sistema

| Componente | Prima (0.3.6) | Dopo (0.3.7) | Note |
|------------|---------------|--------------|------|
| PHP | 8.1+ | 8.0+ | ⬇️ Requisito ridotto |
| WordPress | 6.0+ | 6.2+ | ⬆️ Raccomandato più recente |
| MySQL | 5.7+ | 5.7+ | Invariato |
| WooCommerce | 7.0+ | 7.0+ | Invariato |

---

## 🚀 Procedura di Aggiornamento

### Opzione 1: Aggiornamento Automatico (Consigliato)

```bash
# 1. Backup (SEMPRE!)
wp db export backup-pre-0.3.7-$(date +%Y%m%d).sql
tar -czf backup-files-pre-0.3.7-$(date +%Y%m%d).tar.gz wp-content/plugins/fp-experiences

# 2. Aggiorna plugin
wp plugin update fp-experiences

# 3. Verifica versione
wp plugin list --name=fp-experiences
# Output atteso: fp-experiences  active  0.3.7

# 4. Svuota cache
wp cache flush

# 5. Testa funzionalità chiave (vedi sotto)
```

### Opzione 2: Aggiornamento Manuale

```bash
# 1. Backup
# (stesso del metodo 1)

# 2. Disattiva plugin
wp plugin deactivate fp-experiences

# 3. Rimuovi vecchia versione
rm -rf wp-content/plugins/fp-experiences

# 4. Carica nuova versione
# Upload tramite FTP o WP Admin

# 5. Riattiva
wp plugin activate fp-experiences

# 6. Verifica e testa
```

---

## ✅ Checklist Post-Aggiornamento

### Immediato (< 5 minuti)

- [ ] Verifica versione corretta (0.3.7)
- [ ] Controlla che il plugin sia attivo
- [ ] Verifica nessun errore PHP
- [ ] Svuota cache (se presente)

### Test Funzionalità Chiave (< 15 minuti)

- [ ] **Checkout Diretto**
  - [ ] Apri una pagina esperienza
  - [ ] Seleziona data e slot
  - [ ] Aggiungi biglietti
  - [ ] Procedi al checkout
  - [ ] Verifica che funzioni correttamente

- [ ] **Request-to-Book** (se abilitato)
  - [ ] Invia una richiesta
  - [ ] Verifica email ricevuta
  - [ ] Approva/Rifiuta da admin
  - [ ] Verifica stato aggiornato

- [ ] **Admin Calendar**
  - [ ] Apri FP Experiences → Calendario
  - [ ] Verifica visualizzazione slot
  - [ ] Testa filtri esperienza
  - [ ] Verifica nessun errore console

- [ ] **Gift Vouchers** (se abilitato)
  - [ ] Acquista gift voucher
  - [ ] Verifica email ricevuta
  - [ ] Testa redemption flow
  - [ ] Verifica prenotazione creata

### Monitoraggio (Prima Settimana)

- [ ] **Error Logs**
  - Vai a: FP Experiences → Logs
  - Cerca errori con codice `capacity_exceeded`
  - Frequenza normale: < 0.1% delle prenotazioni

- [ ] **Performance**
  - Tempo risposta checkout: Dovrebbe essere < 2 secondi
  - Nessun timeout segnalato
  - Nessun rallentamento evidente

- [ ] **Overbooking**
  - Verifica che nessuno slot superi capacità massima
  - Query SQL: 
    ```sql
    SELECT s.id, s.capacity_total, 
           COUNT(r.id) as prenotati
    FROM wp_fp_exp_slots s
    LEFT JOIN wp_fp_exp_reservations r ON s.id = r.slot_id 
      AND r.status NOT IN ('declined', 'cancelled')
    GROUP BY s.id
    HAVING prenotati > s.capacity_total;
    ```
  - Risultato atteso: 0 righe

---

## 🔧 Risoluzione Problemi

### Problema: Errori dopo Aggiornamento

**Sintomo:** Errori PHP o white screen

**Soluzione:**
```bash
# 1. Disabilita cache
wp cache flush
wp transient delete --all

# 2. Rigenera autoloader
composer dump-autoload -o

# 3. Verifica permessi file
chmod -R 755 wp-content/plugins/fp-experiences
chown -R www-data:www-data wp-content/plugins/fp-experiences

# 4. Se ancora problemi: rollback
wp plugin install fp-experiences-0.3.6.zip --force
```

### Problema: Checkout Non Funziona

**Sintomo:** Errore durante checkout

**Debug:**
```bash
# 1. Verifica logs
wp eval "var_dump(get_option('fp_exp_settings'));"

# 2. Verifica nonce
# Apri browser console, cerca errori "nonce"

# 3. Test REST API
wp eval "echo wp_remote_get(rest_url('fp-exp/v1/cart/status'))['body'];"
```

**Soluzioni comuni:**
- Svuota cache browser
- Disabilita plugin di cache
- Verifica sessioni PHP attive

### Problema: "Slot Esaurito" Frequente

**Sintomo:** Molti utenti vedono errore `capacity_exceeded`

**Analisi:**
```bash
# Conta errori capacity_exceeded
wp db query "SELECT COUNT(*) FROM wp_fp_exp_logs 
             WHERE message LIKE '%capacity_exceeded%' 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);"
```

**Se > 5% delle prenotazioni:**
- ✅ Fix sta funzionando (previene overbooking!)
- ⚠️ Potrebbe indicare alta concorrenza
- 💡 Considera: aumentare capacità slot o implementare row locking

---

## 📊 Cosa Cambia per te

### Se sei Amministratore

✅ **Niente cambia nella tua routine quotidiana**

Tutto funziona come prima, solo più sicuro:
- Dashboard: Identica
- Calendario: Identico
- Ordini: Identici
- Impostazioni: Identiche

**Nuovo:** Potresti vedere occasionalmente errore "Slot esaurito" - questo è il fix che **previene overbooking**!

### Se sei Sviluppatore

✅ **Nessuna modifica necessaria al tuo codice**

API pubblica invariata:
- Hook: Identici (chiamati come prima)
- Shortcode: Stessi attributi
- REST API: Stessi endpoint
- Template: Compatibili

**Nuovo:**
- Metodo `Reservations::delete(int $id): bool` disponibile
- Codici errore: `fp_exp_capacity_exceeded`, `fp_exp_rtb_capacity_exceeded`

### Se sei Utente Finale

✅ **Esperienza migliorata**

- Più veloce (memory leak risolto)
- Più sicuro (no overbooking)
- Nessun cambiamento visibile

---

## 🎓 Best Practices Post-Aggiornamento

### Immediate

1. **Backup Regolari**
   ```bash
   # Setup backup giornaliero
   crontab -e
   0 2 * * * wp db export /backup/db-$(date +\%Y\%m\%d).sql
   ```

2. **Monitoraggio Attivo**
   - Configura alerts per errori critici
   - Monitora performance metriche
   - Track error rate

3. **Testing Periodico**
   - Test checkout settimanale
   - Verifica email transazionali
   - Check integrazioni (Brevo, Google Calendar)

### Medio Termine

1. **Load Testing**
   - Simula alta concorrenza
   - Verifica efficacia fix race condition
   - Identifica eventuali bottleneck

2. **Monitoring Dashboard**
   - Implementa New Relic / Datadog
   - Track tempo risposta
   - Monitor error rate

3. **Feedback Loop**
   - Raccogli feedback utenti
   - Monitora ticket supporto
   - Identifica pattern problematici

---

## 📈 Metriche di Successo

### KPI da Monitorare

**Settimana 1:**
- Error rate < 0.5%
- Tempo risposta < 2s
- 0 overbooking
- 0 crash / 500 errors

**Mese 1:**
- Errori `capacity_exceeded` < 0.1%
- Nessun rollback necessario
- Performance stabile
- Feedback utenti positivo

**Trimestre 1:**
- Sistema stabile senza interventi
- Pronto per implementazione row locking (se necessario)
- Metriche confrontate con baseline pre-0.3.7

---

## 🔗 Risorse Utili

### Documentazione

- **[Changelog Completo](../CHANGELOG.md)**
- **[Release Notes](../../RELEASE_NOTES_v0.3.7.md)**
- **[Regression Analysis](bug-reports/REGRESSION_ANALYSIS.md)**
- **[Summary Bug Fixes](bug-reports/SUMMARY_ALL_BUG_FIXES_2025-10-13.md)**

### Supporto

- **[Documentazione Plugin](../README.md)**
- **[FAQ](../README.md#faq)**
- **[GitHub Issues](https://github.com/your-repo/issues)**

---

## ✅ Completamento

Dopo aver completato questa guida:

- [ ] Backup verificato
- [ ] Aggiornamento eseguito
- [ ] Versione 0.3.7 confermata
- [ ] Test chiave completati
- [ ] Monitoraggio attivo
- [ ] Team notificato

**Congratulazioni! Sei ora su FP Experiences v0.3.7** 🎉

---

**Versione Guida:** 1.0  
**Ultima Revisione:** 13 Ottobre 2025  
**Compatibile con:** v0.3.7  
**Prossimo Update:** v0.4.0 (TBD)

# 🚀 Setup Rapido Deployment Automatico

Questo file contiene le istruzioni essenziali per configurare il deployment automatico del plugin FP Experiences.

📖 **Documentazione completa**: `.github/DEPLOYMENT.md`

---

## ✅ Setup Veloce (5 minuti)

### Opzione A: GitHub Updater (CONSIGLIATO) - Nessuna configurazione GitHub richiesta

1. **Installa GitHub Updater sul tuo WordPress**:
   ```
   https://github.com/afragen/github-updater
   ```

2. **Configura il plugin**:
   - Dashboard WordPress > Settings > GitHub Updater
   - Aggiungi il tuo repository GitHub
   - Salva

3. **Fatto!** 🎉
   - Ogni merge su `main` crea una release GitHub
   - WordPress si aggiorna automaticamente

### Opzione B: Deploy Diretto SSH

1. **Configura Secrets su GitHub**:
   - Vai su: `Settings > Secrets and variables > Actions > New repository secret`
   - Aggiungi:
     - `WP_SSH_HOST`: il tuo server (es. `tuosito.com`)
     - `WP_SSH_USER`: username SSH (es. `ubuntu`)
     - `WP_SSH_PASSWORD`: password SSH
     - `WP_INSTALL_PATH`: path WordPress (es. `/var/www/html`)

2. **Abilita il deploy**:
   - Vai su: `Settings > Variables > Actions > New repository variable`
   - Nome: `ENABLE_WP_DEPLOY`
   - Valore: `true`

3. **Fatto!** 🎉
   - Ogni merge su `main` aggiorna automaticamente WordPress

---

## 🔄 Come Funziona

```
Merge su main → GitHub Actions build → Release creata → WordPress aggiornato
```

---

## 🧪 Test del Sistema

1. Fai una modifica al plugin
2. Aggiorna la versione in `fp-experiences.php`
3. Commit e push su `main`
4. Vai su GitHub > Actions per vedere il progresso
5. Vai su GitHub > Releases per vedere la release creata
6. Controlla WordPress per l'aggiornamento

---

## 📊 Workflow Attivi

| Workflow | Quando si attiva | Cosa fa |
|----------|------------------|---------|
| `deploy-on-merge.yml` | Push/merge su main | Build + Release + Deploy (opzionale) |
| `build-zip.yml` | Push su qualsiasi branch o tag | Solo build e artifact |
| `build-plugin-zip.yml` | Push su main o tag v* | Build e artifact ZIP |

---

## ⚡ Comandi Rapidi

### Eseguire manualmente il deployment
1. Vai su GitHub > Actions
2. Seleziona "Deploy Plugin on Merge"
3. Clicca "Run workflow"

### Verificare lo stato
```bash
# Controlla GitHub Actions
# Vai su: https://github.com/[tuo-username]/[repo]/actions

# Controlla Releases
# Vai su: https://github.com/[tuo-username]/[repo]/releases
```

---

## 🛠️ Problemi Comuni

### Il workflow non parte
- ✅ Verifica che il push sia su `main` o `master`
- ✅ Controlla GitHub > Actions per eventuali errori

### WordPress non si aggiorna
- ✅ **Con GitHub Updater**: Verifica che il plugin sia installato e configurato
- ✅ **Con SSH**: Verifica che `ENABLE_WP_DEPLOY=true` e i secrets siano corretti

### Build fallisce
- ✅ Controlla che la versione in `fp-experiences.php` sia valida
- ✅ Verifica i log in GitHub Actions per dettagli

---

## 📖 Documentazione Completa

Per istruzioni dettagliate, troubleshooting avanzato e configurazioni personalizzate:

👉 **Leggi**: `.github/DEPLOYMENT.md`

---

## 🎯 Prossimi Passi

1. ✅ Scegli l'opzione (A o B) che preferisci
2. ✅ Configura seguendo le istruzioni sopra
3. ✅ Testa con un merge su main
4. ✅ Verifica che tutto funzioni

**Tempo stimato setup**: 5-10 minuti  
**Difficoltà**: ⭐⭐☆☆☆ (Facile)

---

Creato: 2025-10-10 | Plugin: FP Experiences v0.3.6

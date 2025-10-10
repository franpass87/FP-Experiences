# 🚀 Guida al Deployment Automatico

Questa guida spiega come configurare il deployment automatico del plugin FP Experiences su GitHub e WordPress.

## 📋 Panoramica

Il workflow `deploy-on-merge.yml` si attiva automaticamente quando:
- Fai un **merge** (o push) sul branch `main` o `master`
- Esegui manualmente il workflow dal tab "Actions" su GitHub

Il workflow esegue questi passaggi:
1. ✅ Checkout del codice
2. 🔧 Setup PHP e installazione dipendenze Composer
3. 📦 Build del plugin ZIP
4. 📤 Upload dell'artifact su GitHub
5. 🏷️ Creazione di una release su GitHub
6. 🌐 (Opzionale) Deploy diretto su WordPress via SSH/WP-CLI

---

## 🎯 Metodo 1: Auto-aggiornamento WordPress con GitHub Updater (CONSIGLIATO)

Questo è il metodo più semplice e sicuro. Usa il plugin **GitHub Updater** per WordPress.

### Installazione GitHub Updater

1. **Installa il plugin GitHub Updater** sul tuo sito WordPress:
   - Vai su: https://github.com/afragen/github-updater
   - Scarica l'ultima release
   - Carica e attiva il plugin su WordPress

2. **Configura GitHub Updater** per il plugin FP Experiences:
   - Vai su `Dashboard > Settings > GitHub Updater`
   - Aggiungi il repository: `[tuo-username]/[tuo-repo]`
   - Salva le impostazioni

3. **Abilita auto-aggiornamenti**:
   - Vai su `Plugins` nel tuo WordPress
   - Trova "FP Experiences"
   - Attiva gli aggiornamenti automatici

### Come funziona

- Ogni volta che fai un merge su `main`, viene creata una nuova release su GitHub
- GitHub Updater controlla periodicamente le nuove release
- Quando trova una nuova versione, aggiorna automaticamente il plugin
- Puoi anche aggiornare manualmente da `Dashboard > Updates`

---

## 🎯 Metodo 2: Deploy Diretto via SSH/WP-CLI (AVANZATO)

Questo metodo invia direttamente il plugin aggiornato al tuo server WordPress via SSH.

### Requisiti

- Accesso SSH al server WordPress
- WP-CLI installato sul server
- Credenziali SSH

### Configurazione GitHub Secrets

1. Vai su **GitHub Repository > Settings > Secrets and variables > Actions**

2. Clicca su **"New repository secret"** e aggiungi:

   | Nome Secret | Descrizione | Esempio |
   |-------------|-------------|---------|
   | `WP_SSH_HOST` | Indirizzo del server SSH | `tuosito.com` o `192.168.1.100` |
   | `WP_SSH_USER` | Username SSH | `ubuntu` o `root` |
   | `WP_SSH_PASSWORD` | Password SSH | `la-tua-password-sicura` |
   | `WP_INSTALL_PATH` | Percorso installazione WordPress | `/var/www/html` |

3. Vai su **Settings > Variables > Actions** e clicca su **"New repository variable"**:

   | Nome Variabile | Valore | Descrizione |
   |----------------|--------|-------------|
   | `ENABLE_WP_DEPLOY` | `true` | Abilita il deploy automatico via SSH |

### Note di Sicurezza

⚠️ **IMPORTANTE**: 
- Usa sempre password SSH complesse
- Considera di usare chiavi SSH invece delle password
- Non condividere mai i secrets pubblicamente

---

## 🎯 Metodo 3: Deploy con Chiavi SSH (MASSIMA SICUREZZA)

Più sicuro del Metodo 2, usa chiavi SSH invece delle password.

### Setup Chiavi SSH

1. **Genera una coppia di chiavi SSH** sul tuo computer locale:
   ```bash
   ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_deploy
   ```

2. **Aggiungi la chiave pubblica** al server WordPress:
   ```bash
   ssh-copy-id -i ~/.ssh/github_deploy.pub [user]@[host]
   ```

3. **Aggiungi la chiave privata** ai secrets di GitHub:
   - Copia il contenuto di `~/.ssh/github_deploy`
   - Crea un secret chiamato `WP_SSH_PRIVATE_KEY` con questo contenuto

4. **Modifica il workflow** `.github/workflows/deploy-on-merge.yml`:
   
   Sostituisci la sezione "Deploy to WordPress via WP-CLI" con:
   ```yaml
   - name: Setup SSH key
     run: |
       mkdir -p ~/.ssh
       echo "${{ secrets.WP_SSH_PRIVATE_KEY }}" > ~/.ssh/github_deploy
       chmod 600 ~/.ssh/github_deploy
       ssh-keyscan -H ${{ secrets.WP_SSH_HOST }} >> ~/.ssh/known_hosts

   - name: Deploy to WordPress via WP-CLI
     if: ${{ vars.ENABLE_WP_DEPLOY == 'true' }}
     run: |
       scp -i ~/.ssh/github_deploy \
         fp-experiences-${{ steps.version.outputs.version }}.zip \
         ${{ secrets.WP_SSH_USER }}@${{ secrets.WP_SSH_HOST }}:/tmp/
       
       ssh -i ~/.ssh/github_deploy \
         ${{ secrets.WP_SSH_USER }}@${{ secrets.WP_SSH_HOST }} \
         "cd ${{ secrets.WP_INSTALL_PATH }} && \
          wp plugin update fp-experiences --version=${{ steps.version.outputs.version }} --activate"
   ```

---

## 🔄 Workflow Completo

### Quando fai un merge su `main`:

```
┌─────────────────────────────────────┐
│  Merge/Push su main                 │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  GitHub Actions: build-and-deploy   │
├─────────────────────────────────────┤
│  1. Checkout codice                 │
│  2. Setup PHP + Composer            │
│  3. Build plugin ZIP                │
│  4. Crea release su GitHub          │
│  5. (Opzionale) Deploy su WordPress │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Release pubblicata su GitHub       │
│  - Tag: v0.3.6                      │
│  - Asset: fp-experiences-0.3.6.zip  │
└──────────────┬──────────────────────┘
               │
               ▼ (se GitHub Updater attivo)
┌─────────────────────────────────────┐
│  WordPress controlla aggiornamenti  │
│  e installa la nuova versione       │
└─────────────────────────────────────┘
```

---

## 📊 Monitoraggio

### Verificare lo stato del deployment

1. Vai su **GitHub > Actions** nel tuo repository
2. Vedi l'ultima esecuzione di "Deploy Plugin on Merge"
3. Clicca per vedere i dettagli e i log

### Verificare la release

1. Vai su **GitHub > Releases**
2. Dovresti vedere la nuova versione pubblicata
3. Il file ZIP è allegato alla release

### Verificare l'aggiornamento su WordPress

1. **Con GitHub Updater**:
   - Vai su `Dashboard > Updates`
   - Verifica che sia presente l'aggiornamento
   - Aggiorna se non è automatico

2. **Con Deploy Diretto**:
   - Vai su `Plugins`
   - Verifica che la versione corrisponda

---

## 🛠️ Troubleshooting

### Il workflow non parte

- ✅ Verifica che il push sia su `main` o `master`
- ✅ Controlla che il workflow file sia corretto
- ✅ Vai su Actions e controlla eventuali errori

### La build fallisce

- ✅ Verifica che tutte le dipendenze Composer siano corrette
- ✅ Controlla che il file `fp-experiences.php` contenga la versione corretta
- ✅ Vedi i log dettagliati nella sezione Actions

### Il deploy SSH fallisce

- ✅ Verifica che tutti i secrets siano configurati correttamente
- ✅ Testa la connessione SSH manualmente dal tuo computer
- ✅ Verifica che WP-CLI sia installato sul server
- ✅ Controlla i permessi del path di installazione WordPress

### GitHub Updater non trova aggiornamenti

- ✅ Verifica che il repository sia pubblico (o configura un token per repo privati)
- ✅ Controlla che la release sia pubblicata (non draft)
- ✅ Vai su Settings > GitHub Updater e forza un check

---

## 📝 Best Practices

1. **Versioning**:
   - Aggiorna sempre la versione in `fp-experiences.php` prima del merge
   - Usa semantic versioning (major.minor.patch)

2. **Testing**:
   - Testa sempre il plugin localmente prima del merge
   - Considera di usare branch di staging prima di mergeare su main

3. **Sicurezza**:
   - Non committare mai secrets nel codice
   - Usa GitHub Secrets per tutte le credenziali sensibili
   - Considera di usare chiavi SSH invece delle password

4. **Monitoraggio**:
   - Controlla sempre i log di GitHub Actions dopo ogni merge
   - Verifica che la release sia stata creata correttamente
   - Testa l'aggiornamento su un ambiente di staging prima della produzione

---

## 🎓 Prossimi Passi

1. **Scegli il metodo di deployment** che preferisci (Metodo 1 consigliato)
2. **Configura i secrets/variabili** necessari su GitHub
3. **Testa il workflow** facendo un merge su main
4. **Verifica** che tutto funzioni correttamente
5. **Documenta** eventuali personalizzazioni specifiche per il tuo setup

---

## 📞 Supporto

Per problemi o domande:
- Controlla i log in GitHub Actions
- Verifica la documentazione ufficiale di GitHub Actions
- Consulta la documentazione di GitHub Updater
- Verifica la documentazione WP-CLI

---

**Creato:** 2025-10-10  
**Versione:** 1.0  
**Plugin:** FP Experiences

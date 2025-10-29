# 📦 Sistema di Deployment Automatico - Riepilogo

Sistema di deployment automatico configurato per il plugin **FP Experiences**.

---

## 🎯 Cosa è Stato Configurato

### ✅ File Creati

| File | Descrizione |
|------|-------------|
| `.github/workflows/deploy-on-merge.yml` | Workflow principale per deployment automatico |
| `.github/scripts/update-version.sh` | Script helper per aggiornare versione plugin |
| `.github/DEPLOYMENT.md` | Documentazione completa con tutte le opzioni |
| `DEPLOYMENT-SETUP.md` | Guida setup rapido (5 minuti) |
| `.github/QUICK-TEST.md` | Procedura di test del sistema |
| `GITHUB-DEPLOYMENT-SUMMARY.md` | Questo file - riepilogo generale |

### ✅ Workflow Esistenti

| Workflow | Quando si attiva | Cosa fa |
|----------|------------------|---------|
| **deploy-on-merge.yml** (NUOVO) | Push/merge su `main` | Build + Release + Deploy opzionale |
| build-zip.yml | Push su branch/tag | Solo build |
| build-plugin-zip.yml | Push su main/tag v* | Build ZIP |

---

## 🚀 Come Funziona il Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│  SVILUPPATORE                                                   │
│  1. Modifica il codice del plugin                              │
│  2. Aggiorna versione: .github/scripts/update-version.sh 0.3.7 │
│  3. Commit & Push su main                                      │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│  GITHUB ACTIONS (Automatico)                                    │
│  ✓ Checkout codice                                             │
│  ✓ Setup PHP 8.2 + Composer                                    │
│  ✓ Installa dipendenze (no-dev)                                │
│  ✓ Estrae versione da fp-experiences.php                       │
│  ✓ Esegue build script (.github/scripts/build-zip.sh)          │
│  ✓ Crea fp-experiences-[version].zip                           │
│  ✓ Upload artifact su GitHub                                   │
│  ✓ Crea release con tag v[version]                             │
│  ✓ (Opzionale) Deploy via SSH su WordPress                     │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│  GITHUB RELEASE                                                 │
│  • Tag: v0.3.7                                                  │
│  • Title: FP Experiences v0.3.7                                 │
│  • Asset: fp-experiences-0.3.7.zip                              │
│  • Public download link                                         │
└────────────────────┬────────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────────┐
│  WORDPRESS (2 opzioni)                                          │
│                                                                 │
│  A) GITHUB UPDATER PLUGIN (consigliato)                         │
│     • Controlla automaticamente nuove release                   │
│     • Scarica e installa l'aggiornamento                        │
│     • Attiva il plugin aggiornato                               │
│                                                                 │
│  B) DEPLOY DIRETTO SSH (opzionale)                              │
│     • GitHub Actions si connette via SSH                        │
│     • Carica il file ZIP sul server                             │
│     • Esegue wp-cli per aggiornare il plugin                    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📋 Setup Necessario

### Minimo (Solo Release GitHub)

✅ **Nessuna configurazione necessaria!**

Il sistema funziona subito per creare release su GitHub.

Ogni merge su `main` → Release automatica su GitHub

### Per Auto-aggiornamento WordPress

Scegli UNA delle seguenti opzioni:

#### Opzione A: GitHub Updater (CONSIGLIATO - No secrets GitHub)

1. Installa plugin GitHub Updater su WordPress
2. Configura con il tuo repository
3. Abilita auto-aggiornamenti

📖 **Dettagli**: `DEPLOYMENT-SETUP.md` → Opzione A

#### Opzione B: Deploy Diretto SSH (Avanzato)

1. Configura 4 secrets su GitHub:
   - `WP_SSH_HOST`
   - `WP_SSH_USER`
   - `WP_SSH_PASSWORD`
   - `WP_INSTALL_PATH`

2. Abilita variabile:
   - `ENABLE_WP_DEPLOY=true`

📖 **Dettagli**: `DEPLOYMENT-SETUP.md` → Opzione B

---

## 🧪 Test Rapido

```bash
# 1. Aggiorna versione
.github/scripts/update-version.sh 0.3.7

# 2. Commit e push
git add fp-experiences.php
git commit -m "Bump version to 0.3.7"
git push origin main

# 3. Monitora
# Vai su GitHub → Actions
# Vai su GitHub → Releases (dopo 2-3 minuti)
```

📖 **Test completo**: `.github/QUICK-TEST.md`

---

## 📚 Documentazione

| Documento | Per Chi | Tempo Lettura |
|-----------|---------|---------------|
| `DEPLOYMENT-SETUP.md` | Tutti - setup iniziale | 5 min |
| `.github/DEPLOYMENT.md` | Documentazione completa | 15 min |
| `.github/QUICK-TEST.md` | Test del sistema | 10 min |
| Questo file | Panoramica generale | 3 min |

---

## 🎯 Prossimi Passi

### 1️⃣ Primi 5 Minuti

- [ ] Leggi `DEPLOYMENT-SETUP.md`
- [ ] Scegli opzione A (GitHub Updater) o B (SSH)
- [ ] Configura secondo l'opzione scelta

### 2️⃣ Test (5 minuti)

- [ ] Segui `.github/QUICK-TEST.md`
- [ ] Verifica che la release venga creata
- [ ] (Opzionale) Verifica aggiornamento WordPress

### 3️⃣ Utilizzo Quotidiano

Da ora in poi:

1. Sviluppa normalmente
2. Prima del merge: `.github/scripts/update-version.sh [nuova-versione]`
3. Commit & push su `main`
4. ✅ Tutto il resto è automatico!

---

## 🔧 Utility Scripts

### Aggiorna Versione

```bash
.github/scripts/update-version.sh 0.4.0
```

Aggiorna automaticamente:
- Header `Version:` in `fp-experiences.php`
- Costante `FP_EXP_VERSION` in `fp-experiences.php`

### Build Manuale Locale

```bash
./build.sh --bump=patch      # 0.3.6 → 0.3.7
./build.sh --bump=minor      # 0.3.6 → 0.4.0
./build.sh --bump=major      # 0.3.6 → 1.0.0
./build.sh --set-version=1.0.0
```

### Workflow Manuale

GitHub → Actions → "Deploy Plugin on Merge" → Run workflow

---

## 📊 Monitoring

### Dove Controllare

| Cosa | Dove |
|------|------|
| Esecuzione workflow | GitHub → Actions |
| Release create | GitHub → Releases |
| Log dettagliati | GitHub → Actions → [workflow run] |
| Aggiornamenti WP | WordPress → Updates |
| Versione corrente | WordPress → Plugins |

### Notifiche

Il workflow mostra:
- ✅ Successo: emoji verde e messaggio di conferma
- ❌ Errore: emoji rossa e dettagli errore

---

## 🎓 Best Practices

### Versioning

- ✅ Usa semantic versioning (X.Y.Z)
- ✅ Patch (0.3.6 → 0.3.7): bug fix
- ✅ Minor (0.3.6 → 0.4.0): nuove feature
- ✅ Major (0.3.6 → 1.0.0): breaking changes

### Workflow

1. Sviluppa su branch feature
2. Testa localmente
3. Merge su `main` solo quando pronto
4. Lascia che GitHub Actions faccia il resto

### Sicurezza

- ✅ Mai committare secrets nel codice
- ✅ Usa GitHub Secrets per credenziali
- ✅ Preferisci SSH keys alle password
- ✅ Testa su staging prima di produzione

---

## 🛠️ Troubleshooting Rapido

| Problema | Soluzione Rapida |
|----------|------------------|
| Workflow non parte | Verifica push su `main`, controlla Actions |
| Build fallisce | Controlla versione in `fp-experiences.php` |
| Release non creata | Verifica log GitHub Actions |
| WordPress non aggiorna | Verifica GitHub Updater / secrets SSH |

📖 **Troubleshooting completo**: `.github/DEPLOYMENT.md` → Sezione Troubleshooting

---

## 📞 Supporto

### Per Problemi Tecnici

1. Controlla i log: GitHub → Actions → [workflow run]
2. Leggi la documentazione: `.github/DEPLOYMENT.md`
3. Testa manualmente: `.github/QUICK-TEST.md`

### Risorse Utili

- [GitHub Actions Docs](https://docs.github.com/actions)
- [GitHub Updater Plugin](https://github.com/afragen/github-updater)
- [WP-CLI Documentation](https://wp-cli.org/)

---

## ✅ Checklist Completamento Setup

- [ ] Letto `DEPLOYMENT-SETUP.md`
- [ ] Scelto metodo deployment (A o B)
- [ ] Configurato secrets/variabili (se opzione B)
- [ ] Eseguito test da `.github/QUICK-TEST.md`
- [ ] Verificato creazione release su GitHub
- [ ] Verificato aggiornamento WordPress (opzionale)
- [ ] Sistema funzionante ✅

---

## 🎉 Risultato Finale

Una volta completato il setup:

```
PRIMA:
  Sviluppo → Build manuale → Upload FTP → Attiva plugin
  ⏱️ Tempo: ~15-30 minuti
  🐛 Errori: Comuni (file mancanti, permessi, ecc.)

DOPO:
  Sviluppo → Push su main → ✅ Fatto!
  ⏱️ Tempo: ~2 minuti (automatico)
  🐛 Errori: Rari (workflow testato)
```

---

**Creato:** 2025-10-10  
**Versione:** 1.0  
**Plugin:** FP Experiences v0.3.6  
**Repository:** Configurato ✅

🚀 **Pronto per il deployment automatico!**

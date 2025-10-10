# 🧪 Test Rapido del Sistema di Deployment

Segui questi passi per testare il sistema di deployment automatico.

## 🚀 Test Completo (5 minuti)

### 1. Aggiorna la Versione del Plugin

Usa lo script helper per aggiornare la versione:

```bash
# Dalla root del progetto
.github/scripts/update-version.sh 0.3.7
```

Oppure manualmente, modifica `fp-experiences.php`:

```php
/**
 * Version: 0.3.7    # <-- Cambia qui
 */

// E anche qui:
define('FP_EXP_VERSION', '0.3.7');
```

### 2. Commit e Push

```bash
git add fp-experiences.php
git commit -m "Bump version to 0.3.7"
git push origin main
```

### 3. Monitora il Workflow

1. Vai su GitHub → Actions
2. Dovresti vedere "Deploy Plugin on Merge" in esecuzione
3. Clicca per vedere i dettagli in tempo reale

### 4. Verifica la Release

1. Vai su GitHub → Releases
2. Dovresti vedere "FP Experiences v0.3.7"
3. Verifica che il file ZIP sia allegato

### 5. Verifica WordPress (se configurato)

**Con GitHub Updater:**
- Dashboard → Updates
- Dovresti vedere l'aggiornamento disponibile

**Con Deploy SSH:**
- Il plugin dovrebbe essere già aggiornato automaticamente
- Verifica: Plugins → FP Experiences → versione 0.3.7

---

## 📊 Cosa Aspettarsi

### Timeline del Workflow (circa 2-3 minuti)

```
00:00 - Trigger workflow (push su main)
00:10 - Checkout e setup PHP
00:30 - Installazione dipendenze Composer
01:00 - Build plugin ZIP
01:30 - Upload artifact
02:00 - Creazione release GitHub
02:30 - (Opzionale) Deploy SSH su WordPress
03:00 - ✅ Completato!
```

### Output Atteso

Il workflow dovrebbe mostrare:

```
✅ Plugin version: 0.3.7
✅ Build plugin ZIP
✅ Upload build artifact
✅ Create GitHub Release
   📦 Release v0.3.7 created
   🔗 https://github.com/[user]/[repo]/releases/tag/v0.3.7
✅ Deployment completato con successo!
```

---

## 🐛 Troubleshooting Test

### ❌ Workflow non parte

**Problema**: Non vedi il workflow in Actions

**Soluzione**:
1. Verifica di aver fatto push su `main` (non su altro branch)
2. Controlla che il file `.github/workflows/deploy-on-merge.yml` esista
3. Vai su Actions → Workflow dovrebbe apparire nella lista a sinistra

### ❌ Build fallisce

**Problema**: Il workflow fallisce durante la build

**Soluzione**:
1. Controlla i log in GitHub Actions
2. Verifica che la versione sia nel formato corretto (X.Y.Z)
3. Assicurati che il file `fp-experiences.php` sia valido

Esempio di errore comune:
```
Error: Unable to determine plugin version
```
→ Controlla che la linea `Version:` sia corretta in `fp-experiences.php`

### ❌ Release non viene creata

**Problema**: Il workflow completa ma non vedo la release

**Soluzione**:
1. Controlla i log del workflow, sezione "Create GitHub Release"
2. Verifica che non esista già una release con lo stesso tag
3. Se esiste, elimina la vecchia release/tag e riprova

### ❌ Deploy SSH fallisce

**Problema**: Il workflow fallisce durante il deploy SSH

**Soluzione**:
1. Verifica che `ENABLE_WP_DEPLOY=true` in Settings → Variables
2. Controlla tutti i secrets:
   - `WP_SSH_HOST`
   - `WP_SSH_USER`
   - `WP_SSH_PASSWORD`
   - `WP_INSTALL_PATH`
3. Testa la connessione SSH manualmente:
   ```bash
   ssh [user]@[host]
   ```

---

## 🎯 Test Opzionali

### Test con Branch Diverso

1. Crea un branch di test:
   ```bash
   git checkout -b test-deployment
   ```

2. Aggiorna la versione a `0.3.7-beta`

3. Push:
   ```bash
   git push origin test-deployment
   ```

4. Verifica che il workflow NON parta (è configurato solo per `main`)

5. Merge su main:
   ```bash
   git checkout main
   git merge test-deployment
   git push origin main
   ```

6. Ora il workflow dovrebbe partire!

### Test Esecuzione Manuale

1. Vai su GitHub → Actions
2. Seleziona "Deploy Plugin on Merge"
3. Clicca "Run workflow"
4. Seleziona il branch `main`
5. Clicca "Run workflow"
6. Il workflow parte manualmente!

---

## ✅ Checklist Test Completo

- [ ] Script di aggiornamento versione funziona
- [ ] Push su main triggera il workflow
- [ ] Workflow completa senza errori
- [ ] Release viene creata su GitHub
- [ ] File ZIP è allegato alla release
- [ ] (Opzionale) WordPress riceve l'aggiornamento
- [ ] Version in WordPress corrisponde a quella del commit

---

## 📝 Risultato Test

Dopo aver completato il test, dovresti avere:

1. ✅ Release `v0.3.7` su GitHub
2. ✅ File `fp-experiences-0.3.7.zip` scaricabile
3. ✅ WordPress aggiornato (se configurato)
4. ✅ Workflow verde in GitHub Actions

---

## 🎓 Dopo il Test

Se tutto ha funzionato:

1. 🎉 **Congratulazioni!** Il sistema è configurato correttamente
2. 📝 Da ora in poi, ogni merge su `main` deployerà automaticamente
3. 🔄 Ricorda di aggiornare la versione prima di ogni merge

Se qualcosa non ha funzionato:

1. 📖 Leggi `.github/DEPLOYMENT.md` per dettagli
2. 🔍 Controlla i log in GitHub Actions
3. 🛠️ Usa questa guida per il troubleshooting

---

**Buon deployment! 🚀**

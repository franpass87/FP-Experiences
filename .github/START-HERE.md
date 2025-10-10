# 🎯 INIZIA QUI - Deployment Automatico

Benvenuto nel sistema di deployment automatico per **FP Experiences**!

---

## 🚀 In 30 Secondi

Il tuo plugin è ora configurato per il **deployment automatico**:

```
Merge su main → Build automatica → Release GitHub → WordPress aggiornato
```

---

## 📖 Documentazione

Scegli dove iniziare in base alle tue esigenze:

### 🟢 Nuovo al Sistema? Inizia Qui

1. **[DEPLOYMENT-SETUP.md](../DEPLOYMENT-SETUP.md)**  
   ⏱️ 5 minuti | 📘 Setup veloce  
   Configurazione base per far funzionare tutto

### 🟡 Vuoi Tutti i Dettagli?

2. **[DEPLOYMENT.md](.github/DEPLOYMENT.md)**  
   ⏱️ 15 minuti | 📗 Guida completa  
   Tutte le opzioni, troubleshooting, best practices

### 🔵 Pronto a Testare?

3. **[QUICK-TEST.md](.github/QUICK-TEST.md)**  
   ⏱️ 5 minuti | 📙 Test pratico  
   Verifica che tutto funzioni correttamente

### 🟣 Vuoi una Panoramica?

4. **[GITHUB-DEPLOYMENT-SUMMARY.md](../GITHUB-DEPLOYMENT-SUMMARY.md)**  
   ⏱️ 3 minuti | 📕 Riepilogo completo  
   Visione d'insieme del sistema

---

## ⚡ Quick Actions

### Aggiorna Versione e Deploy

```bash
# 1. Aggiorna versione
.github/scripts/update-version.sh 0.3.7

# 2. Commit e push
git commit -am "Bump version to 0.3.7"
git push origin main

# 3. Fatto! GitHub Actions fa il resto 🎉
```

### Verifica Deployment

```bash
# Apri GitHub Actions nel browser
# https://github.com/[tuo-username]/[repo]/actions

# Apri GitHub Releases nel browser
# https://github.com/[tuo-username]/[repo]/releases
```

---

## 🎯 Setup in 3 Passaggi

### Passo 1: Scegli il Metodo

- **Opzione A**: GitHub Updater (consigliato, zero configurazione GitHub)
- **Opzione B**: Deploy SSH diretto (richiede secrets GitHub)

### Passo 2: Configura

Segui le istruzioni in **[DEPLOYMENT-SETUP.md](../DEPLOYMENT-SETUP.md)**

### Passo 3: Testa

Segui **[QUICK-TEST.md](.github/QUICK-TEST.md)**

---

## 📊 Cosa Hai Ora

✅ **Workflow GitHub Actions** che si attiva automaticamente  
✅ **Build automatica** del plugin ad ogni merge  
✅ **Release GitHub** con file ZIP scaricabile  
✅ **Deploy WordPress** (opzionale, da configurare)  
✅ **Script helper** per aggiornare la versione  
✅ **Documentazione completa** per ogni scenario  

---

## 🎓 Percorso Consigliato

```
1. Leggi DEPLOYMENT-SETUP.md (5 min)
   ↓
2. Configura il metodo scelto (5 min)
   ↓
3. Esegui QUICK-TEST.md (5 min)
   ↓
4. ✅ Sistema funzionante!
   ↓
5. Usa quotidianamente
```

**Tempo totale**: 15 minuti

---

## 💡 Tips

- 📌 Aggiungi questa guida ai bookmark
- 📌 Condividi con il team
- 📌 Consulta DEPLOYMENT.md per troubleshooting
- 📌 Usa gli script helper per velocizzare

---

## 🆘 Hai Bisogno di Aiuto?

1. ❓ **Domande generali**: Leggi [GITHUB-DEPLOYMENT-SUMMARY.md](../GITHUB-DEPLOYMENT-SUMMARY.md)
2. 🐛 **Problemi tecnici**: Consulta [DEPLOYMENT.md](.github/DEPLOYMENT.md) → Troubleshooting
3. 🧪 **Test fallito**: Segui [QUICK-TEST.md](.github/QUICK-TEST.md) → Sezione Debug

---

## 🎉 Pronto?

**Prossimo passo**: Apri **[DEPLOYMENT-SETUP.md](../DEPLOYMENT-SETUP.md)** e inizia!

⏱️ **Tempo stimato**: 5-15 minuti  
🎯 **Difficoltà**: Facile  
✅ **Risultato**: Deployment automatico funzionante

---

**Buon deployment! 🚀**

_Creato: 2025-10-10 | FP Experiences v0.3.6_

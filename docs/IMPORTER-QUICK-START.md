# Guida Rapida Importer Esperienze

## 🚀 Avvio Veloce

### 1. Accedi all'Importer
**FP Experiences** → **Importer Esperienze**

### 2. Scarica il Template
Clicca su **"⬇️ Scarica Template CSV"** per ottenere il file di esempio.

### 3. Compila il File
Apri con Excel, Google Sheets o LibreOffice e compila le tue esperienze.

### 4. Carica e Importa
Seleziona il file CSV compilato e clicca **"🚀 Importa Esperienze"**.

---

## 📋 Campi Essenziali

| Campo | Obbligatorio | Esempio | Note |
|-------|--------------|---------|------|
| `title` | ✅ Sì | Tour Colosseo | Nome dell'esperienza |
| `status` | No (default: draft) | publish | publish, draft, pending, private |
| `description` | No | Bellissimo tour... | Descrizione completa (supporta HTML) |
| `duration_minutes` | No | 120 | Durata in minuti |
| `base_price` | No | 35.00 | Prezzo (usa il punto per i decimali) |
| `highlights` | No | Centro\|Guida\|Storia | Punti salienti (separati da \|) |
| `themes` | No | Cultura\|Arte | Categorie (separate da \|) |
| `languages` | No | Italiano\|English | Lingue disponibili (separate da \|) |
| `family_friendly` | No | yes | yes/no - adatto alle famiglie |

---

## ⚡ Separatore Liste

Per campi che accettano valori multipli, usa il carattere **pipe** `|`:

✅ **Corretto**: `Cultura|Storia|Arte`  
❌ **Sbagliato**: `Cultura, Storia, Arte`

### Campi che usano il separatore pipe:
- `highlights`
- `inclusions`
- `exclusions`
- `themes`
- `languages`

---

## 💡 Suggerimenti Rapidi

### Formato File
- **Estensione**: `.csv`
- **Codifica**: UTF-8 (importante!)
- **Separatore decimale**: Punto `.` (non virgola)

### In Excel
Salva come: **CSV UTF-8 (delimitato da virgole)**

### In Google Sheets
File → Scarica → **CSV (UTF-8)**

---

## 🎯 Esempio Minimo

```csv
title,status
Tour Colosseo,publish
Visita Musei Vaticani,draft
Cooking Class Trastevere,publish
```

## 🎯 Esempio Completo

```csv
title,status,description,duration_minutes,base_price,highlights,themes,languages,family_friendly
Tour della città storica,publish,"Scopri i segreti della nostra città con una guida esperta. Visiteremo i monumenti più importanti e ascolterai storie affascinanti.",120,35.00,"Centro storico|Monumenti principali|Guida esperta|Storia","Cultura|Storia","Italiano|English",yes
```

---

## ⚠️ Cose da Sapere

### ✅ Cosa viene importato
- Dati base dell'esperienza
- Contenuti testuali
- Prezzi e durate
- Tassonomie (temi, family-friendly)

### ❌ Cosa NON viene importato
- Immagini (aggiungile dopo manualmente)
- Calendari e orari
- Tipi di biglietto avanzati
- FAQ
- Add-ons

### ⚡ Comportamento
- L'import **CREA SEMPRE** nuove esperienze
- Non sovrascrive esperienze esistenti
- Righe con errori vengono saltate
- Gli errori vengono registrati nei log

---

## 🆘 Problemi Comuni

### "File non valido"
➡️ Assicurati che sia CSV con codifica UTF-8

### "Caratteri strani"
➡️ Salva il file con codifica UTF-8

### "Liste non funzionano"
➡️ Usa il carattere pipe `|` senza spazi

### "Import lento"
➡️ Importa max 50-100 esperienze alla volta

---

## 📚 Documentazione Completa

Per la guida completa con tutti i dettagli, vedi:
📖 **[IMPORTER-GUIDE.md](./IMPORTER-GUIDE.md)**

---

## ✅ Checklist Pre-Import

- [ ] File in formato CSV
- [ ] Codifica UTF-8
- [ ] Prima riga con intestazioni
- [ ] Campo `title` compilato per ogni riga
- [ ] Numeri decimali con il punto
- [ ] Liste separate da pipe `|`
- [ ] Testato con 1-2 righe di prova

---

**Buon Import! 🎉**

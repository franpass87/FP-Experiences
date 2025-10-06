# FP Experiences Plugin

Questo repository contiene il codice sorgente del plugin WordPress FP Experiences. Lo sviluppo segue il playbook a fasi documentato in [`docs/PLAYBOOK.md`](docs/PLAYBOOK.md).

## Versione Attuale: 0.3.4

**Ultimo aggiornamento**: 27 gennaio 2025

## Modulo Meeting Points

* Abilita o disabilita la funzionalità da **FP Experiences → Impostazioni → Generali → Modulo meeting points**.
* Gestisci le voci meeting point sotto **FP Experiences → Meeting Points** con indirizzo, note, dettagli di contatto e coordinate opzionali.
* Crea location in massa incollando un CSV in **FP Experiences → Import Meeting Points** (`title,address,lat,lng,notes,phone,email,opening_hours`).
* Associa meeting point alle esperienze dalla meta box dedicata quando modifichi un'esperienza (primario + alternativo).
* Renderizza l'output con lo shortcode `[fp_exp_meeting_points id="123"]` o il widget Elementor "FP Meeting Points"; entrambi mostrano la location primaria con alternative collassabili opzionali e link Google Maps costruiti client-side.

## Miglioramenti Editor Esperienze

* Cura la hero gallery dal pannello **Dettagli → Galleria immagini** con ordinamento drag-and-drop, upload multipli e cancellazione con un click.
* Scegli le lingue disponibili direttamente nella tab **Dettagli**, crea nuovi termini al volo e anteprima i badge pubblici (bandiera + etichetta) prima del salvataggio.
* Guida gli editor con preset badge riutilizzabili (family friendly, best seller, ecc.) che includono descrizioni e possono essere assegnati dal form esperienza.
* Puliti gli elenchi essenziali/note per usare bullet nativi così le checklist copiate si renderizzano consistentemente attraverso i temi.

## Branding e Badge Listing

* Personalizza sfondi icone sezione e colori glifi da **Impostazioni → Branding**; i valori si propagano al frontend via variabili CSS e icone Font Awesome.
* Gestisci la libreria badge globale da **Impostazioni → Showcase → Badge esperienze**, modificando etichette/descrizioni predefinite o aggiungendo voci specifiche dell'organizzazione disponibili agli editor.
* L'iconografia globale ora proviene dal bundle Font Awesome enqueued, assicurando rendering consistente senza dipendere da SVG per-template.

## Processo di Release

Fai riferimento a [README-BUILD.md](README-BUILD.md) per il workflow di packaging end-to-end. In breve:

1. Esegui `bash build.sh --bump=patch` (o `--set-version=1.2.3`) per incrementare la versione, installare le dipendenze di produzione e produrre un zip pulito in `build/`.
2. Opzionalmente pusha un tag come `v1.2.3` per triggerare l'azione GitHub automatizzata che costruisce e carica l'artefatto zip.

## Controlli di Sviluppo

Esegui `tools/run-php-syntax-check.sh` per fare lint di ogni file PHP sia negli alberi sorgente che compilati. Lo script esce al primo errore sintattico così i problemi possono essere risolti prima del packaging.

## Documentazione Consolidata

La documentazione è stata ottimizzata e consolidata:
- **Audit Completo**: [docs/AUDIT-COMPLETO.md](docs/AUDIT-COMPLETO.md) - Tutti gli audit di sicurezza, performance, accessibilità e integrazioni
- **Importer Completo**: [docs/IMPORTER-COMPLETO.md](docs/IMPORTER-COMPLETO.md) - Guida completa all'importer di esperienze
- **Verifica Completa**: [docs/VERIFICA-COMPLETA.md](docs/VERIFICA-COMPLETA.md) - Guida di verifica completa per tutti i componenti

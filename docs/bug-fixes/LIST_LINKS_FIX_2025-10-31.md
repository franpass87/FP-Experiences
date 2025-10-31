# 🐛 Bug Fix: Link Errati nella Lista Esperienze

**Data:** 31 Ottobre 2025  
**Priorità:** 🟡 **ALTA**  
**Status:** 🔍 **IN ANALISI / DEBUG ATTIVO**

---

## 📋 Problema Rilevato

### Sintomo
Le esperienze ("degustazioni") nella **seconda riga** della lista puntano all'ultima esperienza della **prima riga**, come se i link non fossero renderizzati correttamente.

### Esempio
```
Riga 1: [Exp A] [Exp B] [Exp C]
Riga 2: [Exp D] [Exp E] [Exp F]

Exp D, E, F → tutti linkano a Exp C ❌
```

---

## 🔍 Causa Possibile

### Ipotesi #1: Page ID Condiviso
Tutte le esperienze potrebbero avere lo stesso `_fp_exp_page_id` (una pagina template comune), causando la generazione dello stesso URL per esperienze diverse.

### Ipotesi #2: Post Globale di WordPress
Il post globale di WordPress potrebbe non essere resettato correttamente tra un'esperienza e l'altra nel loop.

### Ipotesi #3: Caching
I permalink potrebbero essere cached in modo errato.

---

## ✅ Fix Applicati

### 1. Uso Diretto di `$post->post_title`
Invece di usare `get_the_title($post)` che potrebbe essere influenzato dal post globale, ora usiamo direttamente la proprietà dell'oggetto.

```php
// ❌ PRIMA
$title = get_the_title($post);

// ✅ DOPO
$title = $post->post_title; // Direct property access
```

**File:** `src/Shortcodes/ListShortcode.php` - Linea 502

### 2. Debug Logging Attivo
Aggiunto logging dettagliato per tracciare la risoluzione dei permalink:

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[FP-EXP-LIST] Experience link: ' . wp_json_encode([
        'experience_id' => $experience_id,
        'page_id' => $page_id,
        'url' => $url
    ]));
}
```

**File:** `src/Shortcodes/ListShortcode.php` - Linee 627-659

---

## 🧪 Testing & Debug

### Attivare Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Verificare Log
```bash
# Visualizza log in tempo reale
tail -f wp-content/debug.log | grep 'FP-EXP-LIST'
```

### Output Atteso
```
[FP-EXP-LIST] Experience link: {"experience_id":123,"page_id":0,"experience_url":"https://..."}
[FP-EXP-LIST] Experience link: {"experience_id":124,"page_id":0,"experience_url":"https://..."}
[FP-EXP-LIST] Experience link: {"experience_id":125,"page_id":0,"experience_url":"https://..."}
```

### Red Flags
```
// ❌ PROBLEMA: Tutte le esperienze hanno lo stesso page_id
[FP-EXP-LIST] Page link: {"experience_id":123,"page_id":456,"page_url":"https://..."}
[FP-EXP-LIST] Page link: {"experience_id":124,"page_id":456,"page_url":"https://..."}
[FP-EXP-LIST] Page link: {"experience_id":125,"page_id":456,"page_url":"https://..."}

// ❌ PROBLEMA: URL sono tutte uguali
"page_url":"https://domain.com/esperienze/"
"page_url":"https://domain.com/esperienze/"
"page_url":"https://domain.com/esperienze/"
```

---

## 🔧 Possibili Soluzioni Aggiuntive

### Se il problema è Page ID Condiviso

**Opzione A:** Rimuovere `_fp_exp_page_id` dalle esperienze problematiche:
```php
// Nel backend WordPress, per ogni esperienza:
delete_post_meta($experience_id, '_fp_exp_page_id');
```

**Opzione B:** Usare sempre il permalink diretto (ignorare page_id):
```php
// Modifica resolve_permalink() per skippar

e page_id
private function resolve_permalink(int $experience_id, string $cta_mode): string
{
    // Always use direct experience permalink
    return get_permalink($experience_id) ?: '';
}
```

### Se il problema è nel Template

**Verifica HTML generato:**
```html
<!-- Ogni esperienza dovrebbe avere il proprio URL univoco -->
<article data-experience-id="123">
    <a href="https://domain.com/experience/wine-tasting-123/">...</a>
</article>
<article data-experience-id="124">
    <a href="https://domain.com/experience/cooking-class-124/">...</a>
</article>
```

**Se tutti i link sono uguali:**
```html
<!-- ❌ PROBLEMA RILEVATO -->
<article data-experience-id="123">
    <a href="https://domain.com/esperienze/">...</a>
</article>
<article data-experience-id="124">
    <a href="https://domain.com/esperienze/">...</a> <!-- STESSO URL! -->
</article>
```

---

## 📊 Informazioni da Raccogliere

### Dal Database
```sql
-- Verifica se più esperienze hanno lo stesso page_id
SELECT post_id, meta_value as page_id, COUNT(*) as count
FROM wp_postmeta
WHERE meta_key = '_fp_exp_page_id'
AND meta_value != '0'
GROUP BY meta_value
HAVING count > 1;
```

### Dal Frontend (Ispeziona HTML)
```javascript
// Console browser
document.querySelectorAll('.fp-listing__card').forEach(card => {
    const id = card.dataset.experienceId;
    const links = card.querySelectorAll('a');
    links.forEach(link => console.log(id, link.href));
});
```

---

## 🎯 Prossimi Passi

1. **Attiva debug mode** e ricarica la lista esperienze
2. **Verifica debug.log** per vedere i permalink generati
3. **Ispeziona HTML** per confermare che i link siano diversi
4. **Testa con diverse configurazioni** (page_id presente/assente)
5. **Se il problema persiste**, considera di rimuovere tutti i `_fp_exp_page_id`

---

## 📝 Note Tecniche

### Come Funziona resolve_permalink()
```php
1. Recupera _fp_exp_page_id dal meta dell'esperienza
2. Se page_id > 0:
   → Usa get_permalink($page_id)
   → Se fallisce, usa get_permalink($experience_id)
3. Se page_id = 0:
   → Usa get_permalink($experience_id) direttamente
```

### Quando Usare Page ID
- ✅ Quando ogni esperienza ha una pagina WordPress dedicata **unica**
- ❌ Quando tutte le esperienze usano la stessa pagina template

### Permalink WordPress
`get_permalink($id)` dovrebbe sempre ritornare un URL univoco per ogni post, a meno che:
- Il post non esista
- Lo slug sia duplicato (WordPress aggiunge suffissi automaticamente)
- Ci siano problemi di rewrite rules

---

## 🔗 File Correlati

- `src/Shortcodes/ListShortcode.php` - Logica lista esperienze
- `templates/front/list.php` - Template HTML lista
- `src/Admin/ExperiencePageCreator.php` - Creazione pagine dedicate

---

## 👤 Autore

**Assistant AI (Claude Sonnet 4.5)**  
In collaborazione con: Francesco Passeri

**Data:** 31 Ottobre 2025  
**Status:** Debug attivo, in attesa di log per diagnosi completa



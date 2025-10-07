# Migrazione a Formato Unico: _fp_exp_recurrence

## 🎯 Obiettivo

Eliminare la duplicazione tra `_fp_exp_recurrence` e `_fp_exp_availability`, usando solo `_fp_exp_recurrence` come fonte di verità unica.

## 📊 Situazione Attuale (Problema)

```
Admin (salva)
    ↓
_fp_exp_recurrence (nuovo formato con time_sets)
    ↓
sync_recurrence_to_availability() ⚠️ Può fallire
    ↓
_fp_exp_availability (formato legacy)
    ↓
AvailabilityService (legge)
    ↓
Frontend Calendario
```

**Problemi**:
- Duplicazione dati
- Sincronizzazione può fallire
- Inconsistenze tra i due formati
- Complessità di manutenzione

## ✅ Soluzione Proposta

```
Admin (salva)
    ↓
_fp_exp_recurrence (unica fonte di verità)
    ↓
AvailabilityService (legge direttamente)
    ↓
Frontend Calendario
```

**Vantaggi**:
- ✅ Nessuna sincronizzazione necessaria
- ✅ Nessuna duplicazione
- ✅ Nessuna inconsistenza possibile
- ✅ Codice più semplice e manutenibile
- ✅ Performance migliori (una query invece di due)

## 🔧 Modifiche Necessarie

### 1. Modificare AvailabilityService::get_virtual_slots()

**Cambiare da**:
```php
$availability = get_post_meta($experience_id, '_fp_exp_availability', true);
$times = $availability['times'];
$days = $availability['days_of_week'];
```

**A**:
```php
$recurrence = get_post_meta($experience_id, '_fp_exp_recurrence', true);
// Estrai times e days dai time_sets
$times = [];
$days = [];
foreach ($recurrence['time_sets'] as $set) {
    $times = array_merge($times, $set['times']);
    $days = array_merge($days, $set['days']);
}
```

### 2. Rimuovere sync_recurrence_to_availability()

Questo metodo non sarà più necessario.

### 3. Deprecare _fp_exp_availability

Mantenere per retrocompatibilità ma non usarlo più.

## 📝 Implementazione

Sto creando la nuova versione di AvailabilityService che legge direttamente da _fp_exp_recurrence.

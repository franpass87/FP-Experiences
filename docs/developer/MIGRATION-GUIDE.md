# 🔄 Guida alla Migrazione - Da Plugin::instance() a Bootstrap::kernel()

**Versione**: 1.2.0+  
**Data**: Dicembre 2025

Questa guida spiega come migrare dal vecchio pattern `Plugin::instance()` alla nuova architettura basata su Kernel.

---

## 📋 Indice

1. [Panoramica](#panoramica)
2. [Perché Migrare](#perché-migrare)
3. [Pattern Vecchio vs Nuovo](#pattern-vecchio-vs-nuovo)
4. [Esempi Pratici](#esempi-pratici)
5. [Helper Functions](#helper-functions)
6. [FAQ](#faq)

---

## 🎯 Panoramica

### Architettura Vecchia (Deprecata)

```php
// ❌ VECCHIO - Deprecato dalla v1.2.0
$plugin = Plugin::instance();
$cart = $plugin->cart; // Accesso diretto alle proprietà
```

### Architettura Nuova (Raccomandata)

```php
// ✅ NUOVO - Raccomandato
$kernel = Bootstrap::kernel();
$container = $kernel->container();
$cart = $container->make(Cart::class);
```

---

## ❓ Perché Migrare

1. **Dependency Injection**: Accesso ai servizi tramite container
2. **Testabilità**: Più facile testare con mock
3. **Manutenibilità**: Codice più modulare e organizzato
4. **Performance**: Lazy loading dei servizi
5. **Futuro**: Il Plugin legacy sarà rimosso in v2.0.0

---

## 🔄 Pattern Vecchio vs Nuovo

### Accesso ai Servizi

#### Vecchio Pattern

```php
// ❌ Deprecato
$plugin = Plugin::instance();
$cart = $plugin->cart;
$orders = $plugin->orders;
$checkout = $plugin->checkout;
```

#### Nuovo Pattern

```php
// ✅ Raccomandato
$kernel = Bootstrap::kernel();
if ($kernel !== null) {
    $container = $kernel->container();
    $cart = $container->make(Cart::class);
    $orders = $container->make(Orders::class);
    $checkout = $container->make(Checkout::class);
}
```

#### Con Helper (Più Semplice)

```php
// ✅ Ancora più semplice
$cart = Bootstrap::get(Cart::class);
$orders = Bootstrap::get(Orders::class);
$checkout = Bootstrap::get(Checkout::class);
```

---

## 💡 Esempi Pratici

### Esempio 1: Accesso al Carrello

```php
// ❌ VECCHIO
$plugin = Plugin::instance();
$cart = $plugin->cart;
$items = $cart->get_items();

// ✅ NUOVO
$cart = Bootstrap::get(Cart::class);
if ($cart !== null) {
    $items = $cart->get_items();
}
```

### Esempio 2: Accesso al Logger

```php
// ❌ VECCHIO
$plugin = Plugin::instance();
// Logger non era direttamente accessibile

// ✅ NUOVO
$logger = Bootstrap::get(LoggerInterface::class);
if ($logger !== null) {
    $logger->log('info', 'Messaggio di log', ['context' => $data]);
}
```

### Esempio 3: Verifica Disponibilità Servizio

```php
// ❌ VECCHIO
$plugin = Plugin::instance();
if (isset($plugin->cart)) {
    // Usa cart
}

// ✅ NUOVO
if (Bootstrap::has(Cart::class)) {
    $cart = Bootstrap::get(Cart::class);
    // Usa cart
}
```

### Esempio 4: Iniezione Dipendenze in Classe

```php
// ❌ VECCHIO
class MyService {
    public function __construct() {
        $this->cart = Plugin::instance()->cart;
    }
}

// ✅ NUOVO
class MyService {
    private Cart $cart;
    
    public function __construct(?Cart $cart = null) {
        $this->cart = $cart ?? Bootstrap::get(Cart::class);
    }
}

// Oppure tramite container
$container->bind(MyService::class, function($container) {
    return new MyService($container->make(Cart::class));
});
```

---

## 🛠️ Helper Functions

### `Bootstrap::kernel()`

Ottiene l'istanza del Kernel.

```php
$kernel = Bootstrap::kernel();
if ($kernel !== null) {
    // Kernel disponibile
}
```

### `Bootstrap::get(string $service)`

Ottiene un servizio dal container.

```php
$cart = Bootstrap::get(Cart::class);
$logger = Bootstrap::get(LoggerInterface::class);
```

### `Bootstrap::has(string $service)`

Verifica se un servizio è disponibile.

```php
if (Bootstrap::has(Cart::class)) {
    $cart = Bootstrap::get(Cart::class);
}
```

---

## 📚 Servizi Disponibili

### Core Services

- `LoggerInterface` - Sistema di logging
- `CacheInterface` - Sistema di cache
- `OptionsInterface` - Gestione opzioni
- `DatabaseInterface` - Accesso database
- `ValidatorInterface` - Validazione
- `SanitizerInterface` - Sanitizzazione

### Booking Services

- `Cart::class` - Carrello prenotazioni
- `Orders::class` - Gestione ordini
- `Checkout::class` - Processo checkout
- `Slots::class` - Gestione slot

### Domain Repositories

- `ExperienceRepositoryInterface` - Repository esperienze
- `SlotRepositoryInterface` - Repository slot
- `ReservationRepositoryInterface` - Repository prenotazioni
- `VoucherRepositoryInterface` - Repository voucher

### Use Cases

- `CheckAvailabilityUseCase` - Verifica disponibilità
- `CreateReservationUseCase` - Crea prenotazione
- `ProcessCheckoutUseCase` - Processa checkout
- E molti altri...

---

## ❓ FAQ

### Q: Il vecchio codice funziona ancora?

**A**: Sì, `Plugin::instance()` è ancora disponibile per retrocompatibilità, ma genera un warning di deprecazione. Sarà rimosso in v2.0.0.

### Q: Devo migrare tutto subito?

**A**: No, puoi migrare gradualmente. Il vecchio codice continuerà a funzionare fino alla v2.0.0.

### Q: Come verifico se un servizio esiste?

**A**: Usa `Bootstrap::has($service)` prima di chiamare `Bootstrap::get($service)`.

### Q: Cosa succede se il Kernel non è inizializzato?

**A**: `Bootstrap::kernel()` restituisce `null`. Controlla sempre il valore di ritorno.

### Q: Posso ancora usare Plugin::instance()?

**A**: Sì, ma è deprecato. Usa `Bootstrap::kernel()` o `Bootstrap::get()` per il nuovo codice.

---

## 🔍 Checklist Migrazione

- [ ] Sostituire `Plugin::instance()` con `Bootstrap::kernel()` o `Bootstrap::get()`
- [ ] Verificare che tutti i servizi siano disponibili nel container
- [ ] Aggiornare i test per usare il nuovo pattern
- [ ] Rimuovere dipendenze dirette dal Plugin legacy
- [ ] Documentare le modifiche nel codice

---

## 📖 Risorse

- [Architettura Modulare](technical/MODULAR-ARCHITECTURE.md)
- [Playbook Sviluppatore](PLAYBOOK.md)
- [Quick Start Dev](QUICK-START-DEV.md)

---

**Ultimo aggiornamento**: Dicembre 2025  
**Versione Plugin**: 1.2.0+




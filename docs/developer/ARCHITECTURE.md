# Architettura FP Experiences

## Panoramica

FP Experiences utilizza un'architettura basata su **Kernel** e **Service Providers**, seguendo i principi di **Dependency Injection** e **Separation of Concerns**.

## Componenti Principali

### 1. Kernel

Il **Kernel** è il componente centrale che gestisce il ciclo di vita del plugin e coordina tutti i Service Providers.

**File**: `src/Core/Kernel/Kernel.php`

**Responsabilità**:
- Gestione del Container (Dependency Injection)
- Registrazione e boot dei Service Providers
- Coordinamento dell'ordine di inizializzazione

**Accesso**:
```php
$kernel = \FP_Exp\Core\Bootstrap\Bootstrap::kernel();
$container = $kernel->container();
```

### 2. Container

Il **Container** gestisce la Dependency Injection, permettendo di risolvere automaticamente le dipendenze tra servizi.

**File**: `src/Core/Container/Container.php`

**Caratteristiche**:
- Singleton pattern per servizi condivisi
- Factory pattern per servizi con dipendenze complesse
- Lazy loading dei servizi

**Uso**:
```php
// Ottenere un servizio
$cart = Bootstrap::get(Cart::class);

// Verificare disponibilità
if (Bootstrap::has(Cart::class)) {
    $cart = Bootstrap::get(Cart::class);
}
```

### 3. Service Providers

I **Service Providers** registrano e bootano i servizi del plugin, organizzati per dominio funzionale.

#### CoreServiceProvider
**File**: `src/Providers/CoreServiceProvider.php`

**Servizi**:
- Logger
- Cache
- Options
- Database
- Security (RoleManager)

#### DomainServiceProvider
**File**: `src/Providers/DomainServiceProvider.php`

**Servizi**:
- Repositories
- Domain Services
- Value Objects

#### ApplicationServiceProvider
**File**: `src/Providers/ApplicationServiceProvider.php`

**Servizi**:
- Use Cases
- Application Services

#### BookingServiceProvider
**File**: `src/Providers/BookingServiceProvider.php`

**Servizi**:
- Cart
- Orders
- Checkout
- Emails
- RequestToBook
- Brevo (integrazione email)

#### GiftServiceProvider
**File**: `src/Providers/GiftServiceProvider.php`

**Servizi**:
- VoucherCPT
- VoucherManager

#### UtilityServiceProvider
**File**: `src/Providers/UtilityServiceProvider.php`

**Servizi**:
- MeetingPointsManager
- MigrationRunner
- AutoTranslator
- Webhooks
- RestRoutes

#### AdminServiceProvider
**File**: `src/Presentation/Admin/Providers/AdminServiceProvider.php`

**Servizi**:
- SettingsPage
- CalendarAdmin
- RequestsPage
- LogsPage
- ToolsPage
- EmailsPage
- CheckinPage
- OrdersPage
- HelpPage
- ImporterPage
- ExperiencePageCreator
- Onboarding
- LanguageAdmin
- AdminMenu
- ExperienceMetaBoxes

#### FrontendServiceProvider
**File**: `src/Presentation/Frontend/Providers/FrontendServiceProvider.php`

**Servizi**:
- ShortcodeRegistrar
- ExperienceCPT
- ElementorWidgetsRegistrar
- SingleExperienceRenderer

#### IntegrationServiceProvider
**File**: `src/Integrations/Providers/IntegrationServiceProvider.php`

**Servizi**:
- Brevo
- GoogleCalendar
- GA4
- GoogleAds
- MetaPixel
- Clarity
- ExperienceProduct
- WooCommerceProduct
- WooCommerceCheckout
- PerformanceIntegration

#### RESTServiceProvider
**File**: `src/Presentation/REST/Providers/RESTServiceProvider.php`

**Servizi**:
- REST API Controllers
- REST API Routes

#### LegacyServiceProvider
**File**: `src/Providers/LegacyServiceProvider.php`

**Servizi**:
- Plugin (facade per retrocompatibilità)
- RoleManager hooks
- Admin capabilities

## Flusso di Inizializzazione

1. **Bootstrap::init()** viene chiamato dal file principale del plugin
2. Il Kernel viene creato e inizializzato
3. I Service Providers vengono registrati nell'ordine corretto:
   - Core
   - Domain
   - Application
   - Booking
   - Gift
   - Utility
   - Legacy
   - Admin/Frontend (context-aware)
   - REST
   - Integrations
4. Ogni Service Provider registra i suoi servizi nel Container
5. Il Kernel viene bootato su `wp_loaded`
6. Ogni Service Provider boota i suoi servizi e registra gli hook

## Pattern Architetturali

### Dependency Injection

Tutti i servizi ricevono le loro dipendenze tramite il costruttore, risolte automaticamente dal Container.

**Esempio**:
```php
// Orders dipende da Cart
$container->singleton(Orders::class, static function (ContainerInterface $container): Orders {
    $cart = $container->make(Cart::class);
    return new Orders($cart);
});
```

### Service Provider Pattern

Ogni Service Provider implementa `AbstractServiceProvider` e definisce:
- `register()`: Registra i servizi nel Container
- `boot()`: Boota i servizi e registra gli hook
- `provides()`: Lista dei servizi forniti

### Facade Pattern

La classe `Plugin` è ora una facade minimale che delega al Container per retrocompatibilità.

**Esempio**:
```php
// Vecchio modo (deprecato)
$cart = Plugin::instance()->cart;

// Nuovo modo
$cart = Bootstrap::get(Cart::class);

// Facade (per retrocompatibilità)
$cart = Plugin::instance()->cart();
```

## Convenzioni

### Naming

- Service Providers: `*ServiceProvider.php`
- Services: Nome descrittivo (es. `Cart`, `Orders`)
- Controllers: `*Controller.php`
- Repositories: `*Repository.php`

### Organizzazione File

```
src/
├── Core/              # Componenti core (Kernel, Container, etc.)
├── Providers/         # Service Providers principali
├── Booking/           # Servizi booking
├── Gift/              # Servizi gift voucher
├── Admin/             # Servizi admin
├── Integrations/      # Integrazioni esterne
├── Presentation/      # Service Providers per presentazione
│   ├── Admin/
│   ├── Frontend/
│   └── REST/
└── Utils/             # Utility e helper
```

## Best Practices

1. **Sempre usare Dependency Injection**: Non istanziare servizi direttamente, usare il Container
2. **Service Providers per dominio**: Raggruppare servizi correlati nello stesso provider
3. **Lazy Loading**: I servizi vengono creati solo quando richiesti
4. **Error Handling**: Gestire gli errori gracefully senza rompere il sito
5. **Backward Compatibility**: Mantenere la classe Plugin come facade per retrocompatibilità

## Migrazione da Plugin Legacy

Vedi [MIGRATION-GUIDE.md](./MIGRATION-GUIDE.md) per dettagli sulla migrazione dal vecchio sistema al nuovo.

## Estendere l'Architettura

### Aggiungere un Nuovo Servizio

1. Creare la classe del servizio
2. Registrarla nel Service Provider appropriato
3. Definire le dipendenze nel costruttore
4. Registrare gli hook nel metodo `boot()` del provider

### Aggiungere un Nuovo Service Provider

1. Creare la classe che estende `AbstractServiceProvider`
2. Implementare `register()`, `boot()`, e `provides()`
3. Registrarlo in `Bootstrap::init()` nell'ordine corretto

## Debugging

### Verificare se un Servizio è Disponibile

```php
if (Bootstrap::has(Cart::class)) {
    $cart = Bootstrap::get(Cart::class);
}
```

### Ottenere il Container

```php
$kernel = Bootstrap::kernel();
$container = $kernel->container();
```

### Log degli Errori

Gli errori durante il boot dei servizi vengono loggati in `WP_DEBUG` mode.

## Riferimenti

- [Kernel Documentation](./KERNEL.md)
- [Migration Guide](./MIGRATION-GUIDE.md)
- [Service Provider Pattern](https://laravel.com/docs/providers)




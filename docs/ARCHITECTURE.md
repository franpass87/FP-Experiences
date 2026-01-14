# FP Experiences - Architecture Documentation

## Overview

FP Experiences follows a **Clean Architecture** pattern with clear separation of concerns across multiple layers. This architecture is designed to be **universal** and reusable across all FP plugins.

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Status**: Phase 1 - Foundation Complete

---

## Architecture Layers

### 1. Core Layer (Universal)

**Location**: `src/Core/`

**Responsibilities**:
- Plugin bootstrap and lifecycle management
- Dependency injection container
- Service provider registry
- Hook management
- Event system (future)
- Exception handling
- Compatibility checks

**Key Components**:
- `Bootstrap` - Plugin entry point
- `Container` - Dependency injection container
- `Kernel` - Plugin kernel managing service providers
- `CompatibilityCheck` - Environment validation
- `LifecycleManager` - Activation/deactivation handling

**Usage**: Never modify Core layer for plugin-specific logic. This layer is shared across all FP plugins.

---

### 2. Services Layer (Universal)

**Location**: `src/Services/`

**Responsibilities**:
- Cross-cutting concerns
- Reusable utilities
- No business logic

**Available Services**:

#### Logger Service
- **Interface**: `LoggerInterface`
- **Implementation**: `Logger`
- **Usage**: All modules should use this instead of `error_log()`

#### Cache Service
- **Interface**: `CacheInterface`
- **Implementation**: `TransientCache`
- **Usage**: Replace all `get_transient()` / `set_transient()` calls

#### Options Service
- **Interface**: `OptionsInterface`
- **Implementation**: `Options`
- **Usage**: Replace all `get_option()` / `update_option()` calls

#### Validation Service
- **Interface**: `ValidatorInterface`
- **Implementation**: `Validator`
- **Usage**: All form inputs and API requests

#### Sanitization Service
- **Interface**: `SanitizerInterface`
- **Implementation**: `Sanitizer`
- **Usage**: All user input before processing

#### HTTP Client Service
- **Interface**: `HttpClientInterface`
- **Implementation**: `WordPressHttpClient`
- **Usage**: All external API calls

#### Security Services
- **NonceManager**: Nonce generation/verification
- **CapabilityChecker**: Permission checking
- **InputSanitizer**: Input sanitization

**Usage**: Services can depend on other services, but should not depend on Domain or Application layers.

---

### 3. Domain Layer (Plugin-Specific)

**Location**: `src/Domain/`

**Responsibilities**:
- Business entities and value objects
- Repository interfaces
- Domain services
- Business rules and logic
- **No WordPress dependencies**

**Structure**:
```
Domain/
├── Booking/
│   ├── Entities/
│   ├── Repositories/
│   │   ├── SlotRepositoryInterface.php
│   │   ├── ReservationRepositoryInterface.php
│   │   └── ExperienceRepositoryInterface.php
│   └── ValueObjects/
└── Gift/
    └── ...
```

**Key Principles**:
- No WordPress functions
- Pure PHP business logic
- Interfaces define contracts
- Value objects encapsulate business concepts

---

### 4. Application Layer (Plugin-Specific)

**Location**: `src/Application/`

**Responsibilities**:
- Use cases (application services)
- Commands and queries
- DTOs for data transfer
- Orchestration of domain services

**Structure**:
```
Application/
├── Settings/
│   ├── GetSettingsUseCase.php
│   └── UpdateSettingsUseCase.php
└── Booking/
    └── GetAvailabilityUseCase.php
```

**Key Principles**:
- One use case per operation
- DTOs for input/output
- Orchestrates domain services
- No direct database access

---

### 5. Infrastructure Layer (Plugin-Specific)

**Location**: `src/Infrastructure/`

**Responsibilities**:
- Repository implementations
- Database access
- External API clients
- WordPress-specific implementations

**Structure**:
```
Infrastructure/
├── Database/
│   ├── Repositories/
│   │   ├── SlotRepository.php
│   │   ├── ReservationRepository.php
│   │   └── ExperienceRepository.php
│   └── Migrations/
└── WordPress/
    ├── Options/
    └── PostTypes/
```

**Key Principles**:
- Implements domain interfaces
- Handles WordPress-specific code
- Database queries here only
- External API calls here

---

### 6. Presentation Layer (Plugin-Specific)

**Location**: `src/Presentation/`

**Responsibilities**:
- Admin UI
- Frontend rendering
- REST API controllers
- Shortcodes/Blocks

**Structure**:
```
Presentation/
├── Admin/
│   ├── Pages/
│   ├── MetaBoxes/
│   └── Providers/
│       └── AdminServiceProvider.php
├── Frontend/
│   ├── Renderers/
│   ├── Shortcodes/
│   └── Providers/
│       └── FrontendServiceProvider.php
└── REST/
    ├── Controllers/
    └── Providers/
        └── RESTServiceProvider.php
```

**Key Principles**:
- UI only, no business logic
- Delegates to Application layer
- Handles HTTP concerns only
- Uses DTOs for data transfer

---

## Service Providers

Service providers register services with the container and boot them when needed.

### Provider Registration Order

1. **CoreServiceProvider** - Foundation services (always first)
2. **DomainServiceProvider** - Domain repositories and services
3. **ApplicationServiceProvider** - Use cases and application services
4. **Context-Aware Providers** (only if applicable):
   - `AdminServiceProvider` - Only if `is_admin()`
   - `FrontendServiceProvider` - Only if not admin
   - `RESTServiceProvider` - Registers on `rest_api_init`
5. **IntegrationServiceProvider** - External integrations (last)

### Provider Pattern

All providers extend `AbstractServiceProvider` and implement:

- `register()` - Register services with container
- `boot()` - Boot services after all providers registered
- `provides()` - List of services provided

---

## Dependency Flow

### Allowed Dependencies

```
Presentation → Application → Domain ← Infrastructure
     ↓              ↓            ↑
  Services ← Services ← Services
```

### Rules

1. **Presentation** can depend on:
   - Application layer
   - Services layer

2. **Application** can depend on:
   - Domain layer
   - Services layer

3. **Domain** can depend on:
   - Services layer (interfaces only)

4. **Infrastructure** can depend on:
   - Domain layer (interfaces only)
   - Services layer

5. **Services** can depend on:
   - Other services only

### Forbidden Dependencies

- ❌ Domain → Infrastructure (use interfaces)
- ❌ Domain → Presentation (no UI dependencies)
- ❌ Domain → Application (circular)
- ❌ Application → Infrastructure (use domain interfaces)
- ❌ Services → Domain/Application/Presentation (services are cross-cutting)

---

## Service Container

The container provides dependency injection throughout the plugin.

### Getting Services

```php
// From container directly
$kernel = Bootstrap::kernel();
$container = $kernel->container();
$logger = $container->make(LoggerInterface::class);

// From constructor (preferred)
class MyService {
    public function __construct(
        private LoggerInterface $logger
    ) {}
}
```

### Service Registration

Services are registered in service providers:

```php
// Singleton (one instance)
$container->singleton(LoggerInterface::class, Logger::class);

// Factory (new instance each time)
$container->bind(CacheInterface::class, function($container) {
    return new TransientCache('fp_exp_');
});
```

---

## Migration Strategy

### Current Status: Phase 1 - Foundation

✅ **Completed**:
- Core services registered
- HTTP client service
- Security services (Nonce, Capability, InputSanitizer)
- Lifecycle manager
- Migration logger
- Container fully functional

🔄 **In Progress**:
- Helpers compatibility layer (partial)
- Service migration

⏳ **Pending**:
- Domain layer extraction
- Application layer creation
- Presentation layer refactoring

### Backward Compatibility

- Legacy `Plugin` class still works (deprecated)
- Legacy `Helpers` class still works (deprecated)
- All hooks maintained
- All REST endpoints maintained
- All shortcodes maintained

---

## Best Practices

### 1. Use Dependency Injection

✅ **Good**:
```php
class MyService {
    public function __construct(
        private LoggerInterface $logger
    ) {}
}
```

❌ **Bad**:
```php
class MyService {
    public function log() {
        $logger = new Logger(); // Don't do this
    }
}
```

### 2. Use Service Interfaces

✅ **Good**:
```php
public function __construct(
    private CacheInterface $cache
) {}
```

❌ **Bad**:
```php
public function __construct(
    private TransientCache $cache
) {}
```

### 3. Keep Classes Small

- **Target**: < 500 lines per class
- **Current**: Some classes exceed this (refactoring in progress)

### 4. Single Responsibility

Each class should have one reason to change.

### 5. No Static Dependencies

Use dependency injection instead of static methods (except services).

---

## File Structure

```
FP-Experiences/
├── fp-experiences.php              # Entry point (< 50 lines)
├── src/
│   ├── Core/                       # Universal infrastructure
│   ├── Services/                   # Universal services
│   ├── Domain/                     # Business logic (no WP)
│   ├── Application/                # Use cases
│   ├── Infrastructure/             # WP-specific implementations
│   ├── Presentation/               # UI layer
│   ├── Integrations/               # External integrations
│   └── Providers/                  # Service providers
└── docs/
    └── ARCHITECTURE.md             # This file
```

---

## Related Documentation

- [Refactor Plan](../fp-experiences-refactor-plan.plan.md) - Complete refactoring strategy
- [Service Provider Guide](./SERVICE-PROVIDERS.md) - Detailed provider documentation
- [Migration Guide](./MIGRATION-GUIDE.md) - Step-by-step migration instructions

---

## Questions?

For questions about the architecture:
1. Check this documentation
2. Review the refactor plan
3. Check existing code examples
4. Contact the development team








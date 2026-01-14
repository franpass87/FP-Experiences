# FP Experiences - Automated Testing Plan

Comprehensive plan for automated testing of FP Experiences plugin.

## 1. Testing Architecture

### Test Structure
```
tests/
├── Unit/              # Unit tests for individual classes
├── Integration/       # Integration tests for components
├── QA/                # QA test suite
├── E2E/               # End-to-end tests (if applicable)
└── bootstrap.php      # Test bootstrap
```

### Testing Framework
- **PHPUnit 10.5+** - Primary testing framework
- **BrainMonkey** - WordPress hook/function mocking
- **WP_Mock** - WordPress-specific mocking (alternative)

---

## 2. Unit Testing

### Core Services Tests

#### Logger Tests
- [ ] Test log message creation
- [ ] Test log levels (debug, info, warning, error)
- [ ] Test log context data
- [ ] Test log file creation (if enabled)

**File:** `tests/Unit/Services/LoggerTest.php`

#### Cache Tests
- [ ] Test cache set/get operations
- [ ] Test cache expiration
- [ ] Test cache deletion
- [ ] Test cache key collision prevention

**File:** `tests/Unit/Services/CacheTest.php`

#### Options Tests
- [ ] Test option get/set/delete
- [ ] Test default values
- [ ] Test option serialization
- [ ] Test option autoload

**File:** `tests/Unit/Services/OptionsTest.php`

#### Security Services Tests
- [ ] Test nonce generation
- [ ] Test nonce verification
- [ ] Test capability checks
- [ ] Test input sanitization

**File:** `tests/Unit/Services/SecurityTest.php`

### Booking System Tests

#### Slots Tests
- [ ] Test slot creation
- [ ] Test slot capacity calculation
- [ ] Test slot overlap detection
- [ ] Test slot buffer conflict
- [ ] Test slot deletion

**File:** `tests/Unit/Booking/SlotsTest.php`

#### Availability Tests
- [ ] Test single date availability
- [ ] Test date range availability
- [ ] Test recurring availability
- [ ] Test capacity-based availability

**File:** `tests/Unit/Booking/AvailabilityTest.php`

#### Cart Tests
- [ ] Test add to cart
- [ ] Test remove from cart
- [ ] Test update quantity
- [ ] Test cart persistence

**File:** `tests/Unit/Booking/CartTest.php`

### Gift Voucher Tests

#### VoucherManager Tests
- [ ] Test voucher creation
- [ ] Test voucher code generation
- [ ] Test voucher redemption
- [ ] Test voucher expiration

**File:** `tests/Unit/Gift/VoucherManagerTest.php`

---

## 3. Integration Testing

### REST API Integration Tests

#### Availability Controller Tests
- [ ] Test GET /availability/{id}
- [ ] Test date range filtering
- [ ] Test invalid experience ID
- [ ] Test authentication

**File:** `tests/Integration/Api/AvailabilityControllerTest.php`

#### Checkout Controller Tests
- [ ] Test POST /checkout/cart-set
- [ ] Test POST /checkout/process
- [ ] Test nonce verification
- [ ] Test order creation

**File:** `tests/Integration/Api/CheckoutControllerTest.php`

### Database Integration Tests

#### Repository Tests
- [ ] Test ExperienceRepository
- [ ] Test SlotRepository
- [ ] Test ReservationRepository
- [ ] Test data persistence

**File:** `tests/Integration/Database/RepositoryTest.php`

### WooCommerce Integration Tests

#### Product Integration Tests
- [ ] Test product creation
- [ ] Test product update
- [ ] Test cart integration
- [ ] Test checkout integration

**File:** `tests/Integration/WooCommerce/IntegrationTest.php`

---

## 4. QA Test Suite

### Complete QA Test Suite
- [ ] Core services verification
- [ ] Booking system verification
- [ ] Gift voucher verification
- [ ] REST API verification
- [ ] Shortcodes verification
- [ ] Integrations verification

**File:** `tests/QA/CompleteQATestSuite.php`

### Hook Registration Tests
- [ ] Test all hooks registered
- [ ] Test hook priorities
- [ ] Test no duplicate hooks
- [ ] Test hook removal on deactivation

**File:** `tests/QA/HookRegistrationTest.php`

---

## 5. Test Configuration

### PHPUnit Configuration

**File:** `phpunit.xml.dist`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>./tests/Integration</directory>
        </testsuite>
        <testsuite name="QA">
            <directory>./tests/QA</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist>
            <directory suffix=".php">./src</directory>
        </whitelist>
    </filter>
</phpunit>
```

### Test Bootstrap

**File:** `tests/bootstrap.php`

```php
<?php
// Load WordPress test environment
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WordPress
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Load plugin
require_once dirname(__DIR__) . '/fp-experiences.php';
```

---

## 6. Test Coverage Goals

### Coverage Targets
- **Unit Tests:** 80%+ coverage
- **Integration Tests:** 60%+ coverage
- **Critical Paths:** 100% coverage
- **Overall:** 70%+ coverage

### Critical Paths (100% Coverage Required)
- Booking flow (cart → checkout → order)
- Voucher redemption
- REST API endpoints
- Security functions
- Database operations

---

## 7. Continuous Integration

### GitHub Actions Workflow

**File:** `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [8.0, 8.1, 8.2, 8.3]
        wp-version: ['6.2', '6.3', '6.4', 'latest']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: vendor/bin/phpunit
      
      - name: Generate coverage
        run: vendor/bin/phpunit --coverage-clover=coverage.xml
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

## 8. Test Execution

### Running Tests

#### All Tests
```bash
vendor/bin/phpunit
```

#### Specific Test Suite
```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
vendor/bin/phpunit --testsuite QA
```

#### Specific Test File
```bash
vendor/bin/phpunit tests/Unit/Services/LoggerTest.php
```

#### With Coverage
```bash
vendor/bin/phpunit --coverage-html coverage/
```

#### QA Test Suite (Standalone)
```bash
php tests/QA/CompleteQATestSuite.php
```

Or via web:
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/CompleteQATestSuite.php?run_qa=1
```

---

## 9. Mocking Strategy

### WordPress Functions
- Use BrainMonkey for WordPress function mocking
- Mock `wp_remote_*` for HTTP requests
- Mock `get_option()` for options
- Mock `current_user_can()` for capabilities

### Database
- Use in-memory database for unit tests
- Use test database for integration tests
- Clean up after each test

### External APIs
- Mock Google Calendar API
- Mock Brevo API
- Mock WooCommerce functions

---

## 10. Test Data Management

### Fixtures
- Create test experiences
- Create test slots
- Create test reservations
- Create test vouchers

### Test Database
- Use separate test database
- Reset database between test runs
- Use transactions where possible

### Cleanup
- Clean up test data after tests
- Remove test posts
- Remove test options
- Remove test transients

---

## 11. Performance Testing

### Load Testing
- [ ] Test with 100+ experiences
- [ ] Test with 1000+ slots
- [ ] Test with 100+ concurrent requests
- [ ] Measure response times

### Stress Testing
- [ ] Test under high load
- [ ] Test memory usage
- [ ] Test database performance
- [ ] Identify bottlenecks

---

## 12. Test Maintenance

### Regular Updates
- [ ] Update tests when code changes
- [ ] Add tests for new features
- [ ] Fix broken tests promptly
- [ ] Review test coverage regularly

### Test Documentation
- [ ] Document test structure
- [ ] Document test execution
- [ ] Document test data requirements
- [ ] Document known issues

---

## 13. Test Reporting

### Test Results
- [ ] Generate HTML reports
- [ ] Generate coverage reports
- [ ] Track test metrics
- [ ] Share results with team

### Metrics to Track
- Test execution time
- Test pass/fail rate
- Code coverage percentage
- Test maintenance effort

---

## 14. Best Practices

### Writing Tests
- Write tests before code (TDD) when possible
- Test one thing per test
- Use descriptive test names
- Keep tests independent
- Clean up after tests

### Test Organization
- Group related tests
- Use test fixtures
- Reuse test helpers
- Document complex tests

### Test Performance
- Keep tests fast
- Use mocks for slow operations
- Parallelize when possible
- Cache test data

---

## Implementation Priority

### Phase 1: Core Services (High Priority)
1. Logger tests
2. Cache tests
3. Options tests
4. Security tests

### Phase 2: Booking System (High Priority)
1. Slots tests
2. Availability tests
3. Cart tests
4. Checkout tests

### Phase 3: Integrations (Medium Priority)
1. REST API tests
2. WooCommerce tests
3. Database tests

### Phase 4: Advanced (Low Priority)
1. E2E tests
2. Performance tests
3. Load tests

---

## Notes

- Start with critical path tests
- Add tests incrementally
- Maintain test suite
- Review coverage regularly
- Fix failing tests immediately








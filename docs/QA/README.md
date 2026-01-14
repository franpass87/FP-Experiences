# FP Experiences - Quality Assurance Documentation

Complete QA documentation and test suites for FP Experiences plugin.

## Overview

This directory contains comprehensive Quality Assurance documentation, test suites, and checklists for the FP Experiences plugin.

## Documentation Structure

### Test Checklists
- **[Manual Test Checklist](MANUAL-TEST-CHECKLIST.md)** - Comprehensive manual testing checklist for all modules
- **[Security Test Checklist](SECURITY-TEST-CHECKLIST.md)** - Security testing procedures and checklist
- **[Performance Test Checklist](PERFORMANCE-TEST-CHECKLIST.md)** - Performance testing procedures and checklist
- **[Final Release Checklist](FINAL-RELEASE-CHECKLIST.md)** - Pre-release verification checklist

### Testing Plans
- **[Automated Testing Plan](AUTOMATED-TESTING-PLAN.md)** - Strategy and architecture for automated testing

## Test Suites

### Complete QA Test Suite
Location: `tests/QA/CompleteQATestSuite.php`

Comprehensive automated test suite covering:
- Core Services
- Booking System
- Gift Vouchers
- REST API
- Shortcodes
- Integrations
- Admin UI
- Database
- Security
- Performance
- Hook Registration

**Run via CLI:**
```bash
php tests/QA/CompleteQATestSuite.php
```

**Run via Web:**
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/CompleteQATestSuite.php?run_qa=1
```

### Hook Registration Test
Location: `tests/QA/HookRegistrationTest.php`

Tests WordPress hook registration:
- Critical hooks registered
- No duplicate hooks
- Hook priorities correct
- Hook contexts correct

**Run via CLI:**
```bash
php tests/QA/HookRegistrationTest.php
```

**Run via Web:**
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/HookRegistrationTest.php?run_hook_test=1
```

### Database Integrity Test
Location: `tests/QA/DatabaseIntegrityTest.php`

Tests database schema and data integrity:
- Table structure validation
- Index verification
- Data integrity checks
- Orphan record detection

**Run via CLI:**
```bash
php tests/QA/DatabaseIntegrityTest.php
```

**Run via Web:**
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/DatabaseIntegrityTest.php?run_db_test=1
```

### Frontend QA Test
Location: `tests/QA/FrontendQATest.php`

Tests frontend rendering and shortcodes:
- Shortcode registration
- Shortcode rendering
- Asset loading
- HTML structure validation

**Run via CLI:**
```bash
php tests/QA/FrontendQATest.php
```

**Run via Web:**
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/FrontendQATest.php?run_frontend_test=1
```

### Run All QA Tests
Location: `tests/QA/run-all-qa-tests.php`

Runs all QA test suites in sequence and generates comprehensive report.

**Run via CLI:**
```bash
php tests/QA/run-all-qa-tests.php
```

**Run via Web:**
```
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/run-all-qa-tests.php?run_all_qa=1
```

## Quick Start

### Running QA Tests

1. **Complete QA Suite:**
   ```bash
   cd wp-content/plugins/FP-Experiences
   php tests/QA/CompleteQATestSuite.php
   ```

2. **Hook Registration Test:**
   ```bash
   php tests/QA/HookRegistrationTest.php
   ```

3. **PHPUnit Tests:**
   ```bash
   vendor/bin/phpunit
   ```

### Manual Testing

1. Review [Manual Test Checklist](MANUAL-TEST-CHECKLIST.md)
2. Follow checklist for each module
3. Document results
4. Report issues

### Security Testing

1. Review [Security Test Checklist](SECURITY-TEST-CHECKLIST.md)
2. Test all security measures
3. Verify no vulnerabilities
4. Document findings

### Performance Testing

1. Review [Performance Test Checklist](PERFORMANCE-TEST-CHECKLIST.md)
2. Measure performance metrics
3. Identify bottlenecks
4. Optimize as needed

## Test Coverage Goals

- **Unit Tests:** 80%+ coverage
- **Integration Tests:** 60%+ coverage
- **Critical Paths:** 100% coverage
- **Overall:** 70%+ coverage

## Test Execution Workflow

### Before Development
1. Review existing tests
2. Understand test structure
3. Set up test environment

### During Development
1. Write tests for new features
2. Run tests frequently
3. Fix failing tests
4. Maintain test coverage

### Before Release
1. Run complete QA suite
2. Complete manual testing checklist
3. Complete security testing
4. Complete performance testing
5. Complete final release checklist

## Test Results

### Automated Test Results
Test results are saved as JSON files in the `tests/QA/` directory:
- `qa-report-YYYY-MM-DD-HHMMSS.json`

### Manual Test Results
Document manual test results in:
- Test execution logs
- Issue tracking system
- Release notes

## Continuous Integration

Tests are automatically run on:
- Push to main/develop branches
- Pull requests
- Scheduled nightly builds

See [Automated Testing Plan](AUTOMATED-TESTING-PLAN.md) for CI configuration.

## Test Maintenance

### Regular Tasks
- Update tests when code changes
- Add tests for new features
- Fix broken tests promptly
- Review test coverage regularly
- Update documentation

### Test Review
- Review test structure quarterly
- Identify gaps in coverage
- Optimize slow tests
- Remove obsolete tests

## Reporting Issues

### Test Failures
1. Document failure details
2. Identify root cause
3. Fix issue or update test
4. Verify fix with test

### Coverage Gaps
1. Identify uncovered code
2. Prioritize critical paths
3. Add tests incrementally
4. Track coverage improvements

## Resources

### External Tools
- **PHPUnit** - Unit testing framework
- **Query Monitor** - Database query analysis
- **Lighthouse** - Performance auditing
- **OWASP ZAP** - Security scanning

### Additional Checklists
- **[Multisite QA](MULTISITE-QA.md)** - Multisite testing checklist (if applicable)
- **[Multilanguage QA](MULTILANGUAGE-QA.md)** - Multilanguage testing checklist

### Documentation
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

## Support

For questions or issues with testing:
1. Review documentation
2. Check existing tests
3. Consult team
4. Update documentation if needed

---

**Last Updated:** 2025-01-XX
**Version:** 1.1.5


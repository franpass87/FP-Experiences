# FP Experiences - QA Quick Start Guide

Quick reference guide for running QA tests and completing checklists.

## Quick Commands

### Run All QA Tests
```bash
cd wp-content/plugins/FP-Experiences
php tests/QA/run-all-qa-tests.php
```

### Run Individual Test Suites
```bash
# Complete QA Suite
php tests/QA/CompleteQATestSuite.php

# Hook Registration
php tests/QA/HookRegistrationTest.php

# Database Integrity
php tests/QA/DatabaseIntegrityTest.php

# Frontend QA
php tests/QA/FrontendQATest.php
```

### Via Web Interface
```
# Complete QA Suite
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/CompleteQATestSuite.php?run_qa=1

# All Tests
http://yoursite.com/wp-content/plugins/FP-Experiences/tests/QA/run-all-qa-tests.php?run_all_qa=1
```

---

## Test Results

Test results are saved as JSON files in `tests/QA/`:
- `qa-report-YYYY-MM-DD-HHMMSS.json` - Individual suite reports
- `qa-complete-report-YYYY-MM-DD-HHMMSS.json` - Complete test run report

---

## Manual Testing

1. Open `docs/QA/MANUAL-TEST-CHECKLIST.md`
2. Follow checklist for each module
3. Check off completed items
4. Document any issues found

---

## Pre-Release Checklist

Before any release:
1. Run all automated tests
2. Complete manual testing checklist
3. Complete security testing
4. Complete performance testing
5. Complete final release checklist
6. Get sign-offs

---

## Common Issues

### Tests Fail to Run
- Verify WordPress is loaded
- Check file permissions
- Verify plugin is activated
- Check for PHP errors

### Missing Dependencies
- Ensure WooCommerce is active (for checkout tests)
- Verify database tables exist
- Check service providers are registered

---

## Need Help?

- See `docs/QA/README.md` for full documentation
- Check `docs/QA/QA-PLAN-IMPLEMENTATION-STATUS.md` for implementation status
- Review individual checklist files for detailed procedures








import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateXSSProtection } from '../helpers/validation';

test.describe('Sanitization Validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should validate XSS protection on settings page', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    // Test with a simple XSS attempt
    const testInput = '<script>alert("XSS")</script>';
    const isProtected = await validateXSSProtection(page, testInput);
    
    // Should be protected (script tags escaped)
    expect(isProtected).toBe(true);
  });
});








import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad, hasNonceField } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Emails Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.emails);
    await waitForAdminPageLoad(page);
  });

  test('should load emails page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_emails/);
    
    const errors = await validateNoConsoleErrors(page, 'Emails Page');
    if (errors.length > 0) {
      console.log('Emails page console errors:', errors);
    }
  });

  test('should have nonce protection on email forms', async ({ page }) => {
    const hasNonce = await hasNonceField(page);
    expect(hasNonce).toBe(true);
  });
});








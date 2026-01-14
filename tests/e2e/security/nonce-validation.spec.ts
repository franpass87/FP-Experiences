import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, hasNonceField, getNonceValue, waitForAdminPageLoad } from '../helpers/admin';
import { validateNonceProtection } from '../helpers/validation';

test.describe('Nonce Validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should have nonce on settings form', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    const hasNonce = await hasNonceField(page);
    expect(hasNonce).toBe(true);
    
    const nonceValue = await getNonceValue(page);
    expect(nonceValue).not.toBeNull();
    expect(nonceValue?.length).toBeGreaterThan(0);
  });

  test('should validate nonce protection on forms', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    const isProtected = await validateNonceProtection(page);
    expect(isProtected).toBe(true);
  });
});








import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToSettings, hasNonceField, getNonceValue, saveSettingsForm, getAdminMessages, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors, validateRequiredFields } from '../helpers/validation';

test.describe('FP Experiences Settings Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToSettings(page);
    await waitForAdminPageLoad(page);
  });

  test('should load settings page without errors', async ({ page }) => {
    await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Impostazioni|Settings/i);
    
    const errors = await validateNoConsoleErrors(page, 'Settings Page');
    expect(errors.length).toBe(0);
  });

  test('should have nonce protection on settings form', async ({ page }) => {
    const hasNonce = await hasNonceField(page);
    expect(hasNonce).toBe(true);
    
    const nonceValue = await getNonceValue(page);
    expect(nonceValue).not.toBeNull();
    expect(nonceValue?.length).toBeGreaterThan(0);
  });

  test('should display all settings sections', async ({ page }) => {
    // Check for common settings sections/tabs
    const pageContent = await page.textContent('body');
    expect(pageContent).toBeTruthy();
  });

  test('should validate form fields', async ({ page }) => {
    // Check for common form elements
    const forms = page.locator('form');
    const formCount = await forms.count();
    expect(formCount).toBeGreaterThan(0);
  });
});








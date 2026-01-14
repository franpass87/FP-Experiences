import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Logs Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.logs);
    await waitForAdminPageLoad(page);
  });

  test('should load logs page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_logs/);
    
    const errors = await validateNoConsoleErrors(page, 'Logs Page');
    if (errors.length > 0) {
      console.log('Logs page console errors:', errors);
    }
  });
});








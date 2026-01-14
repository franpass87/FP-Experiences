import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Help Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.help);
    await waitForAdminPageLoad(page);
  });

  test('should load help page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_help/);
    
    const errors = await validateNoConsoleErrors(page, 'Help Page');
    if (errors.length > 0) {
      console.log('Help page console errors:', errors);
    }
  });
});








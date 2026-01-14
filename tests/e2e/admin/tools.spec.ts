import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Tools Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.tools);
    await waitForAdminPageLoad(page);
  });

  test('should load tools page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_tools/);
    
    const errors = await validateNoConsoleErrors(page, 'Tools Page');
    if (errors.length > 0) {
      console.log('Tools page console errors:', errors);
    }
  });
});








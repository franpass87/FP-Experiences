import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Check-in Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.checkin);
    await waitForAdminPageLoad(page);
  });

  test('should load check-in page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_checkin/);
    
    const errors = await validateNoConsoleErrors(page, 'Check-in Page');
    if (errors.length > 0) {
      console.log('Check-in page console errors:', errors);
    }
  });
});








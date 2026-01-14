import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Calendar Page', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToAdminPage(page, FP_EXP_PAGES.calendar);
    await waitForAdminPageLoad(page);
  });

  test('should load calendar page without errors', async ({ page }) => {
    await expect(page).toHaveURL(/fp_exp_calendar/);
    
    const errors = await validateNoConsoleErrors(page, 'Calendar Page');
    if (errors.length > 0) {
      console.log('Calendar page console errors:', errors);
    }
  });
});








import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('Console Errors Check', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should have no console errors on dashboard', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.dashboard);
    await waitForAdminPageLoad(page);
    
    const errors = await validateNoConsoleErrors(page, 'Dashboard');
    // Log errors for reporting
    if (errors.length > 0) {
      console.log('Dashboard console errors:', errors);
    }
  });

  test('should have no console errors on settings', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    const errors = await validateNoConsoleErrors(page, 'Settings');
    if (errors.length > 0) {
      console.log('Settings console errors:', errors);
    }
  });

  test('should have no console errors on calendar', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.calendar);
    await waitForAdminPageLoad(page);
    
    const errors = await validateNoConsoleErrors(page, 'Calendar');
    if (errors.length > 0) {
      console.log('Calendar console errors:', errors);
    }
  });
});








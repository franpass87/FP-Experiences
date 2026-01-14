import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToDashboard, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
    await navigateToDashboard(page);
    await waitForAdminPageLoad(page);
  });

  test('should load dashboard without errors', async ({ page }) => {
    await expect(page.locator('h1, .wp-heading-inline')).toBeVisible();
    
    const errors = await validateNoConsoleErrors(page, 'Dashboard');
    expect(errors.length).toBe(0);
  });

  test('should display dashboard content', async ({ page }) => {
    // Use first() to avoid strict mode violation
    const mainContent = page.locator('#wpcontent').first();
    await expect(mainContent).toBeVisible();
  });
});


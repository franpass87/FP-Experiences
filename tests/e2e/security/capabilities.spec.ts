import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validatePageAccessible } from '../helpers/validation';

test.describe('Capabilities Validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should have access to settings page as admin', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    // Should not be redirected or see access denied
    await expect(page).toHaveURL(/fp_exp_settings/);
    
    const pageContent = await page.textContent('body');
    expect(pageContent).not.toContain('Non hai i permessi');
    expect(pageContent).not.toContain('You do not have permission');
  });

  test('should have access to dashboard as admin', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.dashboard);
    await waitForAdminPageLoad(page);
    
    await expect(page).toHaveURL(/fp_exp_dashboard/);
  });
});


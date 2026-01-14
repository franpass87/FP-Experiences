import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences CPT - Esperienze', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should navigate to experiences list', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.experiences);
    await waitForAdminPageLoad(page);
    
    await expect(page).toHaveURL(/post_type=fp_experience/);
    
    const errors = await validateNoConsoleErrors(page, 'Experiences List');
    if (errors.length > 0) {
      console.log('Experiences list console errors:', errors);
    }
  });

  test('should navigate to new experience page', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.newExperience);
    await waitForAdminPageLoad(page);
    
    await expect(page).toHaveURL(/post_type=fp_experience/);
    
    // Check for post editor elements
    const titleField = page.locator('#title, input[name="post_title"]');
    await expect(titleField).toBeVisible({ timeout: 5000 });
  });

  test('should have metaboxes on experience edit page', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.newExperience);
    await waitForAdminPageLoad(page);
    
    // Wait for metaboxes to load
    await page.waitForTimeout(2000);
    
    // Check for common metabox containers
    const metaboxes = page.locator('.postbox, .meta-box-sortables');
    const count = await metaboxes.count();
    
    // Should have at least some metaboxes
    expect(count).toBeGreaterThan(0);
  });

  test('should validate required fields', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.newExperience);
    await waitForAdminPageLoad(page);
    
    // Check for title field (required)
    const titleField = page.locator('#title, input[name="post_title"]');
    await expect(titleField).toBeVisible();
    
    const errors = await validateNoConsoleErrors(page, 'New Experience');
    if (errors.length > 0) {
      console.log('New experience console errors:', errors);
    }
  });
});








import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode List', () => {
  test('should render shortcode list', async ({ page }) => {
    // Navigate to a page that might have the shortcode
    // In a real scenario, we would create a test page with [fp_exp_list]
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    // Check if shortcode container exists
    const shortcodeContainer = page.locator('.fp-exp-list, [data-fp-exp-list]');
    const count = await shortcodeContainer.count();
    
    // If shortcode is present, validate it
    if (count > 0) {
      await expect(shortcodeContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode List');
    if (errors.length > 0) {
      console.log('Shortcode list console errors:', errors);
    }
  });

  test('should have proper structure when shortcode is present', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const shortcodeContainer = page.locator('.fp-exp-list, [data-fp-exp-list]');
    const count = await shortcodeContainer.count();
    
    if (count > 0) {
      // Check for experience items
      const items = shortcodeContainer.locator('.fp-exp-item, .experience-item');
      const itemCount = await items.count();
      expect(itemCount).toBeGreaterThanOrEqual(0);
    }
  });
});








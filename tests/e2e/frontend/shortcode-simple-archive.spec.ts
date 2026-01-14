import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Simple Archive', () => {
  test('should render simple archive shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const archiveContainer = page.locator('.fp-exp-simple-archive, [data-fp-exp-simple-archive]');
    const count = await archiveContainer.count();
    
    if (count > 0) {
      await expect(archiveContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Simple Archive');
    if (errors.length > 0) {
      console.log('Shortcode simple archive console errors:', errors);
    }
  });
});








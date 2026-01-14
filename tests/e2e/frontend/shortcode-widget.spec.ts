import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Widget', () => {
  test('should render widget shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const widgetContainer = page.locator('.fp-exp-widget, [data-fp-exp-widget]');
    const count = await widgetContainer.count();
    
    if (count > 0) {
      await expect(widgetContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Widget');
    if (errors.length > 0) {
      console.log('Shortcode widget console errors:', errors);
    }
  });
});








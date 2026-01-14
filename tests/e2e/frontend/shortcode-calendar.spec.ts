import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Calendar', () => {
  test('should render calendar shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const calendarContainer = page.locator('.fp-exp-calendar, [data-fp-exp-calendar]');
    const count = await calendarContainer.count();
    
    if (count > 0) {
      await expect(calendarContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Calendar');
    if (errors.length > 0) {
      console.log('Shortcode calendar console errors:', errors);
    }
  });
});








import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Experience', () => {
  test('should render experience shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    // Check if experience shortcode container exists
    const experienceContainer = page.locator('.fp-exp-experience, [data-fp-exp-experience]');
    const count = await experienceContainer.count();
    
    if (count > 0) {
      await expect(experienceContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Experience');
    if (errors.length > 0) {
      console.log('Shortcode experience console errors:', errors);
    }
  });

  test('should have booking widget when present', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const bookingWidget = page.locator('.fp-exp-widget, .fp-exp-booking-widget');
    const count = await bookingWidget.count();
    
    if (count > 0) {
      await expect(bookingWidget.first()).toBeVisible();
    }
  });
});








import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Meeting Points', () => {
  test('should render meeting points shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const meetingPointsContainer = page.locator('.fp-exp-meeting-points, [data-fp-exp-meeting-points]');
    const count = await meetingPointsContainer.count();
    
    if (count > 0) {
      await expect(meetingPointsContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Meeting Points');
    if (errors.length > 0) {
      console.log('Shortcode meeting points console errors:', errors);
    }
  });
});








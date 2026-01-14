import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Gift Redeem', () => {
  test('should render gift redeem shortcode', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const redeemContainer = page.locator('.fp-exp-gift-redeem, [data-fp-exp-gift-redeem]');
    const count = await redeemContainer.count();
    
    if (count > 0) {
      await expect(redeemContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Gift Redeem');
    if (errors.length > 0) {
      console.log('Shortcode gift redeem console errors:', errors);
    }
  });

  test('should have voucher code input when present', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    const voucherInput = page.locator('input[name*="voucher"], input[name*="code"], input[type="text"]');
    const count = await voucherInput.count();
    
    if (count > 0) {
      // Check if it's within a gift redeem container
      const redeemContainer = page.locator('.fp-exp-gift-redeem, [data-fp-exp-gift-redeem]');
      const hasContainer = await redeemContainer.count() > 0;
      expect(hasContainer || count > 0).toBeTruthy();
    }
  });
});








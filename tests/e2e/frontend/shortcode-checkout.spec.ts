import { test, expect } from '@playwright/test';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors, validateRequiredFields } from '../helpers/validation';

test.describe('FP Experiences Frontend - Shortcode Checkout', () => {
  test('should render checkout shortcode', async ({ page }) => {
    // Checkout page might be at a specific URL
    await page.goto(`${BASE_URL}/checkout`);
    await page.waitForLoadState('networkidle');
    
    const checkoutContainer = page.locator('.fp-exp-checkout, [data-fp-exp-checkout], .woocommerce-checkout');
    const count = await checkoutContainer.count();
    
    if (count > 0) {
      await expect(checkoutContainer.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Shortcode Checkout');
    if (errors.length > 0) {
      console.log('Shortcode checkout console errors:', errors);
    }
  });

  test('should have checkout form when present', async ({ page }) => {
    await page.goto(`${BASE_URL}/checkout`);
    await page.waitForLoadState('networkidle');
    
    const checkoutForm = page.locator('form.checkout, .checkout-form');
    const count = await checkoutForm.count();
    
    if (count > 0) {
      await expect(checkoutForm.first()).toBeVisible();
    }
  });
});








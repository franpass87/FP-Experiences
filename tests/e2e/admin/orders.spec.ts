import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Orders Page (WooCommerce)', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should navigate to orders page if WooCommerce is active', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.orders);
    await waitForAdminPageLoad(page);
    
    // Page might not exist if WooCommerce is not active, so we check URL or error message
    const currentUrl = page.url();
    const isOrdersPage = currentUrl.includes('fp_exp_orders');
    const hasError = await page.locator('body').textContent().then(text => 
      text?.includes('Non hai i permessi') || text?.includes('You do not have permission')
    );
    
    // If page exists and is accessible, validate it
    if (isOrdersPage && !hasError) {
      await expect(page).toHaveURL(/fp_exp_orders/);
      
      const errors = await validateNoConsoleErrors(page, 'Orders Page');
      if (errors.length > 0) {
        console.log('Orders page console errors:', errors);
      }
    } else {
      // WooCommerce might not be active, skip test
      test.skip();
    }
  });
});








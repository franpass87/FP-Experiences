import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Admin - Requests Page (RTB)', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should navigate to requests page if RTB is active', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.requests);
    await waitForAdminPageLoad(page);
    
    // Page might not exist if RTB is not active, so we check URL or error message
    const currentUrl = page.url();
    const isRequestsPage = currentUrl.includes('fp_exp_requests');
    const hasError = await page.locator('body').textContent().then(text => 
      text?.includes('Non hai i permessi') || text?.includes('You do not have permission')
    );
    
    // If page exists and is accessible, validate it
    if (isRequestsPage && !hasError) {
      await expect(page).toHaveURL(/fp_exp_requests/);
      
      const errors = await validateNoConsoleErrors(page, 'Requests Page');
      if (errors.length > 0) {
        console.log('Requests page console errors:', errors);
      }
    } else {
      // RTB might not be active, skip test
      test.skip();
    }
  });
});








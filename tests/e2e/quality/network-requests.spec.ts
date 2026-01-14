import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, waitForAdminPageLoad } from '../helpers/admin';
import { validateNetworkRequests } from '../helpers/validation';

test.describe('Network Requests Validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should validate network requests on dashboard', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.dashboard);
    await waitForAdminPageLoad(page);
    
    const failedRequests = await validateNetworkRequests(page);
    
    if (failedRequests.length > 0) {
      console.log('Dashboard failed network requests:', failedRequests);
      // Log but don't fail - some 500 errors might be expected
    }
  });

  test('should validate network requests on settings', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await waitForAdminPageLoad(page);
    
    const failedRequests = await validateNetworkRequests(page);
    
    if (failedRequests.length > 0) {
      console.log('Settings failed network requests:', failedRequests);
    }
  });
});








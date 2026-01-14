import { test, expect } from '@playwright/test';
import { loginToWordPress, isLoggedIn, ADMIN_URL } from '../helpers/auth';
import { navigateToDashboard, isMenuItemVisible } from '../helpers/admin';

test.describe('WordPress Admin Login', () => {
  test('should login to WordPress admin successfully', async ({ page }) => {
    await loginToWordPress(page);
    
    // Verify we're on admin dashboard
    await expect(page).toHaveURL(/wp-admin/);
    
    // Verify admin bar is visible
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should see FP Experiences menu after login', async ({ page }) => {
    await loginToWordPress(page);
    
    // Check if FP Experiences menu is visible
    const hasMenu = await isMenuItemVisible(page, 'FP Experiences');
    expect(hasMenu).toBe(true);
  });

  test('should navigate to FP Experiences dashboard', async ({ page }) => {
    await loginToWordPress(page);
    await navigateToDashboard(page);
    
    // Verify we're on the dashboard
    await expect(page).toHaveURL(/fp_exp_dashboard/);
  });

  test('should maintain login session', async ({ page }) => {
    await loginToWordPress(page);
    
    // Navigate away and back
    await page.goto(ADMIN_URL);
    
    // Check if still logged in
    const loggedIn = await isLoggedIn(page);
    expect(loggedIn).toBe(true);
  });
});








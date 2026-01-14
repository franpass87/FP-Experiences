import { Page, expect } from '@playwright/test';

/**
 * WordPress admin credentials
 */
export const WP_CREDENTIALS = {
  username: 'FranPass87',
  password: '00Antonelli00',
};

/**
 * Base URL for the WordPress site
 */
export const BASE_URL = 'http://fp-development.local';

/**
 * Admin URL
 */
export const ADMIN_URL = `${BASE_URL}/wp-admin`;

/**
 * Login to WordPress admin
 * @param page Playwright page instance
 * @param username WordPress username (default: from WP_CREDENTIALS)
 * @param password WordPress password (default: from WP_CREDENTIALS)
 */
export async function loginToWordPress(
  page: Page,
  username: string = WP_CREDENTIALS.username,
  password: string = WP_CREDENTIALS.password
): Promise<void> {
  // Navigate directly to login page
  await page.goto(`${BASE_URL}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  
  // Wait for login form to be visible
  await page.waitForSelector('#loginform, #user_login', { timeout: 15000, state: 'visible' });
  
  // Fill login form - use more specific selectors
  const usernameField = page.locator('#user_login').first();
  const passwordField = page.locator('#user_pass').first();
  
  await usernameField.waitFor({ state: 'visible', timeout: 5000 });
  await passwordField.waitFor({ state: 'visible', timeout: 5000 });
  
  await usernameField.fill(username, { timeout: 5000 });
  await passwordField.fill(password, { timeout: 5000 });
  
  // Submit form - wait for navigation
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }),
    page.locator('#wp-submit').first().click()
  ]).catch(async () => {
    // If navigation doesn't happen, try clicking again
    await page.locator('#wp-submit').first().click();
    await page.waitForLoadState('domcontentloaded', { timeout: 15000 });
  });
  
  // Verify we're logged in - check multiple indicators
  try {
    // Wait for admin elements with multiple fallbacks
    await Promise.race([
      page.waitForSelector('#wpadminbar', { timeout: 10000, state: 'visible' }),
      page.waitForSelector('#wpcontent', { timeout: 10000, state: 'visible' }),
      page.waitForSelector('.wp-heading-inline', { timeout: 10000, state: 'visible' }),
      page.waitForURL(/wp-admin/, { timeout: 10000 })
    ]);
  } catch (error) {
    // If all checks fail, verify URL is admin
    const currentUrl = page.url();
    if (!currentUrl.includes('wp-admin') && !currentUrl.includes('wp-login')) {
      throw new Error(`Login failed: Unexpected URL after login: ${currentUrl}`);
    }
  }
}

/**
 * Check if user is logged in
 * @param page Playwright page instance
 * @returns true if logged in, false otherwise
 */
export async function isLoggedIn(page: Page): Promise<boolean> {
  try {
    await page.goto(ADMIN_URL);
    // Check for admin bar or dashboard elements
    const adminBar = page.locator('#wpadminbar');
    const dashboard = page.locator('#dashboard-widgets, .wp-heading-inline');
    
    await Promise.race([
      adminBar.waitFor({ timeout: 3000 }),
      dashboard.first().waitFor({ timeout: 3000 })
    ]);
    
    return true;
  } catch {
    return false;
  }
}

/**
 * Logout from WordPress
 * @param page Playwright page instance
 */
export async function logoutFromWordPress(page: Page): Promise<void> {
  // Try to find logout link in admin bar
  const logoutLink = page.locator('#wp-admin-bar-logout a, a[href*="action=logout"]').first();
  
  if (await logoutLink.isVisible({ timeout: 2000 }).catch(() => false)) {
    await logoutLink.click();
    await page.waitForURL(/wp-login\.php/, { timeout: 10000 });
  } else {
    // Direct logout URL
    await page.goto(`${ADMIN_URL}/admin.php?action=logout`);
    await page.waitForURL(/wp-login\.php/, { timeout: 10000 });
  }
}


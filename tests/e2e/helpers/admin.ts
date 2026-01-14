import { Page, expect } from '@playwright/test';
import { ADMIN_URL } from './auth';

/**
 * FP Experiences admin menu slugs
 */
export const FP_EXP_PAGES = {
  dashboard: 'fp_exp_dashboard',
  experiences: 'edit.php?post_type=fp_experience',
  newExperience: 'post-new.php?post_type=fp_experience',
  giftVouchers: 'edit.php?post_type=fp_exp_gift_voucher',
  newVoucher: 'post-new.php?post_type=fp_exp_gift_voucher',
  importer: 'fp_exp_importer',
  meetingPoints: 'edit.php?post_type=fp_meeting_point',
  calendar: 'fp_exp_calendar',
  requests: 'fp_exp_requests',
  checkin: 'fp_exp_checkin',
  orders: 'fp_exp_orders',
  settings: 'fp_exp_settings',
  emails: 'fp_exp_emails',
  tools: 'fp_exp_tools',
  logs: 'fp_exp_logs',
  help: 'fp_exp_help',
  createPage: 'fp_exp_create_page',
} as const;

/**
 * Navigate to an admin page
 * @param page Playwright page instance
 * @param pageSlug Page slug or URL
 */
export async function navigateToAdminPage(page: Page, pageSlug: string): Promise<void> {
  const url = pageSlug.startsWith('http') 
    ? pageSlug 
    : pageSlug.includes('?') 
      ? `${ADMIN_URL}/${pageSlug}`
      : `${ADMIN_URL}/admin.php?page=${pageSlug}`;
  
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Navigate to FP Experiences dashboard
 * @param page Playwright page instance
 */
export async function navigateToDashboard(page: Page): Promise<void> {
  await navigateToAdminPage(page, FP_EXP_PAGES.dashboard);
  await expect(page.locator('h1, .wp-heading-inline')).toContainText(/FP Experiences|Dashboard/i, { timeout: 10000 });
}

/**
 * Navigate to FP Experiences settings
 * @param page Playwright page instance
 */
export async function navigateToSettings(page: Page): Promise<void> {
  await navigateToAdminPage(page, FP_EXP_PAGES.settings);
  await expect(page.locator('h1, .wp-heading-inline')).toContainText(/Impostazioni|Settings/i, { timeout: 10000 });
}

/**
 * Check if a menu item is visible in admin sidebar
 * @param page Playwright page instance
 * @param menuText Text of the menu item to check
 */
export async function isMenuItemVisible(page: Page, menuText: string): Promise<boolean> {
  try {
    const menuItem = page.locator(`#adminmenu a:has-text("${menuText}")`).first();
    return await menuItem.isVisible({ timeout: 2000 });
  } catch {
    return false;
  }
}

/**
 * Click on a menu item in admin sidebar
 * @param page Playwright page instance
 * @param menuText Text of the menu item to click
 */
export async function clickMenuItem(page: Page, menuText: string): Promise<void> {
  const menuItem = page.locator(`#adminmenu a:has-text("${menuText}")`).first();
  await menuItem.click();
  await page.waitForLoadState('networkidle');
}

/**
 * Get all console errors from the page
 * @param page Playwright page instance
 * @returns Array of console error messages
 */
export async function getConsoleErrors(page: Page): Promise<string[]> {
  const errors: string[] = [];
  
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(msg.text());
    }
  });
  
  return errors;
}

/**
 * Wait for admin page to be fully loaded
 * @param page Playwright page instance
 */
export async function waitForAdminPageLoad(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded', { timeout: 15000 });
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  
  // Wait for WordPress admin elements - use first() to avoid strict mode violation
  try {
    await page.waitForSelector('#wpcontent', { timeout: 10000, state: 'visible' });
  } catch {
    // Fallback: just check if page is loaded
    await page.waitForLoadState('domcontentloaded', { timeout: 5000 });
  }
}

/**
 * Check if nonce field exists in a form
 * @param page Playwright page instance
 * @param nonceName Name of the nonce field (default: '_wpnonce')
 */
export async function hasNonceField(page: Page, nonceName: string = '_wpnonce'): Promise<boolean> {
  try {
    // Check for standard WordPress nonce field
    const nonceField = page.locator(`input[name="${nonceName}"], input[name*="nonce"], input[type="hidden"][name*="nonce"]`).first();
    const isVisible = await nonceField.isVisible({ timeout: 3000 });
    if (isVisible) return true;
    
    // Also check if settings_fields() was called (WordPress adds nonce automatically)
    const form = page.locator('form[method="post"]').first();
    if (await form.isVisible({ timeout: 2000 }).catch(() => false)) {
      // WordPress settings API forms have nonce via settings_fields()
      const formHtml = await form.innerHTML().catch(() => '');
      if (formHtml.includes('nonce') || formHtml.includes('_wpnonce')) {
        return true;
      }
    }
    
    return false;
  } catch {
    return false;
  }
}

/**
 * Get nonce value from a form
 * @param page Playwright page instance
 * @param nonceName Name of the nonce field (default: '_wpnonce')
 */
export async function getNonceValue(page: Page, nonceName: string = '_wpnonce'): Promise<string | null> {
  try {
    const nonceField = page.locator(`input[name="${nonceName}"]`).first();
    return await nonceField.getAttribute('value');
  } catch {
    return null;
  }
}

/**
 * Save settings form
 * @param page Playwright page instance
 * @param submitButtonText Text of the submit button (default: 'Salva')
 */
export async function saveSettingsForm(page: Page, submitButtonText: string = 'Salva'): Promise<void> {
  const submitButton = page.locator(`button[type="submit"], input[type="submit"], button:has-text("${submitButtonText}")`).first();
  await submitButton.click();
  await page.waitForLoadState('networkidle');
}

/**
 * Check for success/error messages
 * @param page Playwright page instance
 * @returns Object with success and error messages
 */
export async function getAdminMessages(page: Page): Promise<{ success: string[]; errors: string[] }> {
  const success: string[] = [];
  const errors: string[] = [];
  
  // WordPress success notices
  const successNotices = page.locator('.notice-success, .updated, .notice.notice-success');
  const count = await successNotices.count();
  for (let i = 0; i < count; i++) {
    const text = await successNotices.nth(i).textContent();
    if (text) success.push(text.trim());
  }
  
  // WordPress error notices
  const errorNotices = page.locator('.notice-error, .error, .notice.notice-error');
  const errorCount = await errorNotices.count();
  for (let i = 0; i < errorCount; i++) {
    const text = await errorNotices.nth(i).textContent();
    if (text) errors.push(text.trim());
  }
  
  return { success, errors };
}


import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { BASE_URL } from '../helpers/auth';
import { validateNoConsoleErrors } from '../helpers/validation';

test.describe('FP Experiences Multilanguage', () => {
  test('should detect language switcher if available', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    // Look for common language switcher elements
    const langSwitcher = page.locator('.lang-switcher, .language-switcher, [data-lang], .wpml-ls');
    const count = await langSwitcher.count();
    
    if (count > 0) {
      await expect(langSwitcher.first()).toBeVisible();
    }
    
    const errors = await validateNoConsoleErrors(page, 'Language Switcher');
    if (errors.length > 0) {
      console.log('Language switcher console errors:', errors);
    }
  });

  test('should have proper language attributes', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    // Check for lang attribute on html element
    const htmlLang = await page.locator('html').getAttribute('lang');
    expect(htmlLang).toBeTruthy();
  });

  test('should test language switching if available', async ({ page }) => {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    
    // Look for language links
    const langLinks = page.locator('a[href*="lang="], a[data-lang], .wpml-ls-item a');
    const count = await langLinks.count();
    
    if (count > 0) {
      // Language switcher is available
      const firstLang = langLinks.first();
      const href = await firstLang.getAttribute('href');
      expect(href).toBeTruthy();
    }
  });
});








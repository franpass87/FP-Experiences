import { Page, expect } from '@playwright/test';

/**
 * Validate that a page has no console errors
 * @param page Playwright page instance
 * @param pageName Name of the page for error reporting
 */
export async function validateNoConsoleErrors(page: Page, pageName: string): Promise<string[]> {
  const errors: string[] = [];
  
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      errors.push(`[${pageName}] ${msg.text()}`);
    }
  });
  
  return errors;
}

/**
 * Validate that all images on the page are loaded
 * @param page Playwright page instance
 */
export async function validateImagesLoaded(page: Page): Promise<void> {
  const images = page.locator('img');
  const count = await images.count();
  
  for (let i = 0; i < count; i++) {
    const img = images.nth(i);
    await expect(img).toHaveAttribute('src', /.+/, { timeout: 5000 });
  }
}

/**
 * Validate that a form has proper nonce protection
 * @param page Playwright page instance
 * @param formSelector CSS selector for the form
 * @param nonceName Name of the nonce field
 */
export async function validateNonceProtection(
  page: Page,
  formSelector: string = 'form',
  nonceName: string = '_wpnonce'
): Promise<boolean> {
  const form = page.locator(formSelector).first();
  const nonceField = form.locator(`input[name="${nonceName}"]`);
  
  const hasNonce = await nonceField.isVisible();
  if (!hasNonce) {
    return false;
  }
  
  const nonceValue = await nonceField.getAttribute('value');
  return nonceValue !== null && nonceValue.length > 0;
}

/**
 * Validate that output is properly escaped (no XSS vulnerabilities)
 * @param page Playwright page instance
 * @param testInput Input string to test
 */
export async function validateXSSProtection(page: Page, testInput: string): Promise<boolean> {
  // Check if script tags are escaped in the HTML
  const html = await page.content();
  
  // If the test input contains script tags, they should be escaped
  if (testInput.includes('<script>')) {
    // Check if script tags are present as text (escaped) rather than executable
    const scriptMatches = html.match(/<script[^>]*>/gi);
    if (scriptMatches) {
      // Check if any script tags match our test input (should be escaped)
      for (const match of scriptMatches) {
        if (match.includes(testInput)) {
          return false; // Script tag found in HTML, potential XSS
        }
      }
    }
  }
  
  return true;
}

/**
 * Validate that a page is accessible (no 404 or 500 errors)
 * @param page Playwright page instance
 * @param url URL to validate
 */
export async function validatePageAccessible(page: Page, url: string): Promise<boolean> {
  const response = await page.goto(url, { waitUntil: 'networkidle' });
  if (!response) return false;
  
  const status = response.status();
  return status >= 200 && status < 400;
}

/**
 * Validate that required form fields are present
 * @param page Playwright page instance
 * @param fieldNames Array of field names to check
 */
export async function validateRequiredFields(
  page: Page,
  fieldNames: string[]
): Promise<{ missing: string[]; found: string[] }> {
  const missing: string[] = [];
  const found: string[] = [];
  
  for (const fieldName of fieldNames) {
    const field = page.locator(`input[name="${fieldName}"], textarea[name="${fieldName}"], select[name="${fieldName}"]`).first();
    const exists = await field.isVisible({ timeout: 2000 }).catch(() => false);
    
    if (exists) {
      found.push(fieldName);
    } else {
      missing.push(fieldName);
    }
  }
  
  return { missing, found };
}

/**
 * Validate network requests for errors
 * @param page Playwright page instance
 * @returns Array of failed requests
 */
export async function validateNetworkRequests(page: Page): Promise<Array<{ url: string; status: number }>> {
  const failedRequests: Array<{ url: string; status: number }> = [];
  
  page.on('response', (response) => {
    const status = response.status();
    if (status >= 400) {
      failedRequests.push({
        url: response.url(),
        status: status,
      });
    }
  });
  
  return failedRequests;
}

/**
 * Validate that a page has proper meta tags
 * @param page Playwright page instance
 * @param requiredTags Array of required meta tag names
 */
export async function validateMetaTags(
  page: Page,
  requiredTags: string[] = []
): Promise<{ missing: string[]; found: string[] }> {
  const missing: string[] = [];
  const found: string[] = [];
  
  for (const tagName of requiredTags) {
    const metaTag = page.locator(`meta[name="${tagName}"], meta[property="${tagName}"]`).first();
    const exists = await metaTag.isVisible({ timeout: 1000 }).catch(() => false);
    
    if (exists) {
      found.push(tagName);
    } else {
      missing.push(tagName);
    }
  }
  
  return { missing, found };
}

/**
 * Validate responsive layout (basic check)
 * @param page Playwright page instance
 * @param viewports Array of viewport sizes to test
 */
export async function validateResponsiveLayout(
  page: Page,
  viewports: Array<{ width: number; height: number }> = [
    { width: 1920, height: 1080 },
    { width: 768, height: 1024 },
    { width: 375, height: 667 },
  ]
): Promise<boolean> {
  let allValid = true;
  
  for (const viewport of viewports) {
    await page.setViewportSize(viewport);
    await page.waitForLoadState('networkidle');
    
    // Check if main content is visible (basic responsive check)
    const mainContent = page.locator('#wpcontent, #content, main, .site-main').first();
    const isVisible = await mainContent.isVisible({ timeout: 2000 }).catch(() => false);
    
    if (!isVisible) {
      allValid = false;
    }
  }
  
  return allValid;
}

/**
 * Validate that links on the page are working
 * @param page Playwright page instance
 * @param maxLinks Maximum number of links to check (default: 10)
 */
export async function validateLinks(
  page: Page,
  maxLinks: number = 10
): Promise<Array<{ url: string; status: number | null; error: string | null }>> {
  const links = page.locator('a[href]');
  const count = Math.min(await links.count(), maxLinks);
  const results: Array<{ url: string; status: number | null; error: string | null }> = [];
  
  for (let i = 0; i < count; i++) {
    const link = links.nth(i);
    const href = await link.getAttribute('href');
    
    if (!href || href.startsWith('#')) {
      continue;
    }
    
    try {
      const response = await page.request.get(href);
      results.push({
        url: href,
        status: response.status(),
        error: response.status() >= 400 ? `HTTP ${response.status()}` : null,
      });
    } catch (error) {
      results.push({
        url: href,
        status: null,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }
  }
  
  return results;
}








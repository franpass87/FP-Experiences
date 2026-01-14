import { test, expect } from '@playwright/test';
import { loginToWordPress } from '../helpers/auth';
import { navigateToAdminPage, FP_EXP_PAGES, isMenuItemVisible, clickMenuItem } from '../helpers/admin';

test.describe('FP Experiences Admin Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await loginToWordPress(page);
  });

  test('should navigate to dashboard', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.dashboard);
    await expect(page).toHaveURL(/fp_exp_dashboard/);
  });

  test('should navigate to settings', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.settings);
    await expect(page).toHaveURL(/fp_exp_settings/);
  });

  test('should navigate to experiences list', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.experiences);
    await expect(page).toHaveURL(/post_type=fp_experience/);
  });

  test('should navigate to new experience', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.newExperience);
    await expect(page).toHaveURL(/post_type=fp_experience/);
  });

  test('should navigate to gift vouchers', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.giftVouchers);
    await expect(page).toHaveURL(/post_type=fp_exp_gift_voucher/);
  });

  test('should navigate to calendar', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.calendar);
    await expect(page).toHaveURL(/fp_exp_calendar/);
  });

  test('should navigate to check-in', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.checkin);
    await expect(page).toHaveURL(/fp_exp_checkin/);
  });

  test('should navigate to emails', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.emails);
    await expect(page).toHaveURL(/fp_exp_emails/);
  });

  test('should navigate to tools', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.tools);
    await expect(page).toHaveURL(/fp_exp_tools/);
  });

  test('should navigate to logs', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.logs);
    await expect(page).toHaveURL(/fp_exp_logs/);
  });

  test('should navigate to help', async ({ page }) => {
    await navigateToAdminPage(page, FP_EXP_PAGES.help);
    await expect(page).toHaveURL(/fp_exp_help/);
  });
});








import type { Page } from "@playwright/test";

import type { UserCredentials } from "./artisan";
import { expect } from "./fixtures";

// ログイン後の遷移先は config('fortify.home')、PROTECTED_PATH はログインが必要な画面の代表。
// 派生アプリはここの値だけ差し替える。
export const HOME_URL = /\/dashboard$/;
export const PROTECTED_PATH = "/dashboard";
export const SETTINGS_PATHS = {
  profile: "/settings/profile",
  security: "/settings/security",
} as const;

export const settingsUrl = (section: keyof typeof SETTINGS_PATHS): RegExp =>
  new RegExp(`${SETTINGS_PATHS[section]}$`);

export const login = async (page: Page, user: UserCredentials): Promise<void> => {
  await page.goto("/login");
  await page.locator('input[name="email"]').fill(user.email);
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="login-button"]').click();
  await expect(page).toHaveURL(HOME_URL);
};

export const confirmPassword = async (
  page: Page,
  password: string,
  returnUrl: RegExp,
): Promise<void> => {
  await page.locator('input[name="password"]').fill(password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(returnUrl);
};

export const openConfirmedSettings = async (
  page: Page,
  user: UserCredentials,
  section: keyof typeof SETTINGS_PATHS,
): Promise<void> => {
  await login(page, user);
  await page.goto(SETTINGS_PATHS[section]);
  await confirmPassword(page, user.password, settingsUrl(section));
};

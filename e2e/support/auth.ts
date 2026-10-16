import type { Page } from "@playwright/test";

import type { UserCredentials } from "./artisan";
import { expect } from "./fixtures";

// ログイン後の遷移先は config('fortify.home')。派生アプリはここだけ差し替える。
export const HOME_URL = /\/dashboard$/;

export const login = async (page: Page, user: UserCredentials): Promise<void> => {
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(HOME_URL);
};

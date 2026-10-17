import type { BrowserContext } from "@playwright/test";

import { createUser } from "../support/artisan";
import { login } from "../support/auth";
import { expect, test } from "../support/fixtures";

const PAGE_EXPIRED_MESSAGE = "ページの有効期限が切れました。再度お試しください。";
const PROFILE_URL = /\/settings\/profile$/;

// Sec-Fetch-Site を送らないブラウザで、期限の切れたトークンのまま送信した状態を再現する。
const expireCsrfToken = async (context: BrowserContext, path: string): Promise<void> => {
  await context.route(
    (url) => url.pathname === path,
    async (route) => {
      if (route.request().method() === "GET") {
        await route.continue();

        return;
      }

      const headers: Record<string, string> = {
        ...route.request().headers(),
        "x-xsrf-token": "expired-token",
      };
      delete headers["sec-fetch-site"];

      await route.fulfill({ response: await route.fetch({ headers }) });
    },
  );
};

test("ログイン前の画面で送信の有効期限が切れたら、そのことを伝える", async ({ page, context }) => {
  const user = createUser();
  await expireCsrfToken(context, "/login");

  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();

  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).toBeVisible();
  await expect(page).toHaveURL(/\/login$/);
});

test("ログイン後の画面で送信の有効期限が切れたら、そのことを伝える", async ({ page, context }) => {
  const user = createUser();
  await login(page, user);
  await page.goto("/settings/profile");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(PROFILE_URL);

  await expireCsrfToken(context, "/settings/profile");
  await page.getByLabel("ニックネーム").fill("期限切れの送信");
  await page.locator('[data-test="update-profile-button"]').click();

  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).toBeVisible();
  await expect(page).toHaveURL(PROFILE_URL);
});

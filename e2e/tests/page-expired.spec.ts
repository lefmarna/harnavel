import type { BrowserContext, Page } from "@playwright/test";

import { createUser } from "../support/artisan";
import { login, openConfirmedSettings, SETTINGS_PATHS, settingsUrl } from "../support/auth";
import { expect, test } from "../support/fixtures";

const PAGE_EXPIRED_MESSAGE = "ページの有効期限が切れました。再度お試しください。";
const PROFILE_URL = settingsUrl("profile");

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

const openProfileSettings = (page: Page): Promise<void> =>
  openConfirmedSettings(page, createUser(), "profile");

test("ログイン後の画面で送信の有効期限が切れたら、そのことを伝える", async ({ page, context }) => {
  await openProfileSettings(page);

  await expireCsrfToken(context, "/settings/profile");
  await page.getByLabel("ニックネーム").fill("期限切れの送信");
  await page.locator('[data-test="update-profile-button"]').click();

  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).toBeVisible();
  await expect(page).toHaveURL(PROFILE_URL);
});

test("ダイアログから送信して有効期限が切れたら、ダイアログの中で伝える", async ({
  page,
  context,
}) => {
  await openProfileSettings(page);

  await page.locator('[data-test="delete-user-button"]').click();
  await expireCsrfToken(context, "/settings/profile");
  await page.locator('[data-test="confirm-delete-user-button"]').click();

  await expect(page.getByRole("dialog").getByText(PAGE_EXPIRED_MESSAGE)).toBeVisible();
});

test("画面の下の方で送信して有効期限が切れても、伝えるメッセージを画面内に出す", async ({
  page,
  context,
}) => {
  await openProfileSettings(page);
  await page.goto(SETTINGS_PATHS.security);

  await expireCsrfToken(context, "/settings/sessions");
  const logoutOtherSessions = page.locator('[data-test="logout-other-sessions-button"]');
  await logoutOtherSessions.click();
  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).toBeInViewport();

  // 2 回目も同じ文言が返るので、文言の変化ではなく応答ごとに寄せていることを確かめる。
  await logoutOtherSessions.scrollIntoViewIfNeeded();
  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).not.toBeInViewport();
  await logoutOtherSessions.click();
  await expect(page.getByText(PAGE_EXPIRED_MESSAGE)).toBeInViewport();
});

test("スマホのサイドバーから送信して有効期限が切れても、そのことを伝える", async ({
  page,
  context,
}) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, createUser());

  await page.locator('[data-sidebar="trigger"]').click();
  await page.locator('[data-test="sidebar-menu-button"]').click();
  await expireCsrfToken(context, "/logout");
  await page.locator('[data-test="logout-button"]').click();

  await expect(page.getByRole("alert").filter({ hasText: PAGE_EXPIRED_MESSAGE })).toBeInViewport();
});

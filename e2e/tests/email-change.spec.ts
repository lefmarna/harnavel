import type { Page } from "@playwright/test";

import {
  createUser,
  emailChangeRevertLink,
  requestEmailChangeLink,
  type UserCredentials,
} from "../support/artisan";
import { expect, test } from "../support/fixtures";

// ログイン後の遷移先は config('fortify.home')。派生アプリはここだけ差し替える。
const HOME_URL = /\/dashboard$/;
const PROFILE_URL = /\/settings\/profile$/;

const login = async (page: Page, user: UserCredentials): Promise<void> => {
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(HOME_URL);
};

const openProfileSettings = async (page: Page, user: UserCredentials): Promise<void> => {
  await login(page, user);
  await page.goto("/settings/profile");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(PROFILE_URL);
};

test("新しいメールアドレスは確認リンクで確定し、旧アドレスへのリンクで取り消せる", async ({
  page,
}) => {
  const user = createUser();
  const newEmail = `changed-${user.email}`;

  await openProfileSettings(page, user);
  await page.getByLabel("メールアドレス").fill(newEmail);
  await page.locator('[data-test="update-profile-button"]').click();
  await expect(page.getByText("新しいメールアドレスに確認メールを送信しました。")).toBeVisible();
  await expect(page.getByLabel("メールアドレス")).toHaveValue(user.email);

  await page.goto(requestEmailChangeLink(user.email, newEmail));
  await page.getByRole("button", { name: "メールアドレスを変更する" }).click();
  await expect(
    page.getByText("次回からは新しいメールアドレスでログインしてください。", { exact: false }),
  ).toBeVisible();

  await page.goto(emailChangeRevertLink(newEmail, user.email));
  await page.getByRole("button", { name: "変更を取り消す" }).click();
  await expect(
    page.getByText("パスワードを再設定してからログインしてください。", { exact: false }),
  ).toBeVisible();

  await page.getByRole("link", { name: "パスワードを再設定する" }).click();
  await expect(page).toHaveURL(/\/forgot-password$/);
});

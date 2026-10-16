import type { Page } from "@playwright/test";

import { createUser, requestEmailChangeLink, type UserCredentials } from "../support/artisan";
import { login } from "../support/auth";
import { expect, test } from "../support/fixtures";

const PROFILE_URL = /\/settings\/profile$/;

const openProfileSettings = async (page: Page, user: UserCredentials): Promise<void> => {
  await login(page, user);
  await page.goto("/settings/profile");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(PROFILE_URL);
};

test("新しいメールアドレスは確認リンクで確定する", async ({ page }) => {
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
});

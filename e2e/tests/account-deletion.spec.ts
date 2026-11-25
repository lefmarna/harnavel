import { createUser } from "../support/artisan";
import { openConfirmedSettings, SETTINGS_PATHS, settingsUrl } from "../support/auth";
import { expect, test } from "../support/fixtures";

const PROFILE_URL = settingsUrl("profile");

test("本人確認済みならパスワードを入力せずにアカウントを削除できる", async ({ page }) => {
  const user = createUser();
  await openConfirmedSettings(page, user, "profile");

  await page.locator('[data-test="delete-user-button"]').click();
  const dialog = page.getByRole("dialog");
  await expect(dialog.locator('input[type="password"]')).toHaveCount(0);
  await expect(dialog.getByText(/完全に削除されます/)).toBeVisible();
  await dialog.getByRole("button", { name: "キャンセル", exact: true }).click();
  await expect(dialog).toBeHidden();
  await expect(page).toHaveURL(PROFILE_URL);

  await page.locator('[data-test="delete-user-button"]').click();
  await page.locator('[data-test="confirm-delete-user-button"]').click();
  await expect(page).not.toHaveURL(PROFILE_URL);
  await page.goto(SETTINGS_PATHS.profile);
  await expect(page).toHaveURL(/\/login$/);

  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(
    page.getByText("ログイン用の認証情報が正しくありません。再度お試しください。"),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/login$/);
});

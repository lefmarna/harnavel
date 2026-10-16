import { createUser, setUserGoogleId } from "../support/artisan";
import { login } from "../support/auth";
import { expect, test } from "../support/fixtures";

const SECURITY_URL = /\/settings\/security$/;

test("心当たりのない Google ログイン連携をセキュリティ設定から解除できる", async ({ page }) => {
  const user = createUser();
  setUserGoogleId(user.email, `google-${Date.now()}`);

  await login(page, user);

  await page.goto("/settings/security");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(SECURITY_URL);

  await expect(page.getByText("Googleアカウントと連携しています。")).toBeVisible();
  await page.locator('[data-test="unlink-google-button"]').click();

  await expect(page.getByRole("status")).toHaveText("Googleログイン連携を解除しました。");
  await expect(page.getByText("Googleアカウントとは連携していません。")).toBeVisible();
});

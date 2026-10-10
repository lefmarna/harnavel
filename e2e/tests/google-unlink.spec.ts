import { createUser, setUserGoogleId } from "../support/artisan";
import { expect, test } from "../support/fixtures";

// ログイン後の遷移先は config('fortify.home')。派生アプリはここだけ差し替える。
const HOME_URL = /\/dashboard$/;
const SECURITY_URL = /\/settings\/security$/;

test("心当たりのない Google ログイン連携をセキュリティ設定から解除できる", async ({ page }) => {
  const user = createUser();
  setUserGoogleId(user.email, `google-${Date.now()}`);

  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(HOME_URL);

  await page.goto("/settings/security");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(SECURITY_URL);

  await expect(page.getByText("Googleアカウントと連携しています。")).toBeVisible();
  await page.locator('[data-test="unlink-google-button"]').click();

  await expect(page.getByRole("status")).toHaveText("Googleログイン連携を解除しました。");
  await expect(page.getByText("Googleアカウントとは連携していません。")).toBeVisible();
});

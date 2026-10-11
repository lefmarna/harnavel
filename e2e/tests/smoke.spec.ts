import { expect, test } from "../support/fixtures";

test("認証済みユーザーでダッシュボードが正常に表示される", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("Test1234");
  await page.getByRole("button", { name: "ログイン", exact: true }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByText("ダッシュボード").first()).toBeVisible();
});

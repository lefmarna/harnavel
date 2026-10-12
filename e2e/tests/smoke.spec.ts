import { expect, test } from "../support/fixtures";

test("パスワードでログインするとログイン状態を保持してダッシュボードを表示する", async ({
  page,
  context,
}) => {
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("Test1234");
  await page.getByRole("button", { name: "ログイン", exact: true }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByText("ダッシュボード").first()).toBeVisible();
  const cookies = await context.cookies();
  expect(cookies.some((cookie) => cookie.name.startsWith("remember_web_"))).toBe(true);
});

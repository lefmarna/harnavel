import { createUser } from "../support/artisan";
import { expect, test } from "../support/fixtures";

test("存在しない URL では、アプリの 404 画面を表示する", async ({ page }) => {
  const response = await page.goto("/no-such-page");

  expect(response?.status()).toBe(404);
  await expect(page.getByRole("heading", { name: "ページが見つかりません" })).toBeVisible();
  await expect(page.getByRole("link", { name: "トップページへ戻る" })).toBeVisible();
});

test("回数制限にかかったときは、アプリの 429 画面を表示する", async ({ page }) => {
  // 制限は接続元ごとに 1 分 10 回。先に使い切ってから、画面として開く。
  for (let attempt = 0; attempt < 10; attempt++) {
    await page.request.get("/auth/google/callback", { maxRedirects: 0 });
  }

  const response = await page.goto("/auth/google/callback");

  expect(response?.status()).toBe(429);
  await expect(page.getByRole("heading", { name: "しばらくお待ちください" })).toBeVisible();
});

test("権限のない操作では、アプリの 403 画面を表示する", async ({ page }) => {
  const user = createUser();
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).not.toHaveURL(/\/login$/);

  // 署名のないメール確認リンクは、ログイン済みでも 403 になる。
  const response = await page.goto("/email/verify/1/invalid-hash");

  expect(response?.status()).toBe(403);
  await expect(page.getByRole("heading", { name: "アクセスできません" })).toBeVisible();
});

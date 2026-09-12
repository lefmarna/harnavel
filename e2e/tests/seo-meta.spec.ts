import { expect, test } from "../support/fixtures";

test("SPA遷移時にSEOメタタグが更新される", async ({ page }) => {
  await page.goto("/");

  await expect(page).toHaveTitle(/ホーム - /);

  const welcomeOgUrl = await page.locator('meta[property="og:url"]').getAttribute("content");
  const welcomeCanonical = page.locator('link[rel="canonical"]');

  expect(welcomeOgUrl).toContain("/");
  await expect(welcomeCanonical).toHaveAttribute("href", /\/$/);

  await page.click('a[href*="login"]');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page).toHaveTitle(/ログイン - /);

  const loginOgTitle = page.locator('meta[property="og:title"]');
  await expect(loginOgTitle).toHaveAttribute("content", /ログイン - /);

  const loginDescription = page.locator('meta[name="description"]');
  await expect(loginDescription).toHaveAttribute(
    "content",
    "メールアドレスとパスワードを入力してログインします。",
  );
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "noindex, nofollow");

  const loginCanonical = page.locator('link[rel="canonical"]');
  await expect(loginCanonical).toHaveAttribute("href", /\/login$/);

  const loginOgUrl = page.locator('meta[property="og:url"]');
  await expect(loginOgUrl).toHaveAttribute("content", /\/login$/);

  expect(await loginOgUrl.getAttribute("content")).not.toBe(welcomeOgUrl);

  const loginTwitterTitle = page.locator('meta[name="twitter:title"]');
  await expect(loginTwitterTitle).toHaveAttribute("content", /ログイン - /);

  const loginOgDescription = page.locator('meta[property="og:description"]');
  await expect(loginOgDescription).toHaveAttribute(
    "content",
    "メールアドレスとパスワードを入力してログインします。",
  );
});

test("パスワードリセットページのcanonicalとog:urlにクエリ文字列が含まれない", async ({ page }) => {
  await page.goto("/reset-password/test-token?email=test%40example.com");

  const canonical = await page.locator('link[rel="canonical"]').getAttribute("href");
  const ogUrl = await page.locator('meta[property="og:url"]').getAttribute("content");

  expect(canonical).not.toBeNull();
  expect(canonical).not.toContain("?");
  expect(canonical).not.toContain("email=");
  expect(canonical).toContain("/reset-password/");

  expect(ogUrl).not.toBeNull();
  expect(ogUrl).not.toContain("?");
  expect(ogUrl).not.toContain("email=");
  expect(ogUrl).toContain("/reset-password/");
});

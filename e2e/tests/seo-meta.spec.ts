import { expect, test } from "../support/fixtures";

// 文言は派生アプリが差し替えるので、値ではなく遷移で書き換わることと、各タグがそろうことを確かめる。
test("SPA遷移時にSEOメタタグが更新される", async ({ page }) => {
  await page.goto("/");

  const ogUrl = page.locator('meta[property="og:url"]');
  await expect(ogUrl).toHaveAttribute("content", /\/$/);
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", /\/$/);
  const homeTitle = await page.title();
  const homeDescription = await page.locator('meta[name="description"]').getAttribute("content");

  await page.click('a[href*="login"]');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page).not.toHaveTitle(homeTitle);

  const title = await page.title();
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute("content", title);
  await expect(page.locator('meta[name="twitter:title"]')).toHaveAttribute("content", title);

  const description = page.locator('meta[name="description"]');
  await expect(description).not.toHaveAttribute("content", homeDescription ?? "");
  const loginDescription = (await description.getAttribute("content")) ?? "";
  expect(loginDescription).not.toBe("");
  await expect(page.locator('meta[property="og:description"]')).toHaveAttribute(
    "content",
    loginDescription,
  );
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "noindex, nofollow");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", /\/login$/);
  await expect(ogUrl).toHaveAttribute("content", /\/login$/);
});

test("パスワードリセットページのcanonicalとog:urlにクエリ文字列が含まれない", async ({ page }) => {
  await page.goto("/reset-password/test-token?email=test%40example.com");
  await expect(page.locator('input[name="password"]')).toBeVisible();

  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    "href",
    /\/reset-password\/test-token$/,
  );
  await expect(page.locator('meta[property="og:url"]')).toHaveAttribute(
    "content",
    /\/reset-password\/test-token$/,
  );
});

import { expect, test } from "../support/fixtures";

test("無効なGoogle連携リンクからフルページ遷移でログインをやり直せる", async ({ page }) => {
  await page.goto("/auth/google/link/verify/invalid");
  await page.route("**/auth/google", (route) =>
    route.fulfill({ contentType: "text/html", body: "OAuth redirect" }),
  );

  const request = page.waitForRequest(
    (request) => new URL(request.url()).pathname === "/auth/google",
  );
  await page.getByRole("link", { name: "Googleログインをやり直す" }).click();

  expect((await request).isNavigationRequest()).toBe(true);
});

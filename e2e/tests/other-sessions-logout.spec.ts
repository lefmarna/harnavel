import { createUser } from "../support/artisan";
import { HOME_URL, login } from "../support/auth";
import { expect, test } from "../support/fixtures";

const SECURITY_URL = /\/settings\/security$/;

test("他のすべての端末からログアウトすると、この端末だけがログインを保つ", async ({
  page,
  browser,
}) => {
  const user = createUser();
  const otherDevice = await browser.newContext();
  const otherPage = await otherDevice.newPage();
  await login(otherPage, user);

  await login(page, user);
  await page.goto("/settings/security");
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(SECURITY_URL);

  await page.locator('[data-test="logout-other-sessions-button"]').click();
  await expect(page.getByRole("status")).toHaveText("他のすべての端末からログアウトしました。");

  await page.goto("/dashboard");
  await expect(page).toHaveURL(HOME_URL);
  await otherPage.goto("/dashboard");
  await expect(otherPage).toHaveURL(/\/login$/);

  await otherDevice.close();
});

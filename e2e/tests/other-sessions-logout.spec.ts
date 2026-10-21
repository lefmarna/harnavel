import { createUser } from "../support/artisan";
import { login, openConfirmedSettings, PROTECTED_PATH } from "../support/auth";
import { expect, test } from "../support/fixtures";

test("他のすべての端末からログアウトすると、この端末だけがログインを保つ", async ({
  page,
  browser,
}) => {
  const user = createUser();
  const otherDevice = await browser.newContext();
  const otherPage = await otherDevice.newPage();
  await login(otherPage, user);

  await openConfirmedSettings(page, user, "security");

  await page.locator('[data-test="logout-other-sessions-button"]').click();
  await expect(page.getByRole("status")).toHaveText("他のすべての端末からログアウトしました。");

  await page.goto(PROTECTED_PATH);
  await expect(page).toHaveURL(new RegExp(`${PROTECTED_PATH}$`));
  await otherPage.goto(PROTECTED_PATH);
  await expect(otherPage).toHaveURL(/\/login$/);

  await otherDevice.close();
});

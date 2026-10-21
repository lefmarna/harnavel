import { createUser, requestEmailChangeLink } from "../support/artisan";
import { openConfirmedSettings } from "../support/auth";
import { expect, test } from "../support/fixtures";

test("新しいメールアドレスは確認リンクで確定する", async ({ page }) => {
  const user = createUser();
  const newEmail = `changed-${user.email}`;

  await openConfirmedSettings(page, user, "profile");
  await page.getByLabel("メールアドレス").fill(newEmail);
  await page.locator('[data-test="update-profile-button"]').click();
  await expect(page.getByText("新しいメールアドレスに確認メールを送信しました。")).toBeVisible();
  await expect(page.getByLabel("メールアドレス")).toHaveValue(user.email);

  await page.goto(requestEmailChangeLink(user.email, newEmail));
  await page.getByRole("button", { name: "メールアドレスを変更する" }).click();
  const completed = page.getByText("次回からは新しいメールアドレスでログインしてください。", {
    exact: false,
  });
  await expect(completed).toBeVisible();
  await expect(page).toHaveURL(/\/settings\/email\/completed$/);

  await page.reload();
  await expect(completed).toBeVisible();
});

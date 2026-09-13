import { expirePasswordConfirmation } from "../support/artisan";
import { expect, test } from "../support/fixtures";

test("パスキーを登録してパスキーだけでログインできる", async ({ page, context, browserName }) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  const client = await context.newCDPSession(page);
  await client.send("WebAuthn.enable");
  await client.send("WebAuthn.addVirtualAuthenticator", {
    options: {
      protocol: "ctap2",
      transport: "internal",
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("password");
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);

  await page.goto("/settings/security");
  await page.getByLabel("現在のパスワード").fill("password");
  await page.getByRole("button", { name: "パスワードを確認" }).click();
  await expect(page).toHaveURL(/\/settings\/security$/);

  await page.getByRole("button", { name: "パスキーを追加" }).click();
  await page.getByLabel("パスキーの名前").fill("E2E オーセンティケータ");
  await page.getByRole("button", { name: "パスキーを登録" }).click();

  await expect(page.getByText("E2E オーセンティケータ")).toBeVisible();

  await context.clearCookies();

  await page.goto("/login");
  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(page).toHaveURL(/\/dashboard$/);
});

test("パスワード確認の期限が切れても再確認してパスキーを登録できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  const client = await context.newCDPSession(page);
  await client.send("WebAuthn.enable");
  await client.send("WebAuthn.addVirtualAuthenticator", {
    options: {
      protocol: "ctap2",
      transport: "internal",
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("password");
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);

  await page.goto("/settings/security");
  await page.getByLabel("現在のパスワード").fill("password");
  await page.getByRole("button", { name: "パスワードを確認" }).click();
  await expect(page).toHaveURL(/\/settings\/security$/);

  expirePasswordConfirmation();

  await page.getByRole("button", { name: "パスキーを追加" }).click();
  await page.getByLabel("パスキーの名前").fill("期限切れ後のパスキー");
  await page.getByRole("button", { name: "パスキーを登録" }).click();

  await expect(page).toHaveURL(/\/user\/confirm-password$/);

  await page.getByLabel("現在のパスワード").fill("password");
  await page.getByRole("button", { name: "パスワードを確認" }).click();
  await expect(page).toHaveURL(/\/settings\/security$/);

  await page.getByRole("button", { name: "パスキーを追加" }).click();
  await page.getByLabel("パスキーの名前").fill("期限切れ後のパスキー");
  await page.getByRole("button", { name: "パスキーを登録" }).click();

  await expect(page.getByText("期限切れ後のパスキー")).toBeVisible();
});

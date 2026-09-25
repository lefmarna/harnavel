import type { Page } from "@playwright/test";

import { expirePasswordConfirmation } from "../support/artisan";
import { expect, test } from "../support/fixtures";
import { enableVirtualAuthenticator } from "../support/passkey";

// ログイン後の遷移先は config('fortify.home')。派生アプリはここだけ差し替える。
const HOME_URL = /\/dashboard$/;

const openSecuritySettings = async (page: Page): Promise<void> => {
  await page.goto("/login");
  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("Test1234");
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(HOME_URL);

  await page.goto("/settings/security");
  await page.locator('input[name="password"]').fill("Test1234");
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(/\/settings\/security$/);
};

const registerPasskey = async (page: Page, name: string): Promise<void> => {
  await page.getByRole("button", { name: "パスキーを追加" }).click();
  await expect(page.getByLabel("パスキーの名前")).toHaveAccessibleDescription(
    "あとで見分けるための名前です。",
  );
  await page.getByLabel("パスキーの名前").fill(name);
  await page.getByRole("button", { name: "パスキーを登録" }).click();
};

test("設定した origin と異なる URL ではパスキー操作を隠し利用できない理由を案内する", async ({
  page,
}) => {
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => false;
  });

  const baseURL = test.info().project.use.baseURL;
  if (!baseURL) {
    throw new Error("E2E の baseURL が必要です");
  }

  const loginURL = new URL("/login", baseURL);
  loginURL.hostname = "127.0.0.1";
  const securityURL = new URL("/settings/security", loginURL);

  await page.goto("/login");
  await expect(page.locator('[data-test="passkey-verify-button"]')).toBeVisible();

  await page.goto(loginURL.href, { waitUntil: "networkidle" });

  await expect(page.locator('[data-test="login-button"]')).toBeVisible();
  await expect(page.locator('[data-test="passkey-verify-button"]')).toBeHidden();

  await page.getByLabel("メールアドレス").fill("test@example.com");
  await page.getByLabel("パスワード").fill("Test1234");
  await page.locator('[data-test="login-button"]').click();
  await expect(page).toHaveURL(HOME_URL);

  await page.goto(securityURL.href);
  await page.locator('input[name="password"]').fill("Test1234");
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(/\/settings\/security$/);

  await expect(page.locator('[data-test="add-passkey-button"]')).toBeHidden();
  await expect(page.locator('[data-test="passkey-register-unavailable"]')).toHaveText(/\S/);
});

test("パスキーを登録してパスキーだけでログインできる", async ({ page, context, browserName }) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  await openSecuritySettings(page);

  await registerPasskey(page, "E2E オーセンティケータ");

  await expect(page.getByText("E2E オーセンティケータ", { exact: true })).toBeVisible();
  // 複数のパスキーが並んでも、読み上げで削除対象を取り違えないこと。
  await expect(
    page.getByRole("button", { name: "「E2E オーセンティケータ」を削除" }),
  ).toBeVisible();

  await context.clearCookies();

  // Conditional UI 対応環境ではボタンが非表示になり、フックも autofill 認証を開始するため、
  // 手動経路のテスト用に isConditionalMediationAvailable を false に固定する
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => false;
  });
  await page.goto("/login");
  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(page).toHaveURL(HOME_URL);
});

test("パスワード確認の期限が切れても再確認してパスキーを登録できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  await openSecuritySettings(page);

  expirePasswordConfirmation();

  await registerPasskey(page, "期限切れ後のパスキー");

  await expect(page).toHaveURL(/\/user\/confirm-password$/);

  await page.locator('input[name="password"]').fill("Test1234");
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(/\/settings\/security$/);

  await registerPasskey(page, "期限切れ後のパスキー");

  await expect(page.getByText("期限切れ後のパスキー", { exact: true })).toBeVisible();
});

test("自動入力が失敗しても再読み込みなしでパスキー認証を再試行できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  await openSecuritySettings(page);

  await registerPasskey(page, "自動入力リトライ用");

  await expect(page.getByText("自動入力リトライ用", { exact: true })).toBeVisible();

  await context.clearCookies();

  // Conditional UI 対応環境の再現。この分岐でだけ自動入力が起動し、手動ボタンが隠れる。
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => true;
  });

  // 自動入力のオプション取得だけを 429 で落とす。スロットリングや一時的な通信断と同じ状態。
  await page.route("**/passkeys/login/options", async (route) => {
    await route.fulfill({
      status: 429,
      contentType: "application/json",
      body: JSON.stringify({ message: "Too Many Attempts." }),
    });
  });

  await page.goto("/login");
  await expect(page.getByText("Too Many Attempts.")).toBeVisible();

  // 通信が復旧しても、フックは自動入力を再試行しない。再試行の導線がなければここで詰まる。
  await page.unroute("**/passkeys/login/options");

  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(page).toHaveURL(HOME_URL);
});

test("Conditional UI 対応ブラウザでは自動入力に任せて手動ボタンを出さない", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  // isConditionalMediationAvailable はヘッドレスでも true を返すので、この分岐には常に入る。
  // 仮想オーセンティケータがないと自動入力の ceremony が NotSupportedError で落ち、
  // フォールバックのボタンが出てしまう。
  await enableVirtualAuthenticator(page, context);

  const loginOptions = page.waitForResponse("**/passkeys/login/options");
  await page.goto("/login");
  await loginOptions;

  // Conditional UI は autocomplete の末尾が webauthn の入力欄を要求する。無いと ceremony が
  // 例外になり、ボタンも隠れたままログイン導線が消える。
  await expect(page.locator('input[autocomplete$="webauthn"]')).toBeVisible();
  // 非表示の検証にアクセシブル名を使わない。ボタン名は label / loadingLabel props で変わり、
  // 変わった瞬間に 0 件マッチとなって toBeHidden が素通りする。
  await expect(page.locator('[data-test="passkey-verify-button"]')).toBeHidden();
});

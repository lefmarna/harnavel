import type { BrowserContext, Page } from "@playwright/test";

import {
  createUser,
  expirePasswordConfirmation,
  runArtisan,
  setUserPassword,
  type UserCredentials,
} from "../support/artisan";
import { HOME_URL, login } from "../support/auth";
import { expect, test } from "../support/fixtures";
import { enableVirtualAuthenticator } from "../support/passkey";

const SECURITY_URL = /\/settings\/security$/;
const PROFILE_URL = /\/settings\/profile$/;
const CONFIRM_PASSWORD_URL = /\/user\/confirm-password$/;

const SEEDED_USER: UserCredentials = { email: "test@example.com", password: "Test1234" };

const confirmPassword = async (page: Page, password: string): Promise<void> => {
  await page.locator('input[name="password"]').fill(password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(SECURITY_URL);
};

const openSecuritySettings = async (page: Page, user = SEEDED_USER): Promise<void> => {
  await login(page, user);

  await page.goto("/settings/security");
  await confirmPassword(page, user.password);
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

  await expect(page.getByText("パスキーを追加しました")).toBeVisible();
  await expect(page.getByText("E2E オーセンティケータ", { exact: true })).toBeVisible();
  await expect(
    page.getByRole("button", { name: "「E2E オーセンティケータ」を削除" }),
  ).toBeVisible();

  await context.clearCookies();

  // 仮想オーセンティケータは環境によって待機中の自動入力に自ら応じ、ボタンより先にログインを
  // 済ませてしまう。ボタンの経路は自動入力を止めてから確かめる。
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => false;
  });
  await page.goto("/login");
  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(page).toHaveURL(HOME_URL);
  const cookies = await context.cookies();
  expect(cookies.some((cookie) => cookie.name.startsWith("remember_web_"))).toBe(true);
});

test("パスキー削除の完了通知と端末に残った資格情報の認証エラーを日本語で表示する", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);
  await openSecuritySettings(page, createUser());
  await registerPasskey(page, "削除するパスキー");
  await expect(page.getByText("削除するパスキー", { exact: true })).toBeVisible();

  await page.getByRole("button", { name: "「削除するパスキー」を削除" }).click();
  await page.getByRole("button", { name: "削除する", exact: true }).click();
  await expect(page.getByRole("status")).toHaveText("パスキーを削除しました。");
  await expect(page.getByRole("status")).toBeVisible();
  await expect(page.getByText("削除するパスキー", { exact: true })).toBeHidden();

  await context.clearCookies();
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => false;
  });
  await page.goto("/login");
  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(
    page.getByText("パスキーが見つかりません。アカウントから削除されている可能性があります。"),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/login$/);
});

test("hex ハンドルへの変更後も旧バイナリ形式のパスキーでログインと本人確認ができる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);
  const user = createUser();
  await openSecuritySettings(page, user);

  const legacyHandle = Buffer.from(
    "4d577e59603ff8b43b36af5f1155eb0967e4390bdde64d75f16c118710484bf2",
    "hex",
  ).toString("base64url");
  await page.route("**/user/passkeys/options", async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as { options: { user: { id: string } } };
    body.options.user.id = legacyHandle;
    await route.fulfill({ response, json: body });
  });
  await registerPasskey(page, "旧バイナリ形式");
  await expect(page.getByText("旧バイナリ形式", { exact: true })).toBeVisible();
  await page.unroute("**/user/passkeys/options");

  runArtisan([
    "tinker",
    "--execute",
    " $user = App\\Models\\User::query()" +
      `->where('email', base64_decode('${Buffer.from(user.email).toString("base64")}'))->firstOrFail();` +
      " $passkey = $user->passkeys()->sole();" +
      " $credential = $passkey->credential;" +
      ` $credential['userHandle'] = '${legacyHandle}';` +
      " $passkey->forceFill(['credential' => $credential])->save();",
  ]);
  await context.clearCookies();
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => false;
  });

  await page.goto("/login");
  await page.getByRole("button", { name: "パスキーでログイン" }).click();
  await expect(page).toHaveURL(HOME_URL);

  await page.goto("/settings/security");
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);
  await page.getByRole("button", { name: "パスキーで確認" }).click();
  await expect(page).toHaveURL(SECURITY_URL);
});

type AutofillStage = "options" | "support-check";

type AutofillRaceWindow = Window & { releaseAutofill: () => void };

test("自動入力でパスキーログインするとログイン状態を保持する", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);
  await openSecuritySettings(page, createUser());
  await registerPasskey(page, "自動入力のログイン状態の保持");
  await expect(page.getByText("自動入力のログイン状態の保持", { exact: true })).toBeVisible();
  await context.clearCookies();

  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => true;
    const get = navigator.credentials.get.bind(navigator.credentials);
    navigator.credentials.get = async (options) => {
      if (options?.mediation !== "conditional") {
        return get(options);
      }

      // 仮想オーセンティケータでも候補を選んだ状態を確実に再現する。
      const requestOptions = { ...options };
      delete requestOptions.mediation;
      return get(requestOptions);
    };
  });

  await page.goto("/login");

  await expect(page).toHaveURL(HOME_URL);
  const cookies = await context.cookies();
  expect(cookies.some((cookie) => cookie.name.startsWith("remember_web_"))).toBe(true);
});

/**
 * 自動入力を stage で止める。自動入力の ceremony は中断されるまで待ち、手動の ceremony は
 * 自動入力の ceremony が始まるまで待つ。手動側が自動入力の決着を待っていなければ中断される。
 */
const prepareAutofillRace = async (
  page: Page,
  context: BrowserContext,
  stage: AutofillStage,
): Promise<void> => {
  await enableVirtualAuthenticator(page, context);

  // シードユーザーはログインの試行制限に近い。
  await openSecuritySettings(page, createUser());
  await registerPasskey(page, "自動入力と競合するボタン");
  await expect(page.getByText("自動入力と競合するボタン", { exact: true })).toBeVisible();

  await context.clearCookies();

  await page.addInitScript((stage: AutofillStage) => {
    let release = () => {};
    const released = new Promise<void>((resolve) => (release = resolve));
    let markConditionalStarted = () => {};
    const conditionalStarted = new Promise<void>((resolve) => (markConditionalStarted = resolve));
    let optionsFetched = false;

    (window as unknown as AutofillRaceWindow).releaseAutofill = release;

    const fetch = window.fetch.bind(window);
    window.fetch = async (input, init) => {
      const response = await fetch(input, init);
      const url = typeof input === "string" ? input : input instanceof URL ? input.href : input.url;
      optionsFetched ||= url.includes("/passkeys/login/options");

      return response;
    };

    // 自動入力は options の取得後、ceremony の直前にもう一度対応を判定する。
    PublicKeyCredential.isConditionalMediationAvailable = async () => {
      if (stage === "support-check" && optionsFetched) {
        await released;
      }

      return true;
    };

    const get = navigator.credentials.get.bind(navigator.credentials);
    navigator.credentials.get = async (options) => {
      if (options?.mediation === "conditional") {
        markConditionalStarted();

        return new Promise((_, reject) => {
          options.signal?.addEventListener("abort", () =>
            reject(new DOMException("The operation was aborted.", "AbortError")),
          );
        });
      }

      release();
      await Promise.race([
        conditionalStarted,
        new Promise((resolve) => setTimeout(resolve, 2_000)),
      ]);

      return get(options);
    };
  }, stage);
};

/**
 * ボタンより前の options 取得（自動入力）を、released か手動側の取得まで止める。
 * 手動側の応答は自動入力の応答の後に返し、challenge が上書きされる順序を作る。
 */
const holdAutofillOptions = async (page: Page, released: Promise<void>) => {
  let clicked = false;
  let markAutofillHeld = () => {};
  const autofillHeld = new Promise<void>((resolve) => (markAutofillHeld = resolve));
  let markManualFetched = () => {};
  const manualFetched = new Promise<void>((resolve) => (markManualFetched = resolve));
  const autofillResponses: Promise<unknown>[] = [];
  let manualRequests = 0;

  await page.route("**/passkeys/login/options", async (route) => {
    if (!clicked) {
      const request = route.request();
      autofillResponses.push(page.waitForResponse((response) => response.request() === request));
      markAutofillHeld();
      await Promise.race([manualFetched, released]);
      await route.continue();
      return;
    }

    manualRequests += 1;
    const response = await route.fetch();
    markManualFetched();
    await Promise.all(autofillResponses);
    await route.fulfill({ response });
  });

  return {
    // 自動入力の取得は描画の後に始まるので、止めたのを見届けてから手動側として扱い始める。
    markClicked: async () => {
      await autofillHeld;
      clicked = true;
    },
    autofillResponded: () => Promise.all(autofillResponses),
    manualRequests: () => manualRequests,
  };
};

test("自動入力の options 取得中にボタンを押しても、パスキーでログインできる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await prepareAutofillRace(page, context, "options");

  let release = () => {};
  const options = await holdAutofillOptions(
    page,
    new Promise<void>((resolve) => (release = resolve)),
  );

  await page.goto("/login");
  await options.markClicked();
  await page.getByRole("button", { name: "パスキーでログイン" }).click();
  // 手動側が待つなら手動の取得は来ないので、時間で流す。
  await page.waitForTimeout(1_000);
  release();

  await expect(page).toHaveURL(HOME_URL);
});

test("自動入力の対応判定中にボタンを押しても、パスキーでログインできる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await prepareAutofillRace(page, context, "support-check");

  const autofillOptions = page.waitForResponse("**/passkeys/login/options");
  await page.goto("/login");
  await autofillOptions;

  await page.getByRole("button", { name: "パスキーでログイン" }).click();
  // 手動側が待つなら手動の ceremony は始まらないので、時間で流す。
  await page.waitForTimeout(1_000);
  await page.evaluate(() => (window as unknown as AutofillRaceWindow).releaseAutofill());

  await expect(page).toHaveURL(HOME_URL);
});

test("自動入力を待っている間に画面を離れたら、パスキー認証を始めない", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await prepareAutofillRace(page, context, "options");

  let release = () => {};
  const options = await holdAutofillOptions(
    page,
    new Promise<void>((resolve) => (release = resolve)),
  );

  await page.goto("/login");
  await options.markClicked();
  await page.getByRole("button", { name: "パスキーでログイン" }).click();
  await page.getByRole("link", { name: "新規登録" }).click();
  await expect(page).toHaveURL(/\/register$/);

  release();
  await options.autofillResponded();
  await page.waitForTimeout(1_000);

  expect(options.manualRequests()).toBe(0);
});

test("ログイン画面に戻った後の手動認証を、前の画面の自動入力が中断しない", async ({ page }) => {
  let releaseOld = () => {};
  const oldReleased = new Promise<void>((resolve) => (releaseOld = resolve));
  await page.exposeFunction("releaseOldAutofill", () => releaseOld());

  // ceremony は中断されるまで待たせる。手動の ceremony が始まったら、前の画面の応答を流す。
  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => true;
    navigator.credentials.get = (options) => {
      if (options?.mediation !== "conditional") {
        document.documentElement.setAttribute("data-manual-started", "");
        void (
          window as unknown as { releaseOldAutofill: () => Promise<void> }
        ).releaseOldAutofill();
      }

      return new Promise((_, reject) => {
        options?.signal?.addEventListener("abort", () =>
          reject(new DOMException("The operation was aborted.", "AbortError")),
        );
      });
    };
  });

  let returned = false;
  const oldResponses: Promise<unknown>[] = [];
  await page.route("**/passkeys/login/options", async (route) => {
    if (!returned) {
      const request = route.request();
      oldResponses.push(page.waitForResponse((response) => response.request() === request));
      await oldReleased;
    }

    await route.continue();
  });

  await page.goto("/login");
  await expect.poll(() => oldResponses.length).toBeGreaterThan(0);
  await page.getByRole("link", { name: "新規登録" }).click();
  await expect(page).toHaveURL(/\/register$/);
  returned = true;
  await page.getByRole("link", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);

  await page.getByRole("button", { name: "パスキーでログイン" }).click();
  // 手動側が待つなら手動の ceremony は始まらないので、時間で流す。
  await page.waitForTimeout(1_000);
  releaseOld();
  await Promise.all(oldResponses);

  await expect(page.locator("html[data-manual-started]")).toBeAttached();
  // 前の画面の自動入力は、応答から間を置かずに ceremony を始める。
  await page.waitForTimeout(500);
  await expect(page.getByText("Authentication ceremony was sent an abort signal")).toBeHidden();
});

test("ログイン画面の自動入力が残っていても、パスキーで本人確認できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  const user = createUser();
  await openSecuritySettings(page, user);
  await registerPasskey(page, "本人確認用");
  await expect(page.getByText("本人確認用", { exact: true })).toBeVisible();

  await context.clearCookies();

  await page.addInitScript(() => {
    PublicKeyCredential.isConditionalMediationAvailable = async () => true;
  });

  // ログイン画面の自動入力を、本人確認を始めるまで決着させない。
  let releaseAutofill = () => {};
  const autofillReleased = new Promise<void>((resolve) => (releaseAutofill = resolve));
  let markRequested = () => {};
  const requested = new Promise<void>((resolve) => (markRequested = resolve));
  await page.route("**/passkeys/login/options", async (route) => {
    markRequested();
    await autofillReleased;
    await route.fulfill({
      status: 503,
      contentType: "application/json",
      body: JSON.stringify({ message: "Temporarily unavailable." }),
    });
  });

  await page.goto("/login");
  await requested;
  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(page).toHaveURL(HOME_URL);

  // 再読み込みすると残っている自動入力が消えるので、画面内の導線で移動する。
  await page.locator('[data-test="sidebar-menu-button"]').click();
  // プロフィール設定も本人確認を求めるため、設定を開いた時点で確認画面へ移る。
  await page.getByRole("menuitem", { name: "Settings" }).click();
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);

  await page.getByRole("button", { name: "パスキーで確認" }).click();
  releaseAutofill();

  await expect(page).toHaveURL(PROFILE_URL);
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

  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);

  await confirmPassword(page, SEEDED_USER.password);

  await registerPasskey(page, "期限切れ後のパスキー");

  await expect(page.getByText("期限切れ後のパスキー", { exact: true })).toBeVisible();
});

/**
 * csrf-token meta を置くと、ログイン時に作り直したトークンを SPA が取り込めないまま、@laravel/passkeys が
 * 古い値を送ってしまう。PreventRequestForgery は Sec-Fetch-Site: same-origin なら先に通すので、素の
 * ブラウザでは表に出ない。送らないブラウザを再現して、トークンの検証まで進ませる。
 *
 * route.continue({ headers }) では Playwright が sec-* を残すため、ブラウザを介さない route.fetch() で送り直す。
 */
test("Sec-Fetch-Site を送らないブラウザでも SPA 遷移のままパスキーを登録できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  // 文書の読み直しはログインまで。以降は画面内の導線だけで進む。
  const user = createUser();
  await login(page, user);

  const passkeyStorePath = "/user/passkeys";
  const csrfProbePath = "/login";
  const sent: Record<string, string>[] = [];
  await context.route(
    (url) => [passkeyStorePath, csrfProbePath].includes(url.pathname),
    async (route) => {
      if (route.request().method() !== "POST") {
        await route.continue();

        return;
      }

      const headers = { ...route.request().headers() };
      delete headers["sec-fetch-site"];
      sent.push(headers);

      await route.fulfill({ response: await route.fetch({ headers }) });
    },
  );

  await page.locator('[data-test="sidebar-menu-button"]').click();
  await page.getByRole("menuitem", { name: "Settings" }).click();
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(PROFILE_URL);
  await page.getByRole("link", { name: "セキュリティ" }).click();
  await expect(page).toHaveURL(SECURITY_URL);

  await registerPasskey(page, "CSRF 回帰のパスキー");
  await expect(page.getByText("CSRF 回帰のパスキー", { exact: true })).toBeVisible();

  // 毎応答で更新される Cookie の値を使い、文書に焼き付いた meta の値は使っていない。
  expect(sent).toHaveLength(1);
  expect(sent[0]?.["x-xsrf-token"]).toBeTruthy();
  expect(sent[0]?.["x-csrf-token"]).toBeUndefined();

  // 上の成功が Sec-Fetch-Site の素通りではなく、トークンの検証を通った結果であることを確かめる。
  const probeStatus = await page.evaluate(async (path) => {
    const response = await fetch(path, {
      method: "POST",
      headers: { Accept: "application/json", "X-XSRF-TOKEN": "broken-token" },
    });

    return response.status;
  }, csrfProbePath);

  expect(probeStatus).toBe(419);
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

  // Conditional UI 対応環境の再現。この分岐でだけ自動入力が起動する。
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
  await expect(
    page.getByText("試行回数が多すぎます。しばらく待ってからもう一度お試しください。"),
  ).toBeVisible();

  // 自動入力は再試行しないので、ボタンから再試行する。
  await page.unroute("**/passkeys/login/options");

  await page.getByRole("button", { name: "パスキーでログイン" }).click();

  await expect(page).toHaveURL(HOME_URL);
});

test("パスワード未設定でパスキーだけを持つユーザーはパスキーで本人確認できる", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  await enableVirtualAuthenticator(page, context);

  const user = createUser();
  await login(page, user);

  await page.goto("/settings/security");
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);
  await expect(page.locator('input[name="password"]')).toBeVisible();
  await expect(page.locator('[data-test="passkey-verify-button"]')).toBeHidden();

  await confirmPassword(page, user.password);
  await registerPasskey(page, "パスキーだけの本人確認");
  await expect(page.getByText("パスキーだけの本人確認", { exact: true })).toBeVisible();

  setUserPassword(user.email, null);
  expirePasswordConfirmation();

  await page.goto("/settings/security");
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);

  const passkeyButton = page.getByRole("button", { name: "パスキーで確認" });
  await expect(passkeyButton).toBeVisible();
  await expect(page.locator('input[name="password"]')).toBeHidden();

  await passkeyButton.click();
  await expect(page).toHaveURL(SECURITY_URL);
});

test("Conditional UI 対応ブラウザでも自動入力と並べてパスキーのボタンを出す", async ({
  page,
  context,
  browserName,
}) => {
  test.skip(browserName !== "chromium", "仮想オーセンティケータは Chromium のみ対応している");

  // isConditionalMediationAvailable はヘッドレスでも true を返すので、この分岐には常に入る。
  // 仮想オーセンティケータがないと自動入力の ceremony が NotSupportedError で落ち、
  // 自動入力が待機している状態を再現できない。
  await enableVirtualAuthenticator(page, context);

  let optionsRequests = 0;
  page.on("request", (request) => {
    if (request.url().endsWith("/passkeys/login/options")) {
      optionsRequests += 1;
    }
  });

  const loginOptions = page.waitForResponse("**/passkeys/login/options");
  await page.goto("/login");
  await loginOptions;

  await expect(page.locator('input[autocomplete$="webauthn"]')).toBeVisible();
  await expect(page.getByRole("button", { name: "パスキーでログイン" })).toBeEnabled();
  // 開発ビルドの StrictMode で effect が再実行されても、自動入力は 1 本だけ。
  expect(optionsRequests).toBe(1);
});

test("自動入力が環境の都合で使えなくても、開いただけのログイン画面にエラーを出さない", async ({
  page,
}) => {
  // 実ブラウザでは実行モードで挙動が変わる（ヘッドレス Chromium は即座に落ち、headed は待ち続ける）。
  // 失敗が画面へ反映されてから判定するため、2 フレーム後に印を付ける。
  await page.addInitScript(() => {
    const get = navigator.credentials.get.bind(navigator.credentials);
    const markSettled = () =>
      requestAnimationFrame(() =>
        requestAnimationFrame(() =>
          document.documentElement.setAttribute("data-conditional-settled", ""),
        ),
      );

    PublicKeyCredential.isConditionalMediationAvailable = async () => true;
    navigator.credentials.get = (options) => {
      if (options?.mediation !== "conditional") {
        return get(options);
      }

      markSettled();

      return Promise.reject(
        new DOMException("Conditional UI is unavailable.", "NotSupportedError"),
      );
    };
  });

  await page.goto("/login");
  await expect(page.locator("html[data-conditional-settled]")).toBeAttached();

  await expect(page.getByRole("button", { name: "パスキーでログイン" })).toBeEnabled();
  await expect(page.getByText("このブラウザはパスキーに対応していません。")).toBeHidden();
});

test("本人確認済みならパスワードを入力せずにアカウントを削除できる", async ({ page }) => {
  const user = createUser();
  await login(page, user);
  await page.goto("/settings/profile");
  await expect(page).toHaveURL(CONFIRM_PASSWORD_URL);
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('[data-test="confirm-password-button"]').click();
  await expect(page).toHaveURL(PROFILE_URL);

  await page.locator('[data-test="delete-user-button"]').click();
  const dialog = page.getByRole("dialog");
  await expect(dialog.locator('input[type="password"]')).toHaveCount(0);
  await expect(dialog.getByText(/完全に削除されます/)).toBeVisible();
  await dialog.getByRole("button", { name: "キャンセル", exact: true }).click();
  await expect(dialog).toBeHidden();
  await expect(page).toHaveURL(PROFILE_URL);

  await page.locator('[data-test="delete-user-button"]').click();
  await page.locator('[data-test="confirm-delete-user-button"]').click();
  await expect(page).not.toHaveURL(PROFILE_URL);
  await page.goto("/settings/profile");
  await expect(page).toHaveURL(/\/login$/);

  await page.getByLabel("メールアドレス").fill(user.email);
  await page.getByLabel("パスワード").fill(user.password);
  await page.getByRole("button", { name: "ログイン", exact: true }).click();
  await expect(
    page.getByText("ログイン用の認証情報が正しくありません。再度お試しください。"),
  ).toBeVisible();
  await expect(page).toHaveURL(/\/login$/);
});

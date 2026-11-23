import type { BrowserContext, Page } from "@playwright/test";

import { createUser } from "../support/artisan";
import { login } from "../support/auth";
import { expect, test } from "../support/fixtures";

const COOKIE_NAME = "appearance";

type InitialThemeSnapshot = {
  dark: boolean;
  colorScheme: string;
};

async function recordInitialTheme(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const observer = new MutationObserver(() => {
      if (document.body) {
        (window as unknown as { __initialTheme: InitialThemeSnapshot }).__initialTheme = {
          dark: document.documentElement.classList.contains("dark"),
          colorScheme: document.documentElement.style.colorScheme,
        };
        observer.disconnect();
      }
    });

    observer.observe(document, { childList: true, subtree: true });
  });
}

async function initialTheme(page: Page): Promise<InitialThemeSnapshot | undefined> {
  return page.evaluate(
    () => (window as unknown as { __initialTheme?: InitialThemeSnapshot }).__initialTheme,
  );
}

async function cookieAppearance(context: BrowserContext): Promise<string | undefined> {
  const cookies = await context.cookies();

  return cookies.find((cookie) => cookie.name === COOKIE_NAME)?.value;
}

test.describe("表示テーマ", () => {
  test("Cookie が未設定なら端末のダーク設定に従い、読み込み直後からダークで表示する", async ({
    page,
  }) => {
    await page.emulateMedia({ colorScheme: "dark" });
    await recordInitialTheme(page);
    await page.goto("/");
    await expect(page.locator('a[href*="login"]')).toBeVisible();

    expect(await initialTheme(page)).toEqual({ dark: true, colorScheme: "dark" });
    await expect(page.locator("html")).toHaveClass(/(^|\s)dark(\s|$)/);
    await expect(page.locator("html")).toHaveCSS("color-scheme", "dark");
  });

  test("localStorage が使えない環境でも Cookie の選択で表示し、設定画面からの切り替えを再読み込み後も保つ", async ({
    page,
    context,
    baseURL,
  }) => {
    const user = createUser();

    await page.emulateMedia({ colorScheme: "light" });
    await recordInitialTheme(page);
    await page.addInitScript(() => {
      Object.defineProperty(window, "localStorage", {
        get() {
          throw new DOMException("localStorage is disabled", "SecurityError");
        },
      });
    });
    await context.addCookies([{ name: COOKIE_NAME, value: "dark", url: baseURL ?? "" }]);
    await login(page, user);
    await page.goto("/settings/appearance");

    const html = page.locator("html");
    expect(await initialTheme(page)).toEqual({ dark: true, colorScheme: "dark" });
    await expect(html).toHaveClass(/(^|\s)dark(\s|$)/);
    await expect(html).toHaveCSS("color-scheme", "dark");
    await expect(page.locator('input[name="appearance"][value="dark"]')).toBeChecked();

    await page.getByText("Light", { exact: true }).click();

    await expect(html).not.toHaveClass(/(^|\s)dark(\s|$)/);
    await expect(html).toHaveCSS("color-scheme", "light");
    expect(await cookieAppearance(context)).toBe("light");

    await page.emulateMedia({ colorScheme: "dark" });
    await page.reload();

    expect(await initialTheme(page)).toEqual({ dark: false, colorScheme: "light" });
    await expect(html).not.toHaveClass(/(^|\s)dark(\s|$)/);
    await expect(html).toHaveCSS("color-scheme", "light");
  });
});

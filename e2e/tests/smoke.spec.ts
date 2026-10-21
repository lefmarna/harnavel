import { login } from "../support/auth";
import { expect, test } from "../support/fixtures";

test("パスワードでログインするとログイン状態を保持してログイン後の画面を表示する", async ({
  page,
  context,
}) => {
  await login(page, { email: "test@example.com", password: "Test1234" });

  const cookies = await context.cookies();
  expect(cookies.some((cookie) => cookie.name.startsWith("remember_web_"))).toBe(true);
});

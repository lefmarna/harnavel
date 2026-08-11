import assert from "node:assert/strict";
import { test } from "node:test";

import { resolveAppName } from "./app-name";

test("設定されたアプリ名を保持する", () => {
  assert.equal(resolveAppName("Custom App"), "Custom App");
});

test("空のアプリ名は既定名へフォールバックする", () => {
  assert.equal(resolveAppName(""), "Laravel App Template");
});

test("未設定のアプリ名は既定名へフォールバックする", () => {
  assert.equal(resolveAppName(undefined), "Laravel App Template");
});

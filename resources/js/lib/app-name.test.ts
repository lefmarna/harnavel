import assert from "node:assert/strict";
import { describe, it } from "node:test";

import { resolveAppName } from "./app-name";

describe("アプリ名の解決", function () {
  it("設定されたアプリ名を保持する", function () {
    assert.equal(resolveAppName("Custom App"), "Custom App");
  });

  it("空のアプリ名は既定名へフォールバックする", function () {
    assert.equal(resolveAppName(""), "Laravel App Template");
  });

  it("未設定のアプリ名は既定名へフォールバックする", function () {
    assert.equal(resolveAppName(undefined), "Laravel App Template");
  });
});

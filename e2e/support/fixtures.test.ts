import assert from "node:assert/strict";
import { describe, it } from "node:test";

import { formatPageError } from "./fixtures";

describe("ブラウザエラーの表示", () => {
  it("stackが空ならエラー名とmessageを表示する", () => {
    const error = new Error("Network error");
    error.name = "Unhandled Promise Rejection";
    error.stack = "";

    assert.equal(formatPageError(error), "Unhandled Promise Rejection: Network error");
  });
});

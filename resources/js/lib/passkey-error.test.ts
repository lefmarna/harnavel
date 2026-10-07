import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  InvalidDomainError,
  NotSupportedError,
  PasskeyError,
  PasskeyExistsError,
  UserCancelledError,
} from "@laravel/passkeys";

import { passkeyErrorMessage } from "./passkey-error";

describe("パスキーのエラー表示", () => {
  it("エラーがなければ文言を表示しない", () => {
    assert.equal(passkeyErrorMessage(null), null);
  });

  for (const [error, expected] of [
    [new UserCancelledError(), "パスキーの操作をキャンセルしました。"],
    [new PasskeyExistsError(), "この端末はすでにパスキーとして登録されています。"],
    [new NotSupportedError(), "このブラウザはパスキーに対応していません。"],
    [new InvalidDomainError("example.test"), "このドメインではパスキーを利用できません。"],
  ] as const) {
    it(`${error.name} は日本語で案内する`, () => {
      assert.equal(passkeyErrorMessage(error), expected);
    });
  }

  it("翻訳済みのサーバーエラーは具体的な理由を保持する", () => {
    const message = "パスキー登録の有効期限が切れました。もう一度お試しください。";

    assert.equal(passkeyErrorMessage(new PasskeyError(message)), message);
  });

  it("未知の通信エラーは日本語の再試行案内に置き換える", () => {
    assert.equal(
      passkeyErrorMessage(new PasskeyError("Failed to fetch")),
      "パスキーの操作に失敗しました。もう一度お試しください。",
    );
  });
});

import {
  InvalidDomainError,
  NotSupportedError,
  PasskeyExistsError,
  UserCancelledError,
  type PasskeyError,
} from "@laravel/passkeys";

export const passkeyErrorMessage = (error: PasskeyError | null): string | null => {
  if (!error) {
    return null;
  }

  if (error instanceof UserCancelledError) {
    return "パスキーの操作をキャンセルしました。";
  }

  if (error instanceof PasskeyExistsError) {
    return "この端末はすでにパスキーとして登録されています。";
  }

  if (error instanceof NotSupportedError) {
    return "このブラウザはパスキーに対応していません。";
  }

  if (error instanceof InvalidDomainError) {
    return "このドメインではパスキーを利用できません。";
  }

  if (error.message === "Too Many Attempts.") {
    return "試行回数が多すぎます。しばらく待ってからもう一度お試しください。";
  }

  // @laravel/passkeys はエラーの出どころを保持しないため、日本語を翻訳済み文言の目印にする。
  if (/[\p{Script=Hiragana}\p{Script=Katakana}\p{Script=Han}]/u.test(error.message)) {
    return error.message;
  }

  return "パスキーの操作に失敗しました。もう一度お試しください。";
};

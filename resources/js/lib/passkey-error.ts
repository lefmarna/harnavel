import type { PasskeyError } from "@laravel/passkeys";

/**
 * ライブラリがブラウザ内で組み立てる文言。lang ファイルでは訳せないため、エラー名で対応づける。
 * サーバーが返した文言は Laravel 側で訳されているので、そのまま通す。
 */
const BROWSER_MESSAGES: Record<string, string> = {
  UserCancelledError: "パスキーの操作をキャンセルしました。",
  PasskeyExistsError: "この端末はすでにパスキーとして登録されています。",
  NotSupportedError: "このブラウザはパスキーに対応していません。",
  InvalidDomainError: "このドメインではパスキーを利用できません。",
};

export const passkeyErrorMessage = (error: PasskeyError | null): string | null => {
  if (!error) {
    return null;
  }

  return BROWSER_MESSAGES[error.name] ?? error.message;
};

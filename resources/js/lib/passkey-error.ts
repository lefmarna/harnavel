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

  return error.message;
};

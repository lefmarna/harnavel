import type { UrlMethodPair } from "@inertiajs/core";
import { router } from "@inertiajs/react";
import { usePasskeyVerify } from "@laravel/passkeys/react";
import { KeyRound } from "lucide-react";
import { useEffect, useState } from "react";

import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { passkeyErrorMessage } from "@/lib/passkey-error";

type Props = {
  autofill?: boolean;
  routes?: {
    options: UrlMethodPair;
    submit: UrlMethodPair;
  };
  label?: string;
  loadingLabel?: string;
  separator?: string;
};

export default function PasskeyVerify({
  autofill,
  routes,
  label,
  loadingLabel,
  separator,
}: Props = {}) {
  const { verify, isLoading, errorInstance, isSupported } = usePasskeyVerify({
    autofill,
    ...(routes && {
      routes: {
        options: routes.options.url,
        submit: routes.submit.url,
      },
    }),
    onSuccess: (response) => {
      // サーバーは常に redirect を返す（PasskeyLoginResponse）。型が省略可能なので
      // 既定値だけ置く。vendor 側の config("passkeys.redirect", "/") と同じ値。
      router.visit(response.redirect ?? "/");
    },
  });

  // Conditional UI 対応ブラウザでは自動入力に任せ、非対応環境ではボタンで補う。
  const [isConditionalUiAvailable, setIsConditionalUiAvailable] = useState(false);
  useEffect(() => {
    if (!autofill) return;
    if (typeof PublicKeyCredential === "undefined") return;
    void PublicKeyCredential.isConditionalMediationAvailable?.().then((available) => {
      setIsConditionalUiAvailable(available);
    });
  }, [autofill]);

  const errorMessage = passkeyErrorMessage(errorInstance);

  if (!isSupported) {
    return null;
  }

  // 失敗時はボタンを出す。usePasskeyVerify はマウント時に一度しか自動入力を起動しないため、
  // ボタンがないと通信が復旧してもページを再読み込みするまで再試行できない。
  const isAutofillActive = isConditionalUiAvailable && !errorMessage;

  if (isAutofillActive) {
    return null;
  }

  return (
    <>
      <div className="grid gap-2">
        <Button
          type="button"
          variant="outline"
          className="w-full"
          onClick={verify}
          disabled={isLoading}
          data-test="passkey-verify-button"
        >
          {isLoading ? <Spinner /> : <KeyRound className="h-4 w-4" />}
          {isLoading ? (loadingLabel ?? "認証しています...") : (label ?? "パスキーでログイン")}
        </Button>

        {errorMessage && <InputError message={errorMessage} className="text-center" />}
      </div>

      <div className="relative my-6">
        <div className="absolute inset-0 flex items-center">
          <Separator className="w-full" />
        </div>

        <div className="relative flex justify-center text-xs uppercase">
          <span className="bg-background px-2 text-muted-foreground">
            {separator ?? "またはメールアドレスで続ける"}
          </span>
        </div>
      </div>
    </>
  );
}

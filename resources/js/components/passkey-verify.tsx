import type { UrlMethodPair } from "@inertiajs/core";
import { router } from "@inertiajs/react";
import {
  InvalidDomainError,
  NotSupportedError,
  PasskeyError,
  Passkeys,
  type VerifyResponse,
} from "@laravel/passkeys";
import { usePasskeyVerify } from "@laravel/passkeys/react";
import { KeyRound } from "lucide-react";
import { useEffect, useEffectEvent, useRef, useState } from "react";

import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { usePasskeyAvailable } from "@/hooks/use-passkey-available";
import { passkeyErrorMessage } from "@/lib/passkey-error";

type Routes = {
  options: UrlMethodPair;
  submit: UrlMethodPair;
};

type Props = {
  /** Conditional UI。候補が出ない環境もあるので、ボタンは併せて常に出す。 */
  autofill?: boolean;
  routes?: Routes;
  label?: string;
  loadingLabel?: string;
};

const toVerifyOptions = (routes?: Routes) =>
  routes && { routes: { options: routes.options.url, submit: routes.submit.url } };

const visitRedirect = (response: VerifyResponse) => {
  // サーバーは常に redirect を返す（PasskeyLoginResponse）。型が省略可能なので
  // 既定値だけ置く。vendor 側の config("passkeys.redirect", "/") と同じ値。
  router.visit(response.redirect ?? "/");
};

export default function PasskeyVerify(props: Props) {
  if (!usePasskeyAvailable()) {
    return null;
  }

  return <AvailablePasskeyVerify {...props} />;
}

function AvailablePasskeyVerify({ autofill = false, routes, label, loadingLabel }: Props) {
  const [autofillError, setAutofillError] = useState<PasskeyError | null>(null);
  const [isStoppingAutofill, setIsStoppingAutofill] = useState(false);

  const stopAutofill = usePasskeyAutofill({
    enabled: autofill,
    routes,
    onError: (error) => {
      // 環境起因の失敗は利用者が操作する前に起きるので黙る。理由はボタンから試せば出る。
      if (error instanceof NotSupportedError || error instanceof InvalidDomainError) {
        return;
      }

      // 候補を選んだ後にサーバーで拒否された場合などは、出さないと何も起きなかったように見える。
      setAutofillError(error);
    },
  });

  const { verify, isLoading, errorInstance } = usePasskeyVerify({
    ...toVerifyOptions(routes),
    onSuccess: visitRedirect,
  });

  const handleClick = async () => {
    setAutofillError(null);
    setIsStoppingAutofill(true);

    // 止めた自動入力は張り直さない。この後の認証や再試行と重なる。
    if (!(await stopAutofill())) {
      return;
    }

    setIsStoppingAutofill(false);
    await verify();
  };

  const errorMessage = passkeyErrorMessage(errorInstance ?? autofillError);
  const isBusy = isLoading || isStoppingAutofill;

  return (
    <div className="grid gap-2">
      <Button
        type="button"
        variant="outline"
        className="w-full"
        onClick={() => void handleClick()}
        disabled={isBusy}
        data-test="passkey-verify-button"
      >
        {isBusy ? <Spinner /> : <KeyRound className="h-4 w-4" />}
        {isBusy ? (loadingLabel ?? "認証しています...") : (label ?? "パスキーでログイン")}
      </Button>

      {errorMessage && <InputError message={errorMessage} className="text-center" />}
    </div>
  );
}

const wait = (milliseconds: number) =>
  new Promise<void>((resolve) => setTimeout(resolve, milliseconds));

// 自動入力は effect の破棄後も（画面を離れても）走り続けるので、モジュールで追跡する。
const runningAutofills = new Set<Promise<void>>();

/**
 * 手動の認証を始めてよくなるまで自動入力を止める関数を返す。@laravel/passkeys は自動入力の
 * options 取得も ceremony の開始時点も制御できず、重なると手動の ceremony の中断や challenge の
 * 上書きが起きる。そのため自動入力が決着するまで中断を繰り返す。
 */
function usePasskeyAutofill({
  enabled,
  routes,
  onError,
}: {
  enabled: boolean;
  routes?: Routes;
  onError: (error: PasskeyError) => void;
}): () => Promise<boolean> {
  const active = useRef(false);
  const loggedIn = useRef(false);
  const handleError = useEffectEvent(onError);
  const optionsUrl = routes?.options.url;
  const submitUrl = routes?.submit.url;

  useEffect(() => {
    active.current = true;

    if (!enabled) {
      return () => {
        active.current = false;
      };
    }

    let disposed = false;
    const run: Promise<void> = (async () => {
      // StrictMode で破棄された effect に options を取りに行かせない。
      if (!(await Passkeys.isAutofillSupported()) || disposed) {
        return;
      }

      try {
        const response = await Passkeys.autofill(
          optionsUrl && submitUrl ? { routes: { options: optionsUrl, submit: submitUrl } } : {},
        );

        if (response && !disposed) {
          loggedIn.current = true;
          visitRedirect(response);
        }
      } catch (error) {
        if (error instanceof PasskeyError && !disposed) {
          handleError(error);
        }
      }
    })().finally(() => runningAutofills.delete(run));
    runningAutofills.add(run);

    return () => {
      active.current = false;
      disposed = true;
      Passkeys.cancel();
    };
  }, [enabled, optionsUrl, submitUrl]);

  return async () => {
    while (runningAutofills.size > 0) {
      if (!active.current) {
        return false;
      }

      Passkeys.cancel();
      await Promise.race([Promise.all(runningAutofills), wait(50)]);
    }

    return active.current && !loggedIn.current;
  };
}

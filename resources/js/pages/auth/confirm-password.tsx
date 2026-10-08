import { Form, usePage } from "@inertiajs/react";

import {
  index as confirmOptions,
  store as confirmStore,
} from "@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController";
import GoogleIcon from "@/components/google-icon";
import InputError from "@/components/input-error";
import PasskeyVerify from "@/components/passkey-verify";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { usePasskeyAvailable } from "@/hooks/use-passkey-available";
import AuthLayout from "@/layouts/auth-layout";
import { reauthenticate } from "@/routes/auth/google";
import { store } from "@/routes/password/confirm";
import type { SharedData } from "@/types";

type Props = {
  hasPasskeys: boolean;
  canConfirmWithGoogle: boolean;
  error: string | null;
};

export default function ConfirmPassword({ hasPasskeys, canConfirmWithGoogle, error }: Props) {
  const { auth } = usePage<SharedData>().props;
  const canUsePasskey = usePasskeyAvailable();

  if (!auth.hasPassword) {
    const showPasskey = hasPasskeys && canUsePasskey;

    return (
      <AuthLayout description="セキュリティ保護のため、本人確認をしてください。">
        <div className="grid gap-3">
          {showPasskey && <PasskeyConfirm />}

          {canConfirmWithGoogle && <GoogleConfirm />}

          {!showPasskey && !canConfirmWithGoogle && (
            <p className="text-center text-sm text-muted-foreground">
              {hasPasskeys
                ? "このブラウザではパスキーを使えないため、本人確認を続けられません。パスキーに対応したブラウザで開き直してください。"
                : "Googleでの本人確認は現在利用できません。時間をおいてもう一度お試しください。"}
            </p>
          )}
        </div>

        <ConfirmError message={error} />
      </AuthLayout>
    );
  }

  const showPasskey = hasPasskeys && canUsePasskey;

  return (
    <AuthLayout
      description={
        showPasskey
          ? "セキュリティ保護のため、パスキーかパスワードで本人確認をしてください。"
          : undefined
      }
    >
      {showPasskey && (
        <div>
          <PasskeyConfirm />

          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <Separator className="w-full" />
            </div>

            <div className="relative flex justify-center text-xs uppercase">
              <span className="bg-background px-2 text-muted-foreground">
                またはパスワードで確認
              </span>
            </div>
          </div>
        </div>
      )}

      <Form {...store.form()} resetOnSuccess={["password"]}>
        {({ processing, errors }) => (
          <div className="space-y-6">
            <div className="grid gap-2">
              <Label htmlFor="password">現在のパスワード</Label>
              <Input
                id="password"
                type="password"
                name="password"
                placeholder="現在のパスワード"
                autoComplete="current-password"
                autoFocus
              />

              <InputError message={errors.password} />
            </div>

            <div className="flex items-center">
              <Button className="w-full" disabled={processing} data-test="confirm-password-button">
                {processing && <Spinner />}
                パスワードを確認
              </Button>
            </div>
          </div>
        )}
      </Form>

      <ConfirmError message={error} />
    </AuthLayout>
  );
}

function GoogleConfirm() {
  return (
    <Button
      variant="outline"
      type="button"
      className="w-full"
      onClick={() => (window.location.href = reauthenticate.url())}
    >
      <GoogleIcon />
      Googleで確認
    </Button>
  );
}

function ConfirmError({ message }: { message: string | null }) {
  if (!message) {
    return null;
  }

  return <div className="text-center text-sm font-medium text-red-600">{message}</div>;
}

function PasskeyConfirm() {
  return (
    <PasskeyVerify
      routes={{
        options: confirmOptions(),
        submit: confirmStore(),
      }}
      label="パスキーで確認"
      loadingLabel="確認しています..."
    />
  );
}

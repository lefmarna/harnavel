import { Form, usePage } from "@inertiajs/react";

import {
  index as confirmOptions,
  store as confirmStore,
} from "@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController";
import InputError from "@/components/input-error";
import PasskeyVerify from "@/components/passkey-verify";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { usePasskeyAvailable } from "@/hooks/use-passkey-available";
import AuthLayout from "@/layouts/auth-layout";
import { store } from "@/routes/password/confirm";
import type { SharedData } from "@/types";

type Props = {
  hasPasskeys: boolean;
};

export default function ConfirmPassword({ hasPasskeys }: Props) {
  const { auth } = usePage<SharedData>().props;
  const canUsePasskey = usePasskeyAvailable();

  if (!auth.hasPassword && hasPasskeys) {
    return (
      <AuthLayout
        title="本人確認"
        description="セキュリティ保護のため、パスキーで本人確認をしてください。"
      >
        {canUsePasskey ? (
          <PasskeyConfirm />
        ) : (
          <p className="text-center text-sm text-muted-foreground">
            このブラウザではパスキーを使えないため、本人確認を続けられません。パスキーに対応したブラウザで開き直してください。
          </p>
        )}
      </AuthLayout>
    );
  }

  const showPasskey = hasPasskeys && canUsePasskey;

  return (
    <AuthLayout
      title={showPasskey ? "本人確認" : undefined}
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
    </AuthLayout>
  );
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

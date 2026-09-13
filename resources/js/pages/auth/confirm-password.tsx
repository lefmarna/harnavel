import { Form } from "@inertiajs/react";

import {
  index as confirmOptions,
  store as confirmStore,
} from "@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController";
import InputError from "@/components/input-error";
import PasskeyVerify from "@/components/passkey-verify";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import AuthLayout from "@/layouts/auth-layout";
import { store } from "@/routes/password/confirm";

export default function ConfirmPassword() {
  return (
    <AuthLayout>
      <PasskeyVerify
        routes={{
          options: confirmOptions(),
          submit: confirmStore(),
        }}
        label="パスキーで確認"
        loadingLabel="確認しています..."
        separator="またはパスワードで確認"
      />

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

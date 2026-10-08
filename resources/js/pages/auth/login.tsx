import { Form } from "@inertiajs/react";
import { useCallback, useRef } from "react";

import GoogleIcon from "@/components/google-icon";
import InputError from "@/components/input-error";
import PasskeyVerify from "@/components/passkey-verify";
import TextLink from "@/components/text-link";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { usePasskeyAvailable } from "@/hooks/use-passkey-available";
import AuthLayout from "@/layouts/auth-layout";
import { register } from "@/routes";
import { google } from "@/routes/auth";
import { store } from "@/routes/login";
import { request } from "@/routes/password";

type Props = {
  error?: string;
  status?: string;
  canResetPassword: boolean;
  canRegister: boolean;
  canGoogleLogin: boolean;
};

export default function Login({
  error,
  status,
  canResetPassword,
  canRegister,
  canGoogleLogin,
}: Props) {
  const canUsePasskey = usePasskeyAvailable();
  const remember = useRef(false);
  const getRemember = useCallback(() => remember.current, []);

  return (
    <AuthLayout>
      <Form {...store.form()} resetOnSuccess={["password"]} className="flex flex-col gap-6">
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid gap-2">
                <Label htmlFor="email">メールアドレス</Label>
                <Input
                  id="email"
                  type="email"
                  name="email"
                  required
                  autoFocus
                  tabIndex={1}
                  autoComplete="email webauthn"
                  placeholder="you@example.com"
                />
                <InputError message={errors.email} />
              </div>

              <div className="grid gap-2">
                <div className="flex items-center">
                  <Label htmlFor="password">パスワード</Label>
                  {canResetPassword && (
                    <TextLink href={request()} className="ml-auto text-sm" tabIndex={5}>
                      パスワードをお忘れですか？
                    </TextLink>
                  )}
                </div>
                <Input
                  id="password"
                  type="password"
                  name="password"
                  required
                  tabIndex={2}
                  autoComplete="current-password"
                  placeholder="パスワード"
                />
                <InputError message={errors.password} />
              </div>

              <div className="flex items-center space-x-3">
                <Checkbox
                  id="remember"
                  name="remember"
                  tabIndex={3}
                  onCheckedChange={(checked) => {
                    remember.current = checked === true;
                  }}
                />
                <Label htmlFor="remember">ログイン状態を保持する</Label>
              </div>

              <Button
                type="submit"
                className="mt-4 w-full"
                tabIndex={4}
                disabled={processing}
                data-test="login-button"
              >
                {processing && <Spinner />}
                ログイン
              </Button>
            </div>

            {canRegister && (
              <div className="text-center text-sm text-muted-foreground">
                アカウントをお持ちでないですか？{" "}
                <TextLink href={register()} tabIndex={5}>
                  新規登録
                </TextLink>
              </div>
            )}

            {(canUsePasskey || canGoogleLogin) && (
              <>
                <div className="relative">
                  <div className="absolute inset-0 flex items-center">
                    <Separator className="w-full" />
                  </div>
                  <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background px-2 text-muted-foreground">または</span>
                  </div>
                </div>

                <div className="grid gap-3">
                  {/* パスキーはこのアカウント自身の資格情報なので、外部の Google より先に置く。 */}
                  <PasskeyVerify autofill remember={getRemember} />

                  {canGoogleLogin && (
                    <Button
                      variant="outline"
                      type="button"
                      className="w-full"
                      onClick={() => (window.location.href = google.url())}
                    >
                      <GoogleIcon />
                      Googleでログイン
                    </Button>
                  )}
                </div>
              </>
            )}
          </>
        )}
      </Form>

      {error && <div className="mb-4 text-center text-sm font-medium text-red-600">{error}</div>}

      {status && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
      )}
    </AuthLayout>
  );
}

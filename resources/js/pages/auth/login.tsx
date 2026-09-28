import { Form } from "@inertiajs/react";

import InputError from "@/components/input-error";
import PasskeyVerify from "@/components/passkey-verify";
import TextLink from "@/components/text-link";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
                <Checkbox id="remember" name="remember" tabIndex={3} />
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
              <div className="relative my-6">
                <div className="absolute inset-0 flex items-center">
                  <span className="w-full border-t border-muted/30" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                  <span className="bg-background/50 px-2 text-muted-foreground backdrop-blur-sm">
                    または
                  </span>
                </div>
              </div>
            )}

            {/* パスキーはこのアカウント自身の資格情報なので、外部の Google より先に置く。 */}
            <PasskeyVerify autofill />

            {canGoogleLogin && (
              <Button
                variant="outline"
                type="button"
                className="h-12 w-full border-muted/40 bg-background/50 text-base text-foreground transition-colors hover:bg-background/80 hover:text-foreground"
                onClick={() => (window.location.href = google.url())}
              >
                <svg className="mr-2 h-5 w-5" viewBox="0 0 24 24">
                  <path
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    fill="#4285F4"
                  />
                  <path
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    fill="#34A853"
                  />
                  <path
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.26.81-.58z"
                    fill="#FBBC05"
                  />
                  <path
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    fill="#EA4335"
                  />
                </svg>
                Googleでログイン
              </Button>
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

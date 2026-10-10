import { Transition } from "@headlessui/react";
import { Form, usePage } from "@inertiajs/react";
import { useRef } from "react";

import { destroyGoogle, update } from "@/actions/App/Http/Controllers/Settings/SecurityController";
import Heading from "@/components/heading";
import InputError from "@/components/input-error";
import ManagePasskeys from "@/components/manage-passkeys";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/app-layout";
import SettingsLayout from "@/layouts/settings/layout";
import { edit } from "@/routes/security";
import type { BreadcrumbItem, Passkey, SharedData } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "セキュリティ設定",
    href: edit().url,
  },
];

type Props = {
  canManagePasskeys: boolean;
  passkeys: Passkey[];
  googleLinked: boolean;
  canUnlinkGoogle: boolean;
};

export default function Security({
  canManagePasskeys,
  passkeys,
  googleLinked,
  canUnlinkGoogle,
}: Props) {
  const { auth, flash } = usePage<SharedData>().props;
  const hasPassword = auth.hasPassword;
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <h1 className="sr-only">セキュリティ設定</h1>

      <SettingsLayout>
        <div className="space-y-12">
          <div className="space-y-6">
            <Heading
              variant="small"
              title="パスワード更新"
              description="安全のため、十分に強いパスワードへ更新してください"
            />

            <Form
              {...update.form()}
              options={{
                preserveScroll: true,
              }}
              resetOnError={["password", "password_confirmation", "current_password"]}
              resetOnSuccess
              onError={(errors) => {
                if (errors.password) {
                  passwordInput.current?.focus();
                }

                if (errors.current_password) {
                  currentPasswordInput.current?.focus();
                }
              }}
              className="space-y-6"
            >
              {({ errors, processing, recentlySuccessful }) => (
                <>
                  {hasPassword && (
                    <div className="grid gap-2">
                      <Label htmlFor="current_password">現在のパスワード</Label>

                      <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        name="current_password"
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        placeholder="現在のパスワード"
                      />

                      <InputError message={errors.current_password} />
                    </div>
                  )}

                  <div className="grid gap-2">
                    <Label htmlFor="password">新しいパスワード</Label>

                    <Input
                      id="password"
                      ref={passwordInput}
                      name="password"
                      type="password"
                      className="mt-1 block w-full"
                      autoComplete="new-password"
                      placeholder="新しいパスワード"
                    />

                    <InputError message={errors.password} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">パスワード確認</Label>

                    <Input
                      id="password_confirmation"
                      name="password_confirmation"
                      type="password"
                      className="mt-1 block w-full"
                      autoComplete="new-password"
                      placeholder="もう一度入力してください"
                    />

                    <InputError message={errors.password_confirmation} />
                  </div>

                  <div className="flex items-center gap-4">
                    <Button disabled={processing} data-test="update-password-button">
                      パスワードを保存
                    </Button>

                    <Transition
                      show={recentlySuccessful}
                      enter="transition ease-in-out"
                      enterFrom="opacity-0"
                      leave="transition ease-in-out"
                      leaveTo="opacity-0"
                    >
                      <p className="text-sm text-neutral-600">保存しました</p>
                    </Transition>
                  </div>
                </>
              )}
            </Form>
          </div>

          <div className="space-y-6">
            <ManagePasskeys canManagePasskeys={canManagePasskeys} passkeys={passkeys} />

            {flash.status === "passkey-deleted" && (
              <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                パスキーを削除しました。
              </p>
            )}
          </div>

          <div className="space-y-6">
            <Heading
              variant="small"
              title="Googleログイン連携"
              description="心当たりのない連携は解除してください"
            />

            {googleLinked ? (
              <Form
                {...destroyGoogle.form()}
                options={{ preserveScroll: true }}
                className="space-y-4"
              >
                {({ errors, processing }) => (
                  <>
                    <p className="text-sm text-muted-foreground">
                      Googleアカウントと連携しています。
                    </p>

                    {!canUnlinkGoogle && (
                      <p className="text-sm text-muted-foreground">
                        解除するには、先にパスワードを設定するかパスキーを追加してください。
                      </p>
                    )}

                    <Button
                      variant="destructive"
                      disabled={processing || !canUnlinkGoogle}
                      data-test="unlink-google-button"
                    >
                      連携を解除
                    </Button>

                    <InputError message={errors.google} />
                  </>
                )}
              </Form>
            ) : (
              <p className="text-sm text-muted-foreground">
                Googleアカウントとは連携していません。
              </p>
            )}

            {flash.status === "google-unlinked" && (
              <p role="status" className="text-sm font-medium text-green-600 dark:text-green-400">
                Googleログイン連携を解除しました。
              </p>
            )}
          </div>
        </div>
      </SettingsLayout>
    </AppLayout>
  );
}

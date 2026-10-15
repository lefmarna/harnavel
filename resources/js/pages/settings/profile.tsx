import { Transition } from "@headlessui/react";
import type { FormComponentRef } from "@inertiajs/core";
import { Form, Link, usePage } from "@inertiajs/react";
import { useRef } from "react";

import { update } from "@/actions/App/Http/Controllers/Settings/ProfileController";
import DeleteUser from "@/components/delete-user";
import Heading from "@/components/heading";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/app-layout";
import SettingsLayout from "@/layouts/settings/layout";
import { edit } from "@/routes/profile";
import { send } from "@/routes/verification";
import type { BreadcrumbItem, SharedData } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: "プロフィール設定",
    href: edit().url,
  },
];

export default function Profile({
  mustVerifyEmail,
  status,
}: {
  mustVerifyEmail: boolean;
  status?: string;
}) {
  const { auth } = usePage<SharedData>().props;
  const form = useRef<FormComponentRef>(null);
  const user = auth.user;

  if (!user) {
    return null;
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <h1 className="sr-only">プロフィール設定</h1>

      <SettingsLayout>
        <div className="space-y-6">
          <Heading
            variant="small"
            title="プロフィール情報"
            description="ニックネームとメールアドレスを更新します"
          />

          {/* 確認メールを送ったときはまだ切り替わっていないので、現在のアドレスに戻す。
              大文字小文字だけの変更はすぐ保存されるので、key で作り直して保存した値を初期値にする。 */}
          <Form
            key={user.email}
            ref={form}
            {...update.form()}
            options={{
              preserveScroll: true,
            }}
            onSuccess={(page) => {
              if (page.props.status === "email-change-link-sent") {
                form.current?.reset("email");
              }
            }}
            className="space-y-6"
          >
            {({ processing, recentlySuccessful, errors }) => (
              <>
                <div className="grid gap-2">
                  <Label htmlFor="name">ニックネーム</Label>

                  <Input
                    id="name"
                    className="mt-1 block w-full"
                    defaultValue={user.name}
                    name="name"
                    required
                    autoComplete="nickname"
                    placeholder="たろう"
                  />

                  <InputError className="mt-2" message={errors.name} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="email">メールアドレス</Label>

                  <Input
                    id="email"
                    type="email"
                    className="mt-1 block w-full"
                    defaultValue={user.email}
                    name="email"
                    required
                    autoComplete="username"
                    placeholder="you@example.com"
                  />

                  <InputError className="mt-2" message={errors.email} />

                  {status === "email-change-link-sent" && (
                    <p className="text-sm font-medium text-green-600">
                      新しいメールアドレスに確認メールを送信しました。メール内のリンクを開くと変更が完了します。
                    </p>
                  )}
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                  <div>
                    <p className="-mt-4 text-sm text-muted-foreground">
                      メールアドレスが未認証です。{" "}
                      <Link
                        href={send()}
                        as="button"
                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                      >
                        認証メールを再送する
                      </Link>
                    </p>

                    {status === "verification-link-sent" && (
                      <div className="mt-2 text-sm font-medium text-green-600">
                        メールアドレス宛に新しい認証リンクを送信しました。
                      </div>
                    )}
                  </div>
                )}

                <div className="flex items-center gap-4">
                  <Button disabled={processing} data-test="update-profile-button">
                    保存
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

        <DeleteUser />
      </SettingsLayout>
    </AppLayout>
  );
}

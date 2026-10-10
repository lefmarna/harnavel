import { Link, router } from "@inertiajs/react";
import { Check, Mail, XCircle } from "lucide-react";

import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { home } from "@/routes";
import { edit } from "@/routes/profile";

type Props = {
  status: "confirm" | "success" | "error";
  actionUrl?: string;
  email?: string;
};

export default function EmailChangeVerify({ status, actionUrl, email }: Props) {
  if (status === "confirm") {
    return (
      <AuthLayout>
        <div className="space-y-6 text-center">
          <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
            <Mail className="size-7" />
          </div>

          <p className="text-sm leading-6 text-muted-foreground">
            メールアドレスを {email ?? "このメールアドレス"}{" "}
            に変更します。問題なければ、下のボタンを押して完了してください。
          </p>

          <Button
            type="button"
            className="w-full"
            onClick={() => {
              if (actionUrl) {
                router.post(actionUrl);
              }
            }}
          >
            メールアドレスを変更する
          </Button>
        </div>
      </AuthLayout>
    );
  }

  if (status === "success") {
    return (
      <AuthLayout>
        <div className="space-y-6 text-center">
          <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400">
            <Check className="size-7" />
          </div>

          <p className="text-sm leading-6 text-muted-foreground">
            メールアドレスの変更が完了しました。次回からは新しいメールアドレスでログインしてください。
          </p>

          <Button asChild className="w-full">
            <Link href={edit().url}>プロフィール設定へ</Link>
          </Button>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <div className="space-y-6 text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <XCircle className="size-7" />
        </div>

        <p className="text-sm leading-6 text-muted-foreground">
          メールアドレスを変更できませんでした。リンクが無効か期限切れ、またはこのメールアドレスがすでに使われている可能性があります。プロフィール設定からもう一度お試しください。
        </p>

        <Button asChild className="w-full">
          <Link href={home().url}>トップへ戻る</Link>
        </Button>
      </div>
    </AuthLayout>
  );
}

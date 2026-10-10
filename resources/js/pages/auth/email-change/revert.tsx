import { Link, router } from "@inertiajs/react";
import { Check, ShieldAlert, XCircle } from "lucide-react";

import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { home } from "@/routes";
import { request } from "@/routes/password";

type Props = {
  status: "confirm" | "success" | "error";
  actionUrl?: string;
  email?: string;
};

export default function EmailChangeRevert({ status, actionUrl, email }: Props) {
  if (status === "confirm") {
    return (
      <AuthLayout>
        <div className="space-y-6 text-center">
          <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
            <ShieldAlert className="size-7" />
          </div>

          <p className="text-sm leading-6 text-muted-foreground">
            メールアドレスを {email ?? "変更前のメールアドレス"}{" "}
            に戻します。あわせてパスワード・パスキー・Googleログイン連携を解除し、すべての端末でログアウトします。
          </p>

          <Button
            type="button"
            variant="destructive"
            className="w-full"
            onClick={() => {
              if (actionUrl) {
                router.post(actionUrl);
              }
            }}
          >
            変更を取り消す
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
            メールアドレスの変更を取り消しました。パスワードを再設定してからログインしてください。
          </p>

          <Button asChild className="w-full">
            <Link href={request().url}>パスワードを再設定する</Link>
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
          メールアドレスの変更を取り消せませんでした。リンクが無効か期限切れ、または変更前のメールアドレスが別のアカウントで使われている可能性があります。
        </p>

        <Button asChild className="w-full">
          <Link href={home().url}>トップへ戻る</Link>
        </Button>
      </div>
    </AuthLayout>
  );
}

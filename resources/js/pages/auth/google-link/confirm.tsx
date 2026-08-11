import { Mail } from "lucide-react";

import AuthLayout from "@/layouts/auth-layout";

export default function GoogleLinkConfirm() {
  return (
    <AuthLayout>
      <div className="space-y-6 text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
          <Mail className="size-7" />
        </div>

        <div className="space-y-2">
          <h2 className="text-lg font-medium">確認メールを送信しました</h2>
          <p className="text-sm leading-6 text-muted-foreground">
            同じメールアドレスの既存アカウントが見つかりました。本人確認のため、Googleログイン連携用の確認メールを送信しました。
          </p>
        </div>
      </div>
    </AuthLayout>
  );
}

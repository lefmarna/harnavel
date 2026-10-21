import { Link } from "@inertiajs/react";
import { Check, Mail, XCircle } from "lucide-react";

import EmailLinkStatus, {
  EmailLinkConfirmButton,
  type EmailLinkPageProps,
} from "@/components/email-link-status";
import { Button } from "@/components/ui/button";
import { home } from "@/routes";
import { edit } from "@/routes/profile";

export default function EmailChangeVerify({ status, actionUrl, email }: EmailLinkPageProps) {
  if (status === "confirm") {
    return (
      <EmailLinkStatus
        status={status}
        icon={Mail}
        message={`メールアドレスを ${email ?? "このメールアドレス"} に変更します。問題なければ、下のボタンを押して完了してください。`}
      >
        <EmailLinkConfirmButton actionUrl={actionUrl}>
          メールアドレスを変更する
        </EmailLinkConfirmButton>
      </EmailLinkStatus>
    );
  }

  if (status === "success") {
    return (
      <EmailLinkStatus
        status={status}
        icon={Check}
        message="メールアドレスの変更が完了しました。次回からは新しいメールアドレスでログインしてください。"
      >
        <Button asChild className="w-full">
          <Link href={edit().url}>プロフィール設定へ</Link>
        </Button>
      </EmailLinkStatus>
    );
  }

  return (
    <EmailLinkStatus
      status={status}
      icon={XCircle}
      message="メールアドレスを変更できませんでした。リンクが無効か期限切れ、またはこのメールアドレスがすでに使われている可能性があります。プロフィール設定からもう一度お試しください。"
    >
      <Button asChild className="w-full">
        <Link href={home().url}>トップへ戻る</Link>
      </Button>
    </EmailLinkStatus>
  );
}

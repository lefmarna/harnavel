import { Link } from "@inertiajs/react";
import { Check, Link as LinkIcon, XCircle } from "lucide-react";

import EmailLinkStatus, {
  EmailLinkConfirmButton,
  type EmailLinkPageProps,
} from "@/components/email-link-status";
import { Button } from "@/components/ui/button";
import { login } from "@/routes";
import { google } from "@/routes/auth";

export default function GoogleLinkVerify({ status, actionUrl, email }: EmailLinkPageProps) {
  if (status === "confirm") {
    return (
      <EmailLinkStatus
        status={status}
        icon={LinkIcon}
        message={`${email ?? "このアカウント"} にGoogleログインを連携します。問題なければ、下のボタンを押して完了してください。`}
      >
        <EmailLinkConfirmButton actionUrl={actionUrl}>
          Googleログインを連携する
        </EmailLinkConfirmButton>
      </EmailLinkStatus>
    );
  }

  if (status === "success") {
    return (
      <EmailLinkStatus
        status={status}
        icon={Check}
        message="Googleログインの連携手続きが完了しました。次回からはGoogleでログインできます。"
      >
        <Button
          type="button"
          className="w-full"
          onClick={() => {
            window.location.href = google.url();
          }}
        >
          Googleでログインする
        </Button>
      </EmailLinkStatus>
    );
  }

  return (
    <EmailLinkStatus
      status={status}
      icon={XCircle}
      message="Googleログインの連携に失敗しました。リンクが無効か、期限切れ、または現在のアカウント状態では連携できない可能性があります。"
    >
      <Button asChild className="w-full">
        <Link href={login().url}>ログイン画面へ戻る</Link>
      </Button>

      <a href={google.url()} className="text-sm underline underline-offset-4">
        Googleログインをやり直す
      </a>
    </EmailLinkStatus>
  );
}

import { router, usePage } from "@inertiajs/react";
import { AlertCircleIcon } from "lucide-react";
import { useEffect, useRef } from "react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";

const submissionError = (errors: Record<string, string | undefined>) =>
  errors.page_expired ?? errors.too_many_requests;

/**
 * 送信の有効期限切れ（419）と回数制限（429）を伝える。サーバーはどのフォームからでも直前の画面へ
 * page_expired か too_many_requests を返すので、フォームごとではなくレイアウトとダイアログで表示する。
 * 開いたダイアログやシートは背面を覆い、読み上げの対象からも外すため、その中にも出す。
 * destructive 系の色はアプリによって文字色にも赤背景の上の文字色にもなるので、入力エラーと同じ赤を使う。
 * アイコンの大きさと色も、Alert の版によって既定が違うため明示する。
 * スクロール位置を保つ送信では画面の外に出るので、表示したら見える位置まで寄せる。同じ文言が
 * 続けて返ると表示は変わらないため、該当する応答を受けるたびにも寄せる。固定ヘッダーに隠れない
 * よう上に余白を取る。
 */
export default function SubmissionErrorAlert({ className }: { className?: string }) {
  const message = submissionError(usePage().props.errors);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    ref.current?.scrollIntoView({ block: "nearest" });
  }, [message]);

  useEffect(
    () =>
      router.on("error", (event) => {
        if (submissionError(event.detail.errors)) {
          ref.current?.scrollIntoView({ block: "nearest" });
        }
      }),
    [],
  );

  if (!message) {
    return null;
  }

  return (
    <Alert
      ref={ref}
      className={cn(
        "scroll-mt-20 border-red-600/30 text-red-600 dark:border-red-400/30 dark:text-red-400 [&>svg]:size-4 [&>svg]:text-current",
        className,
      )}
    >
      <AlertCircleIcon />
      <AlertDescription className="text-current">{message}</AlertDescription>
    </Alert>
  );
}

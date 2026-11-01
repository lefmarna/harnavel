import { router, usePage } from "@inertiajs/react";
import { AlertCircleIcon } from "lucide-react";
import { useEffect, useRef } from "react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { cn } from "@/lib/utils";

const submissionError = (errors: Record<string, string | undefined>) =>
  errors.page_expired ?? errors.too_many_requests;

/**
 * 419 と 429 はサーバーが直前の画面へ共有エラーとして返し、開いたダイアログやシートは背面を覆うため、
 * フォームごとではなくレイアウトとダイアログの双方で表示する。
 */
export default function SubmissionErrorAlert({ className }: { className?: string }) {
  const message = submissionError(usePage().props.errors);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    ref.current?.scrollIntoView({ block: "nearest" });
  }, [message]);

  // 同じ文言が続けて返ると message は変わらないため、応答ごとにも寄せる。
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

  // destructive の文字色はアプリによって赤背景の上の色になるため、入力エラーと同じ赤を使う。
  // アイコンの大きさと色も Alert の版によって既定が違うため明示する。
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

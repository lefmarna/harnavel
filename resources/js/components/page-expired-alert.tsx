import { usePage } from "@inertiajs/react";
import { AlertCircleIcon } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";

/**
 * 送信の有効期限切れ（419）を伝える。サーバーはどのフォームからでも直前の画面へ page_expired を
 * 返すので、フォームごとではなくレイアウトで表示する。
 */
export default function PageExpiredAlert({ className }: { className?: string }) {
  const message = usePage().props.errors.page_expired;

  if (!message) {
    return null;
  }

  return (
    <Alert variant="destructive" className={className}>
      <AlertCircleIcon />
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  );
}

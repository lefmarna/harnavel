import { router } from "@inertiajs/react";
import { KeyRound } from "lucide-react";

import { destroy } from "@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController";
import Heading from "@/components/heading";
import PasskeyItem from "@/components/passkey-item";
import PasskeyRegister from "@/components/passkey-register";
import TextLink from "@/components/text-link";
import { notice } from "@/routes/verification";
import type { Passkey } from "@/types";

type Props = {
  canManagePasskeys: boolean;
  canRegisterPasskey: boolean;
  passkeys: Passkey[];
};

function EmptyState() {
  return (
    <div className="p-8 text-center">
      <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
        <KeyRound className="h-7 w-7 text-muted-foreground" />
      </div>

      <p className="font-medium">パスキーはまだありません</p>

      <p className="mt-1 text-sm text-muted-foreground">
        パスキーを追加すると、端末の画面ロック（指紋・顔認証・PIN）でパスワードなしにログインできます。
      </p>
    </div>
  );
}

export default function ManagePasskeys({ canManagePasskeys, canRegisterPasskey, passkeys }: Props) {
  if (!canManagePasskeys) {
    return null;
  }

  const handleDelete = (id: number, onError: () => void): void => {
    router.delete(destroy.url(id), {
      preserveScroll: true,
      onError,
    });
  };

  return (
    <div className="space-y-6">
      <Heading
        variant="small"
        title="パスキー"
        description={
          passkeys.length > 0
            ? "ログイン画面の「パスキーでログイン」から使えます。対応するブラウザでは、メールアドレス欄の候補にも表示されます。"
            : "パスワードを使わずにログインするためのパスキーを管理します"
        }
      />

      <div className="overflow-hidden rounded-lg border border-border">
        {passkeys.length > 0 ? (
          passkeys.map((passkey) => (
            <PasskeyItem key={passkey.id} passkey={passkey} onDelete={handleDelete} />
          ))
        ) : (
          <EmptyState />
        )}
      </div>

      {canRegisterPasskey ? (
        <PasskeyRegister onSuccess={() => router.reload()} />
      ) : (
        <p className="text-sm text-muted-foreground">
          パスキーを追加するには、<TextLink href={notice()}>メールアドレスの確認</TextLink>
          を済ませてください。
        </p>
      )}
    </div>
  );
}

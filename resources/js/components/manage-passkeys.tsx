import { router } from "@inertiajs/react";
import { KeyRound } from "lucide-react";

import { destroy } from "@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController";
import Heading from "@/components/heading";
import PasskeyItem from "@/components/passkey-item";
import PasskeyRegister from "@/components/passkey-register";
import type { Passkey } from "@/types";

export type Props = {
  canManagePasskeys: boolean;
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
        パスキーを追加すると、パスワードなしでログインできます
      </p>
    </div>
  );
}

export default function ManagePasskeys({ canManagePasskeys, passkeys }: Props) {
  const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
      preserveScroll: true,
      onError,
    });
  };

  if (!canManagePasskeys) {
    return null;
  }

  return (
    <div className="space-y-6">
      <Heading
        variant="small"
        title="パスキー"
        description="パスワードを使わずにログインするためのパスキーを管理します"
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

      <PasskeyRegister onSuccess={() => router.reload()} />
    </div>
  );
}

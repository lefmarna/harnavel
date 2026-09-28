import { KeyRound, Trash2 } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import type { Passkey } from "@/types";

type Props = {
  passkey: Passkey;
  onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDelete = () => {
    setIsDeleting(true);
    onDelete(passkey.id, () => setIsDeleting(false));
  };

  return (
    <div className="flex items-center justify-between border-b p-4 last:border-b-0">
      <div className="flex items-center gap-4">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
          <KeyRound className="h-5 w-5 text-muted-foreground" />
        </div>

        <div className="space-y-1">
          <div className="flex items-center gap-2.5">
            <p className="font-medium tracking-tight">{passkey.name}</p>

            {passkey.authenticator && (
              <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset">
                {passkey.authenticator}
              </span>
            )}
          </div>

          <p className="text-sm text-muted-foreground">
            {passkey.created_at_diff}に追加
            {passkey.last_used_at_diff && (
              <>
                <span className="mx-1 text-muted-foreground/50">/</span>
                最終利用 {passkey.last_used_at_diff}
              </>
            )}
          </p>
        </div>
      </div>

      <Dialog>
        <DialogTrigger asChild>
          <Button
            variant="ghost"
            size="sm"
            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
            data-test="delete-passkey-button"
          >
            <Trash2 className="h-4 w-4" />
            <span className="sr-only">「{passkey.name}」を削除</span>
          </Button>
        </DialogTrigger>

        <DialogContent>
          <DialogTitle>パスキーを削除しますか</DialogTitle>

          <DialogDescription asChild>
            <div className="space-y-2">
              <p>「{passkey.name}」を削除すると、このパスキーではログインできなくなります。</p>
              <p>
                端末やパスワードマネージャーに保存されたパスキーは残るため、不要になったら端末側の設定からも削除してください。
              </p>
            </div>
          </DialogDescription>

          <DialogFooter className="gap-2">
            <DialogClose asChild>
              <Button variant="secondary">キャンセル</Button>
            </DialogClose>

            <Button
              variant="destructive"
              onClick={handleDelete}
              disabled={isDeleting}
              data-test="confirm-delete-passkey-button"
            >
              {isDeleting ? "削除しています..." : "削除する"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

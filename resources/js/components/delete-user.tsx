import { Form } from "@inertiajs/react";

import { destroy } from "@/actions/App/Http/Controllers/Settings/ProfileController";
import Heading from "@/components/heading";
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

export default function DeleteUser() {
  return (
    <div className="space-y-6">
      <Heading
        variant="small"
        title="アカウント削除"
        description="アカウントと関連データを削除します"
      />
      <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
        <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
          <p className="font-medium">警告</p>
          <p className="text-sm">この操作は取り消せません。内容を確認のうえ実行してください。</p>
        </div>

        <Dialog>
          <DialogTrigger asChild>
            <Button variant="destructive" data-test="delete-user-button">
              アカウントを削除
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogTitle>本当にアカウントを削除しますか？</DialogTitle>
            <DialogDescription>
              アカウントを削除すると、関連するすべてのデータも完全に削除されます。
            </DialogDescription>

            <Form
              {...destroy.form()}
              options={{
                preserveScroll: true,
              }}
              className="space-y-6"
            >
              {({ processing }) => (
                <>
                  <DialogFooter className="gap-2">
                    <DialogClose asChild>
                      <Button variant="secondary">キャンセル</Button>
                    </DialogClose>

                    <Button variant="destructive" disabled={processing} asChild>
                      <button type="submit" data-test="confirm-delete-user-button">
                        アカウントを削除
                      </button>
                    </Button>
                  </DialogFooter>
                </>
              )}
            </Form>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}

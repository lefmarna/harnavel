import { Form, usePage } from '@inertiajs/react'
import { useRef } from 'react'
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { SharedData } from '@/types'

export default function DeleteUser() {
  const { auth } = usePage<SharedData>().props
  const hasPassword = auth.hasPassword
  const passwordInput = useRef<HTMLInputElement>(null)

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
          <p className="text-sm">
            この操作は取り消せません。内容を確認のうえ実行してください。
          </p>
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
              アカウントを削除すると、関連するすべてのデータも完全に削除されます。{' '}
              {hasPassword &&
                '削除を確定するには現在のパスワードを入力してください。'}
            </DialogDescription>

            <Form
              {...ProfileController.destroy.form()}
              options={{
                preserveScroll: true,
              }}
              onError={() => passwordInput.current?.focus()}
              resetOnSuccess
              className="space-y-6"
            >
              {({ resetAndClearErrors, processing, errors }) => (
                <>
                  {hasPassword && (
                    <div className="grid gap-2">
                      <Label htmlFor="password" className="sr-only">
                        パスワード
                      </Label>

                      <Input
                        id="password"
                        type="password"
                        name="password"
                        ref={passwordInput}
                        placeholder="現在のパスワード"
                        autoComplete="current-password"
                      />

                      <InputError message={errors.password} />
                    </div>
                  )}

                  <DialogFooter className="gap-2">
                    <DialogClose asChild>
                      <Button
                        variant="secondary"
                        onClick={() => resetAndClearErrors()}
                      >
                        キャンセル
                      </Button>
                    </DialogClose>

                    <Button variant="destructive" disabled={processing} asChild>
                      <button
                        type="submit"
                        data-test="confirm-delete-user-button"
                      >
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
  )
}

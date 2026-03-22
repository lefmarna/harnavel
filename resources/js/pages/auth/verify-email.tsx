// Components
import { Form } from '@inertiajs/react'
import TextLink from '@/components/text-link'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import AuthLayout from '@/layouts/auth-layout'
import { logout } from '@/routes'
import { send } from '@/routes/verification'

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <AuthLayout>
      {status === 'verification-link-sent' && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          登録時に入力したメールアドレス宛に、新しい認証リンクを送信しました。
        </div>
      )}

      <Form {...send.form()} className="space-y-6 text-center">
        {({ processing }) => (
          <>
            <Button disabled={processing} variant="secondary">
              {processing && <Spinner />}
              認証メールを再送する
            </Button>

            <TextLink href={logout()} className="mx-auto block text-sm">
              ログアウト
            </TextLink>
          </>
        )}
      </Form>
    </AuthLayout>
  )
}

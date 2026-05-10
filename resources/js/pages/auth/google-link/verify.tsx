import { Link, router } from '@inertiajs/react'
import { Check, Link as LinkIcon, XCircle } from 'lucide-react'
import TextLink from '@/components/text-link'
import { Button } from '@/components/ui/button'
import AuthLayout from '@/layouts/auth-layout'
import { login } from '@/routes'
import { google } from '@/routes/auth'

type Props = {
  status: 'confirm' | 'success' | 'error'
  actionUrl?: string
  email?: string
}

export default function GoogleLinkVerify({ status, actionUrl, email }: Props) {
  if (status === 'confirm') {
    return (
      <AuthLayout>
        <div className="space-y-6 text-center">
          <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
            <LinkIcon className="size-7" />
          </div>

          <p className="text-sm leading-6 text-muted-foreground">
            {email ?? 'このアカウント'}{' '}
            にGoogleログインを連携します。問題なければ、下のボタンを押して完了してください。
          </p>

          <Button
            type="button"
            className="w-full"
            onClick={() => {
              if (actionUrl) {
                router.post(actionUrl)
              }
            }}
          >
            Googleログインを連携する
          </Button>
        </div>
      </AuthLayout>
    )
  }

  if (status === 'success') {
    return (
      <AuthLayout>
        <div className="space-y-6 text-center">
          <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-400">
            <Check className="size-7" />
          </div>

          <p className="text-sm leading-6 text-muted-foreground">
            Googleログインの連携手続きが完了しました。次回からはGoogleでログインできます。
          </p>

          <Button
            type="button"
            className="w-full"
            onClick={() => {
              window.location.href = google.url()
            }}
          >
            Googleでログインする
          </Button>
        </div>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout>
      <div className="space-y-6 text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <XCircle className="size-7" />
        </div>

        <p className="text-sm leading-6 text-muted-foreground">
          Googleログインの連携に失敗しました。リンクが無効か、期限切れ、または現在のアカウント状態では連携できない可能性があります。
        </p>

        <Button asChild className="w-full">
          <Link href={login().url}>ログイン画面へ戻る</Link>
        </Button>

        <TextLink href={google.url()}>Googleログインをやり直す</TextLink>
      </div>
    </AuthLayout>
  )
}

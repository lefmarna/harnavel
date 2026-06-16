import { Head, Link } from '@inertiajs/react'
import { ServerCrash } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { home } from '@/routes'

export default function ServerError({
  requestId,
}: {
  requestId: string | null
}) {
  const displayRequestId = requestId ?? 'unavailable'

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground md:p-10">
      <Head title="エラーが発生しました" />

      <div className="w-full max-w-md space-y-6 text-center">
        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <ServerCrash className="size-7" />
        </div>

        <div className="space-y-2">
          <h1 className="text-xl font-medium">エラーが発生しました</h1>
          <p className="text-sm leading-6 text-muted-foreground">
            時間をおいてもう一度お試しください。お問い合わせの際は、以下の問い合わせIDをお知らせください。
          </p>
        </div>

        <div className="space-y-1.5 text-sm">
          <p className="text-muted-foreground">問い合わせID</p>
          <code className="inline-block rounded-md border border-input bg-card px-3 py-2 font-mono break-all text-card-foreground">
            {displayRequestId}
          </code>
        </div>

        <Button asChild className="w-full">
          <Link href={home()}>トップページへ戻る</Link>
        </Button>
      </div>
    </div>
  )
}

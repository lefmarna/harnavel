import { Form } from '@inertiajs/react'
import { ShieldBan, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import Heading from '@/components/heading'
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes'
import TwoFactorSetupModal from '@/components/two-factor-setup-modal'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth'
import AppLayout from '@/layouts/app-layout'
import SettingsLayout from '@/layouts/settings/layout'
import { disable, enable, show } from '@/routes/two-factor'
import type { BreadcrumbItem } from '@/types'

type Props = {
  requiresConfirmation?: boolean
  twoFactorEnabled?: boolean
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: '二段階認証設定',
    href: show.url(),
  },
]

export default function TwoFactor({
  requiresConfirmation = false,
  twoFactorEnabled = false,
}: Props) {
  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth()
  const [showSetupModal, setShowSetupModal] = useState<boolean>(false)

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <h1 className="sr-only">二段階認証設定</h1>

      <SettingsLayout>
        <div className="space-y-6">
          <Heading
            variant="small"
            title="二段階認証"
            description="二段階認証の設定を管理します"
          />
          {twoFactorEnabled ? (
            <div className="flex flex-col items-start justify-start space-y-4">
              <Badge variant="default">有効</Badge>
              <p className="text-muted-foreground">
                二段階認証を有効にすると、パスワードログインでも Google
                ログインでも、ログイン時に安全な確認コードの入力が必要になります。コードはスマートフォンの
                TOTP 対応アプリで確認できます。
              </p>

              <TwoFactorRecoveryCodes
                recoveryCodesList={recoveryCodesList}
                fetchRecoveryCodes={fetchRecoveryCodes}
                errors={errors}
              />

              <div className="relative inline">
                <Form {...disable.form()}>
                  {({ processing }) => (
                    <Button
                      variant="destructive"
                      type="submit"
                      disabled={processing}
                    >
                      <ShieldBan /> Disable 2FA
                    </Button>
                  )}
                </Form>
              </div>
            </div>
          ) : (
            <div className="flex flex-col items-start justify-start space-y-4">
              <Badge variant="destructive">無効</Badge>
              <p className="text-muted-foreground">
                二段階認証を有効にすると、パスワードと Google
                のどちらのログイン方法でも確認コードの入力が必要になります。コードはスマートフォンの
                TOTP 対応アプリで確認できます。
              </p>

              <div>
                {hasSetupData ? (
                  <Button onClick={() => setShowSetupModal(true)}>
                    <ShieldCheck />
                    設定を続ける
                  </Button>
                ) : (
                  <Form
                    {...enable.form()}
                    onSuccess={() => setShowSetupModal(true)}
                  >
                    {({ processing }) => (
                      <Button type="submit" disabled={processing}>
                        <ShieldCheck />
                        二段階認証を有効にする
                      </Button>
                    )}
                  </Form>
                )}
              </div>
            </div>
          )}

          <TwoFactorSetupModal
            isOpen={showSetupModal}
            onClose={() => setShowSetupModal(false)}
            requiresConfirmation={requiresConfirmation}
            twoFactorEnabled={twoFactorEnabled}
            qrCodeSvg={qrCodeSvg}
            manualSetupKey={manualSetupKey}
            clearSetupData={clearSetupData}
            fetchSetupData={fetchSetupData}
            errors={errors}
          />
        </div>
      </SettingsLayout>
    </AppLayout>
  )
}

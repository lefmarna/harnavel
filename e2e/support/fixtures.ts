import { expect, test as base } from '@playwright/test'

type PageErrorFixtures = {
  pageErrors: void
}

const formatPageError = (error: Error): string => error.stack ?? error.message

/**
 * 全 spec で `page` の `pageerror`（未捕捉の JS 例外）を収集し、テスト終了時にゼロであることを検証する。
 * `../support/fixtures` から `test` を import するだけで、明示的なアサーションなしに
 * ページ上のランタイムエラーを検知できる（`{ auto: true }` により自動適用）。
 */
export const test = base.extend<PageErrorFixtures>({
  pageErrors: [
    async ({ page }, use) => {
      const pageErrors: Error[] = []
      const collectPageError = (error: Error): void => {
        pageErrors.push(error)
      }

      page.on('pageerror', collectPageError)

      await use()

      page.off('pageerror', collectPageError)

      expect(pageErrors.map(formatPageError)).toEqual([])
    },
    { auto: true },
  ],
})

export { expect }

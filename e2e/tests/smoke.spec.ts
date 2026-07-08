import { expect, test } from '../support/fixtures'

test('トップページが正常に表示される', async ({ page }) => {
  const response = await page.goto('/')
  expect(response?.status()).toBe(200)
})

test('認証済みユーザーでダッシュボードが正常に表示される', async ({ page }) => {
  const response = await page.goto('/_e2e/auth/login-verified')

  expect(response?.status()).toBeLessThan(400)
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.getByText('ダッシュボード').first()).toBeVisible()
})

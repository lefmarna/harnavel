import { expect, test } from '../support/fixtures'

test('トップページが正常に表示される', async ({ page }) => {
  const response = await page.goto('/')
  expect(response?.status()).toBe(200)
})

test('認証済みユーザーでダッシュボードが正常に表示される', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('メールアドレス').fill('test@example.com')
  await page.getByLabel('パスワード').fill('password')
  await page.getByRole('button', { name: 'ログイン' }).click()

  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.getByText('ダッシュボード').first()).toBeVisible()
})

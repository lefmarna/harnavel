import { expect, test } from '../support/fixtures'

test('Googleログイン連携の確認メール送信後ページが表示される', async ({
  page,
}) => {
  const response = await page.goto('/_e2e/auth/google-link/confirm')

  expect(response?.status()).toBeLessThan(400)
  await expect(
    page.getByRole('heading', { name: '確認メールを送信しました' }),
  ).toBeVisible()
})

test('Googleログイン連携の署名付き確認ページが表示される', async ({ page }) => {
  const response = await page.goto('/_e2e/auth/google-link/verify')

  expect(response?.status()).toBeLessThan(400)
  await expect(page.getByText('Googleログインを連携する')).toBeVisible()
})

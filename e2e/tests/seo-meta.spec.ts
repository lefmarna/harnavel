import { expect, test } from '@playwright/test'

test('SPA遷移時にSEOメタタグが更新される', async ({ page }) => {
  // Welcome ページに直接アクセス
  await page.goto('/')

  const welcomeOgUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')

  expect(welcomeOgUrl).toContain('/')

  // Login ページへ SPA 遷移（フルリロードなし）
  await page.click('a[href*="login"]')
  await expect(page).toHaveURL(/\/login$/)

  const loginOgTitle = page.locator('meta[property="og:title"]')
  await expect(loginOgTitle).toHaveAttribute('content', /Log in - /)

  const loginDescription = page.locator('meta[name="description"]')
  await expect(loginDescription).toHaveAttribute(
    'content',
    'Log in to your account.',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex, nofollow',
  )

  const loginOgUrl = page.locator('meta[property="og:url"]')
  await expect(loginOgUrl).toHaveAttribute('content', /\/login$/)

  // og:url が遷移前と異なることを確認
  expect(await loginOgUrl.getAttribute('content')).not.toBe(welcomeOgUrl)

  // twitter:title も同期していることを確認
  const loginTwitterTitle = page.locator('meta[name="twitter:title"]')
  await expect(loginTwitterTitle).toHaveAttribute('content', /Log in - /)

  // og:description も遷移後に更新されていることを確認
  const loginOgDescription = page.locator('meta[property="og:description"]')
  await expect(loginOgDescription).toHaveAttribute(
    'content',
    'Log in to your account.',
  )
})

test('パスワードリセットページのog:urlにクエリ文字列が含まれない', async ({
  page,
}) => {
  // password.reset ページにクエリ付きで直接アクセス
  await page.goto('/reset-password/test-token?email=test%40example.com')

  // ハイドレーション後の og:url を検証
  const ogUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')

  expect(ogUrl).not.toBeNull()
  expect(ogUrl).not.toContain('?')
  expect(ogUrl).not.toContain('email=')
  expect(ogUrl).toContain('/reset-password/')
})

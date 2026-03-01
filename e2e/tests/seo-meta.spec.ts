import { expect, test } from '@playwright/test'

test('SPA遷移時にSEOメタタグが更新される', async ({ page }) => {
  // Welcome ページに直接アクセス
  await page.goto('/')
  await expect(page).toHaveTitle(/Welcome - /)

  const welcomeOgUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')

  expect(welcomeOgUrl).toContain('/')

  // Login ページへ SPA 遷移（フルリロードなし）
  await page.click('a[href*="login"]')
  await expect(page).toHaveTitle(/Log in - /)

  const loginOgTitle = await page
    .locator('meta[property="og:title"]')
    .getAttribute('content')
  expect(loginOgTitle).toContain('Log in - ')

  const loginDescription = await page
    .locator('meta[name="description"]')
    .getAttribute('content')
  // Login ページは seo.description = 'Log in to your account.' を持つ
  expect(loginDescription).toBe('Log in to your account.')

  const loginOgUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')
  expect(loginOgUrl).toContain('/login')

  // og:url が遷移前と異なることを確認
  expect(loginOgUrl).not.toBe(welcomeOgUrl)

  // twitter:title も同期していることを確認
  const loginTwitterTitle = await page
    .locator('meta[name="twitter:title"]')
    .getAttribute('content')
  expect(loginTwitterTitle).toContain('Log in - ')

  // og:description も遷移後に更新されていることを確認
  const loginOgDescription = await page
    .locator('meta[property="og:description"]')
    .getAttribute('content')
  expect(loginOgDescription).toBe('Log in to your account.')
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

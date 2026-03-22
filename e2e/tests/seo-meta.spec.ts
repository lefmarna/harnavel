import { expect, test } from '@playwright/test'

test('SPA遷移時にSEOメタタグが更新される', async ({ page }) => {
  // Welcome ページに直接アクセス
  await page.goto('/')

  await expect(page).toHaveTitle(/ホーム - /)

  const welcomeOgUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')
  const welcomeCanonical = page.locator('link[rel="canonical"]')

  expect(welcomeOgUrl).toContain('/')
  await expect(welcomeCanonical).toHaveAttribute('href', /\/$/)

  // Login ページへ SPA 遷移（フルリロードなし）
  await page.click('a[href*="login"]')
  await expect(page).toHaveURL(/\/login$/)
  await expect(page).toHaveTitle(/ログイン - /)

  const loginOgTitle = page.locator('meta[property="og:title"]')
  await expect(loginOgTitle).toHaveAttribute('content', /ログイン - /)

  const loginDescription = page.locator('meta[name="description"]')
  await expect(loginDescription).toHaveAttribute(
    'content',
    'メールアドレスとパスワードを入力してログインします。',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex, nofollow',
  )

  const loginCanonical = page.locator('link[rel="canonical"]')
  await expect(loginCanonical).toHaveAttribute('href', /\/login$/)

  const loginOgUrl = page.locator('meta[property="og:url"]')
  await expect(loginOgUrl).toHaveAttribute('content', /\/login$/)

  // og:url が遷移前と異なることを確認
  expect(await loginOgUrl.getAttribute('content')).not.toBe(welcomeOgUrl)

  // twitter:title も同期していることを確認
  const loginTwitterTitle = page.locator('meta[name="twitter:title"]')
  await expect(loginTwitterTitle).toHaveAttribute('content', /ログイン - /)

  // og:description も遷移後に更新されていることを確認
  const loginOgDescription = page.locator('meta[property="og:description"]')
  await expect(loginOgDescription).toHaveAttribute(
    'content',
    'メールアドレスとパスワードを入力してログインします。',
  )
})

test('authページでnoindexとnofollowが維持される', async ({ page }) => {
  await page.goto('/login')

  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex, nofollow',
  )
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    /\/login$/,
  )
  await expect(page.locator('meta[property="og:url"]')).toHaveAttribute(
    'content',
    /\/login$/,
  )
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    'content',
    /ログイン - /,
  )
})

test('パスワードリセットページのcanonicalとog:urlにクエリ文字列が含まれない', async ({
  page,
}) => {
  // password.reset ページにクエリ付きで直接アクセス
  await page.goto('/reset-password/test-token?email=test%40example.com')

  const canonical = await page
    .locator('link[rel="canonical"]')
    .getAttribute('href')

  // ハイドレーション後の og:url を検証
  const ogUrl = await page
    .locator('meta[property="og:url"]')
    .getAttribute('content')

  expect(canonical).not.toBeNull()
  expect(canonical).not.toContain('?')
  expect(canonical).not.toContain('email=')
  expect(canonical).toContain('/reset-password/')

  expect(ogUrl).not.toBeNull()
  expect(ogUrl).not.toContain('?')
  expect(ogUrl).not.toContain('email=')
  expect(ogUrl).toContain('/reset-password/')
})

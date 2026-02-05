import { expect, test } from '@playwright/test'

test('トップページが正常に表示される', async ({ page }) => {
  const response = await page.goto('/')
  expect(response?.status()).toBe(200)
})

import { expect, test } from '@playwright/test'

test('knowledge content removes executable markup', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.setItem('misaka.access_token', 'test-token')
    ;(window as any).__knowledgeXss = 0
  })

  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    const data = path.endsWith('/user/knowledge/fetch')
      ? {
          安全: [{
            id: 1,
            category: '安全',
            title: '安全指南',
            updated_at: 1_700_000_000,
            body: '<script>window.__knowledgeXss=1</script><svg onload="window.__knowledgeXss=2"></svg><p><br></p><ul><li>正常列表</li></ul><p>&nbsp;</p><ul><li>第二行</li></ul><a href="javascript:window.__knowledgeXss=3" onclick="window.__knowledgeXss=4">链接</a><strong>正常内容</strong>',
          }],
        }
      : path.endsWith('/user/info')
        ? { email: 'test@example.com' }
        : { currency_symbol: '¥' }

    await route.fulfill({ json: { data } })
  })

  await page.goto('/#/knowledge')
  await page.getByRole('button', { name: /安全指南/ }).click()

  const modal = page.getByRole('dialog')
  await expect(modal).toContainText('正常内容')
  await expect(modal.locator('.knowledge-modal-body script, .knowledge-modal-body svg, .knowledge-modal-body [onload], .knowledge-modal-body [onclick]')).toHaveCount(0)
  await expect.poll(() => modal.locator('.knowledge-modal-body p').evaluateAll((paragraphs) => paragraphs.every((paragraph) => Boolean(paragraph.textContent?.replace(/\u00a0/g, ' ').trim())))).toBe(true)
  await expect(modal.locator('.knowledge-modal-body ul')).toHaveCount(1)
  await expect(modal.locator('.knowledge-modal-body ul ul')).toHaveCount(0)
  await expect(modal.locator('.knowledge-modal-body a', { hasText: '链接' })).not.toHaveAttribute('href')
  await expect.poll(() => page.evaluate(() => (window as any).__knowledgeXss)).toBe(0)
})

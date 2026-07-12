import { expect, test, type Page } from '@playwright/test'

async function navigate(page: Page, href: string) {
  const menu = page.locator('.mobile-menu')
  if (await menu.isVisible()) {
    await menu.click()
    await expect(page.locator('.sidebar.open')).toHaveCount(1)
  }
  const link = page.locator(`.sidebar-nav a[href="#${href}"]`)
  await expect(link).toHaveCount(1)
  await link.evaluate((element: HTMLAnchorElement) => element.click())
}

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('misaka.access_token', 'test-token'))
  await page.route('**/api/v1/**', async (route) => {
    const url = route.request().url()
    let data: any = []
    if (url.includes('/user/info')) data = { email: 'user@example.com', plan: { name: '标准套餐' }, transfer_enable: 536870912000, expired_at: 1783700000 }
    else if (url.includes('/getSubscribe')) data = { u: 10737418240, d: 21474836480, transfer_enable: 536870912000, subscribe_url: 'https://example.com/sub', expired_at: 1783700000, next_reset_at: 1781108000, plan: { name: '标准套餐', transfer_enable: 536870912000, speed_limit: 300, device_limit: 5, content: '全球节点与流媒体解锁' } }
    else if (url.includes('/getTrafficLog')) data = [{ record_at: 1780500000, u: 1073741824, d: 2147483648 }, { record_at: 1780586400, u: 2147483648, d: 3221225472 }]
    else if (url.includes('/server/fetch')) data = [{ id: 1, name: '香港 HKG 01', type: 'Shadowsocks', rate: 1, is_online: true, last_check_at: Math.floor(Date.now() / 1000), tags: ['香港', 'BGP'] }, { id: 2, name: '东京 NRT 02', type: 'VLESS', rate: 1.5, is_online: false, last_check_at: 1780500000, tags: ['日本'] }]
    else if (url.includes('/invite/fetch')) data = { codes: [{ code: 'DEMO2026', pv: 3, status: 0, created_at: 1780500000 }], stat: [2, 1200, 0, 10, 1200] }
    else if (url.includes('/invite/details')) data = { data: [{ id: 1, trade_no: 'T20260712001', order_amount: 6800, get_amount: 680, created_at: 1780500000, invited_user: { email: 'd***@example.com', joined_at: 1780000000 } }] }
    await route.fulfill({ json: { status: 'success', data } })
  })
  await page.goto('/#/dashboard')
})

test('switches theme modes and persists selection', async ({ page }) => {
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await page.getByRole('button', { name: '外观主题' }).click()
  await page.getByRole('button', { name: '暗色' }).click()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await expect.poll(() => page.evaluate(() => localStorage.getItem('misaka.theme-mode'))).toBe('dark')
})

test('renders subscription imports and plan information', async ({ page }) => {
  await navigate(page, '/subscription')
  await expect(page.locator('.client-import')).toHaveCount(3)
  await expect(page.locator('.client-import em')).toHaveCount(3)
  await expect(page.locator('.subscription-overview')).toContainText('标准套餐')
})

test('renders traffic chart, node lights, and invitee detail', async ({ page }) => {
  await navigate(page, '/traffic')
  await expect(page.locator('.traffic-chart')).toBeVisible()
  await expect(page.locator('.traffic-dates button')).toHaveCount(2)
  await navigate(page, '/servers')
  await expect(page.locator('.node-status.online')).toHaveCount(1)
  await expect(page.locator('.node-status.offline')).toHaveCount(1)
  await navigate(page, '/invite')
  await page.getByRole('button', { name: '查询被邀请人' }).click()
  await expect(page.locator('.invite-details')).toContainText('d***@example.com')
})

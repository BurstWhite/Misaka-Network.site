import { expect, test, type Page } from '@playwright/test'
import { readFile } from 'node:fs/promises'
import { basename, resolve } from 'node:path'

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
  await page.addInitScript(() => { localStorage.setItem('misaka.access_token', 'test-token'); (window as any).__noticeXss = 0 })
  await page.route('**/assets/flags/*.svg', async (route) => {
    const filename = basename(new URL(route.request().url()).pathname)
    await route.fulfill({ contentType: 'image/svg+xml', body: await readFile(resolve(process.cwd(), '../public/assets/flags', filename)) })
  })
  let savedCoupons: any[] = []
  await page.route('**/api/v1/**', async (route) => {
    const url = route.request().url()
    let data: any = []
    if (url.includes('/user/info')) data = { email: 'user@example.com', plan: { name: '标准套餐' }, transfer_enable: 536870912000, expired_at: 1783700000 }
    else if (url.includes('/getSubscribe')) data = { u: 10737418240, d: 21474836480, transfer_enable: 536870912000, subscribe_url: 'https://example.com/sub', expired_at: 1783700000, next_reset_at: 1781108000, plan: { name: '标准套餐', transfer_enable: 500, speed_limit: 300, device_limit: 5, content: '### 套餐权益\n\n- 全球节点\n- 流媒体解锁' } }
    else if (url.includes('/getTrafficLog')) data = Array.from({ length: 20 }, (_, index) => ({ record_at: Math.floor(Date.now() / 86400000) * 86400 - (19 - index) * 86400, u: (index + 1) * 107374182, d: (index + 2) * 107374182 }))
    else if (url.includes('/notice/fetch')) data = [{ id: 1, title: '欢迎使用', created_at: 1780500000, content: `### 服务说明\n\n**安全内容**\n\n<script>window.__noticeXss=1</script><a href="javascript:window.__noticeXss=2" onclick="window.__noticeXss=3">不安全链接</a>\n\n${'公告正文。\n\n'.repeat(80)}` }]
    else if (url.includes('/plan/fetch')) data = [{ id: 1, name: 'Premium', month_price: 3000, transfer_enable: 200, speed_limit: 1000, device_limit: 10, content: '#### 📦 套餐详情\n\n- **流量**：200 GB / 月\n\n- **速度限制**：1000 Mbps\n\n- **同时在线设备**：最多 **10 台**\n\n#### 🌟 为什么推荐给你？\n\n- **如果你是**：\n\n  - 经常下载大型游戏\n\n  - 需要频繁上传高清视频\n\n<ul><li><p>HTML 套餐说明第一行</p></li><li><p>HTML 套餐说明第二行</p></li></ul>' }]
    else if (url.includes('/coupon/saved')) data = savedCoupons
    else if (url.includes('/coupon/save')) {
      const code = String(route.request().postDataJSON()?.code || '')
      const coupon = code === 'SAVE5'
        ? { id: 2, code, name: '立减五元券', type: 1, value: 500, ended_at: 1783700000 }
        : { id: 1, code, name: '夏日八折券', type: 2, value: 20, ended_at: 1783700000 }
      if (!savedCoupons.some((item) => item.id === coupon.id)) savedCoupons.push(coupon)
      data = coupon
    }
    else if (url.includes('/coupon/remove')) {
      const couponId = route.request().postDataJSON()?.coupon_id
      savedCoupons = savedCoupons.filter((coupon) => String(coupon.id) !== String(couponId))
      data = true
    }
    else if (url.includes('/coupon/best')) data = savedCoupons.length ? { ...savedCoupons[0], discount_amount: 600 } : null
    else if (url.includes('/coupon/check')) {
      const code = String(route.request().postDataJSON()?.code || '')
      data = { code, name: '手动优惠券', type: 2, value: 10, ended_at: 1783700000 }
    }
    else if (url.includes('/getActiveSession')) data = [{ id: 1, device: 'Safari · macOS', ip: '203.0.113.42', current: true, last_login_at: 1780500000 }]
    else if (url.includes('/server/fetch')) data = [{ id: 1, name: '香港 HKG 01', type: 'Shadowsocks', rate: 1, is_online: true, last_check_at: Math.floor(Date.now() / 1000), tags: ['香港', 'BGP'] }, { id: 2, name: '东京 NRT 02', type: 'VLESS', rate: 1.5, is_online: false, last_check_at: '1780500000', tags: ['日本'] }]
    else if (url.includes('/invite/fetch')) data = { codes: [{ code: 'DEMO2026', pv: 3, status: 0, created_at: 1780500000 }], stat: [2, 1200, 0, 10, 1200] }
    else if (url.includes('/invite/details')) data = { data: [{ id: 1, trade_no: 'T20260712001', order_amount: 6800, get_amount: 680, created_at: 1780500000, invited_user: { email: 'd***@example.com', invite_code: 'DEMO2026', joined_at: 1780000000 } }] }
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
  await expect(page.locator('.plan-details')).toContainText('500.00 GB')
})

test('renders traffic chart, node states, and invitee detail', async ({ page }) => {
  await expect(page.locator('.chart-panel .traffic-point')).toHaveCount(7)
  await expect(page.locator('.chart-panel .traffic-axis span')).toHaveCount(7)
  expect(await page.locator('.usage-chart').evaluate((element) => { const box = element.getBoundingClientRect(); return box.width / box.height })).toBeCloseTo(680 / 150, 2)
  await expect.poll(() => page.locator('.chart-panel .traffic-point > circle:first-child').evaluateAll((circles) => circles.every((circle) => circle.getAttribute('r') === '4'))).toBe(true)
  await expect(page.locator('.dashboard-node-table-panel tbody tr').nth(1).locator('td').last()).toContainText('2026')
  await expect(page.locator('.dashboard-node-table-panel')).not.toContainText('1780500000')
  await navigate(page, '/traffic')
  await expect(page.locator('.traffic-chart')).toBeVisible()
  await expect(page.locator('.traffic-point')).toHaveCount(14)
  await expect(page.locator('.traffic-panel .traffic-axis span')).toHaveCount(14)
  await expect(page.locator('.traffic-panel .traffic-axis')).toContainText(/\d+\.\d+/)
  await navigate(page, '/servers')
  await expect(page.locator('.dashboard-node-item')).toHaveCount(2)
  await expect(page.locator('.dashboard-node-code img')).toHaveCount(2)
  await expect(page.locator('.dashboard-node-code img').nth(0)).toHaveAttribute('src', '/assets/flags/hk.svg')
  await expect(page.locator('.dashboard-node-code img').nth(1)).toHaveAttribute('src', '/assets/flags/jp.svg')
  await expect.poll(() => page.locator('.dashboard-node-code img').evaluateAll((images) => images.every((image) => (image as HTMLImageElement).complete && (image as HTMLImageElement).naturalWidth > 0))).toBe(true)
  await expect(page.locator('.dashboard-node-summary')).toContainText('1 / 2 在线')
  await navigate(page, '/invite')
  await page.getByRole('button', { name: /DEMO2026/ }).click()
  await expect(page.locator('.invite-inline-details')).toContainText('d***@example.com')
})

test('renders backend plans, scrollable rich notices, status help, and real sessions', async ({ page }) => {
  await page.locator('.notice-item').first().click()
  const notice = page.getByRole('dialog')
  await expect(notice.locator('h4')).toContainText('服务说明')
  await expect(notice.locator('strong')).toContainText('安全内容')
  await expect(notice.locator('.notice-modal-body script, .notice-modal-body [onclick]')).toHaveCount(0)
  await expect(notice.getByText('不安全链接')).not.toHaveAttribute('href')
  await expect.poll(() => page.evaluate(() => (window as any).__noticeXss)).toBe(0)
  await expect.poll(() => notice.locator('.notice-modal-body').evaluate((element) => element.scrollHeight > element.clientHeight)).toBe(true)
  await page.getByRole('button', { name: '关闭公告' }).click()

  await page.locator('.node-status-legend').focus()
  await expect(page.locator('.node-status-tooltip')).toContainText('绿色 · 在线')
  await expect(page.locator('.node-status-tooltip')).toContainText('黄色 · 待确认')
  await expect(page.locator('.node-status-tooltip')).toContainText('红色 · 离线')

  await navigate(page, '/plans')
  await expect(page.locator('.plan-card')).toContainText('200.00 GB')
  await expect(page.locator('.plan-card')).toContainText('1000 Mbps')
  await expect(page.locator('.plan-card')).toContainText('同时在线设备')
  await expect(page.locator('.plan-card')).toContainText('为什么推荐给你')
  const planItemSpacings = await page.locator('.plan-card-content li').evaluateAll((elements) => elements.map((element) => {
    const style = getComputedStyle(element)
    return { lineHeight: Number.parseFloat(style.lineHeight), marginTop: Number.parseFloat(style.marginTop), marginBottom: Number.parseFloat(style.marginBottom) }
  }))
  expect(planItemSpacings.length).toBeGreaterThan(3)
  expect(planItemSpacings.every((spacing) => {
    return spacing.lineHeight < 22 && spacing.marginTop === 0 && spacing.marginBottom === 0
  })
  ).toBe(true)
  const planListParagraphMargins = await page.locator('.plan-card-content li > p').evaluateAll((elements) => elements.map((element) => {
    const style = getComputedStyle(element)
    return [Number.parseFloat(style.marginTop), Number.parseFloat(style.marginBottom)]
  }))
  expect(planListParagraphMargins.length).toBeGreaterThan(0)
  expect(planListParagraphMargins.every((margins) => margins.every((margin) => margin === 0))).toBe(true)

  await navigate(page, '/gifts')
  await page.getByPlaceholder('输入管理员创建的优惠码').fill('SAVE20')
  await page.getByRole('button', { name: '验证并保存' }).click()
  await expect(page.locator('.coupon-preview')).toContainText('夏日八折券')
  await page.getByPlaceholder('输入管理员创建的优惠码').fill('SAVE5')
  await page.getByRole('button', { name: '验证并保存' }).click()
  await expect(page.locator('.saved-coupon-list .coupon-preview')).toHaveCount(2)
  const fixedCoupon = page.locator('.saved-coupon-list .coupon-preview').filter({ hasText: 'SAVE5' })
  await fixedCoupon.getByRole('button', { name: '删除' }).click()
  await expect(page.locator('.saved-coupon-list .coupon-preview')).toHaveCount(1)
  await page.getByRole('button', { name: '购买套餐' }).click()
  await page.locator('.plan-card').click()
  await expect(page.locator('.coupon-preview')).toContainText('夏日八折券')
  await expect(page.locator('.coupon-preview')).toContainText('已自动选择账号最优券')
  await expect(page.locator('.coupon-preview')).toContainText('优惠后 ¥ 24.00')
  await page.getByPlaceholder('也可输入优惠码').fill('MANUAL10')
  await page.getByRole('button', { name: '验证', exact: true }).click()
  await expect(page.locator('.coupon-preview')).toContainText('已验证手动优惠码')
  await expect(page.locator('.coupon-preview')).toContainText('优惠后 ¥ 27.00')
  await page.locator('.plan-modal .icon-button').click()

  await navigate(page, '/profile')
  await expect(page.locator('.session-row')).toContainText('Safari · macOS')
  await expect(page.locator('.session-row')).toContainText('203.0.113.42')
  await expect(page.locator('.session-row')).toContainText('当前设备')
})

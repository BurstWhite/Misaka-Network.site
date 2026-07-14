<script setup lang="ts">
/* eslint-disable vue/no-v-html -- renderRichText applies a strict DOM allowlist before rendering. */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { commerceApi, serviceApi, userApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import Icon from '@/shared/Icon.vue'
import NodeStatusLegend from '@/shared/NodeStatusLegend.vue'
import { bytes, date, money, orderStatus } from '@/shared/format'
import { renderRichText } from '@/shared/rich-text'
import { content } from '@/shared/content'
import { aggregateTrafficByDay } from '@/shared/traffic'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
const user = ref<any>({})
const subscribe = ref<any>({})
const notices = ref<any[]>([])
const orders = ref<any[]>([])
const traffic = ref<any[]>([])
const servers = ref<any[]>([])
const selectedNotice = ref<any>(null)
const noticeModal = ref<HTMLElement | null>(null)
const chartFocus = ref(0)
const chartHover = ref(false)
let noticeOpener: HTMLElement | null = null
let previousBodyOverflow = ''
let previousBodyOverscroll = ''

const used = computed(() => Number(subscribe.value?.u || 0) + Number(subscribe.value?.d || 0))
const total = computed(() => Number(subscribe.value?.transfer_enable || user.value?.transfer_enable || 0))
const percent = computed(() => total.value ? Math.min(100, used.value / total.value * 100) : 0)

async function load() {
  loading.value = true
  error.value = ''
  chartFocus.value = 0
  chartHover.value = false
  try {
    const [, s, n, o, tr, sv] = await Promise.all([
      auth.loadUser(),
      userApi.subscribe(),
      serviceApi.notices().catch(() => []),
      commerceApi.orders().catch(() => []),
      serviceApi.traffic({ days: 7 }).catch(() => []),
      serviceApi.servers().catch(() => []),
    ])
    user.value = auth.user || {}
    subscribe.value = s || {}
    notices.value = Array.isArray(n) ? n : []
    orders.value = Array.isArray(o) ? o : o?.data || []
    traffic.value = Array.isArray(tr) ? tr : tr?.data || []
    servers.value = Array.isArray(sv) ? sv : []
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openNotice(notice: any, event: MouseEvent) {
  noticeOpener = event.currentTarget as HTMLElement
  selectedNotice.value = notice
}
function closeNotice() { selectedNotice.value = null }
function noticeTags(notice: any): string[] { return Array.isArray(notice?.tags) ? notice.tags : [] }
function showChartPoint(index: number) {
  chartFocus.value = index
  chartHover.value = true
}

function serverStatus(server: any) {
  if (!server?.is_online) return { key: 'offline', label: '离线' }
  if (server.last_check_at && Date.now() / 1000 - Number(server.last_check_at) > 180) return { key: 'warming', label: '待确认' }
  return { key: 'online', label: '在线' }
}

onMounted(load)
watch(selectedNotice, async (value) => {
  if (value) {
    previousBodyOverflow = document.body.style.overflow
    previousBodyOverscroll = document.body.style.overscrollBehavior
    document.body.style.overflow = 'hidden'
    document.body.style.overscrollBehavior = 'none'
    await nextTick()
    noticeModal.value?.focus()
    return
  }

  document.body.style.overflow = previousBodyOverflow
  document.body.style.overscrollBehavior = previousBodyOverscroll
  noticeOpener?.focus()
  noticeOpener = null
})
onBeforeUnmount(() => {
  document.body.style.overflow = previousBodyOverflow
  document.body.style.overscrollBehavior = previousBodyOverscroll
})

const trafficPoints = computed(() => {
  const rows = aggregateTrafficByDay(traffic.value, 7)
  const max = Math.max(1, ...rows.map((item) => item.amount))
  const width = Math.max(1, rows.length - 1)
  return rows.map((item, index) => {
    return { ...item, x: 10 + index / width * 660, y: 140 - item.amount / max * 105 }
  })
})
const trafficHasData = computed(() => trafficPoints.value.some((point) => point.amount > 0))
const chartLine = computed(() => trafficPoints.value.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.y}`).join(' '))
const chartArea = computed(() => trafficPoints.value.length ? `${chartLine.value} L 670 140 L 10 140 Z` : '')
const activeTraffic = computed(() => trafficPoints.value[chartFocus.value] || trafficPoints.value.at(-1))
const chartTooltipTransform = computed(() => {
  const point = activeTraffic.value
  if (!point) return 'translate(8 6)'
  const x = Number(point.x)
  const y = Number(point.y)
  const left = x > 510 ? x - 176 : x + 14
  const top = y < 62 ? y + 16 : y - 84
  return `translate(${Math.min(508, Math.max(8, left))} ${Math.min(70, Math.max(6, top))})`
})
const onlineServerCount = computed(() => servers.value.filter((server) => serverStatus(server).key === 'online').length)
</script>

<template>
  <div class="dashboard-view">
    <PageState :loading="loading" :error="error" @retry="load">
      <div class="page-heading"><div><h1>{{ t('dashboard.greeting') }}</h1><p>{{ user.email || 'Misaka Network' }}</p></div></div>
      <section class="subscription-hero">
        <div class="hero-plan"><span class="plan-icon"><Icon name="card" :size="30"/></span><div><small>{{ t('dashboard.subscription') }}</small><h2>{{ subscribe.plan?.name || '暂无有效订阅' }}</h2><p v-if="subscribe.expired_at">有效期至 {{ date(subscribe.expired_at) }}</p></div></div>
        <div class="hero-metric"><small>{{ t('dashboard.remaining') }}</small><strong>{{ bytes(Math.max(0, total - used)) }}</strong><div class="progress"><i :style="{ width: `${percent}%` }"/></div><p>{{ bytes(used) }} / {{ bytes(total) }}</p></div>
        <div class="hero-metric"><small>{{ t('dashboard.reset') }}</small><strong class="date-value">{{ date(subscribe.next_reset_at || subscribe.expired_at) }}</strong><p>{{ percent.toFixed(1) }}% 已使用</p></div>
        <div class="hero-actions"><RouterLink class="button secondary" to="/subscription">{{ t('dashboard.view') }}</RouterLink><RouterLink class="button primary" to="/plans">{{ t('dashboard.renew') }}</RouterLink></div>
      </section>
      <section class="quick-actions">
        <RouterLink to="/plans"><span class="action-icon blue"><Icon name="cart"/></span><div><strong>{{ t('dashboard.buy') }}</strong><small>选择最合适的套餐</small></div><Icon name="chevron"/></RouterLink>
        <RouterLink to="/subscription"><span class="action-icon cyan"><Icon name="card"/></span><div><strong>{{ t('dashboard.import') }}</strong><small>一键导入订阅到客户端</small></div><Icon name="chevron"/></RouterLink>
        <RouterLink to="/tickets"><span class="action-icon purple"><Icon name="ticket"/></span><div><strong>{{ t('dashboard.ticket') }}</strong><small>技术问题提交与跟进</small></div><Icon name="chevron"/></RouterLink>
      </section>
      <div class="dashboard-grid">
        <div class="dashboard-main">
          <section class="panel chart-panel">
            <header><div><h2>{{ t('dashboard.usage') }}</h2><p>{{ content('dashboard.usage.description', '悬浮折线节点查看当日用量') }}</p></div><span>{{ trafficPoints.length }} 天</span></header>
            <svg class="usage-chart" viewBox="0 0 680 150" preserveAspectRatio="xMidYMid meet" role="img" aria-label="每日流量使用折线图" @mouseleave="chartHover = false">
              <defs><linearGradient id="dashboard-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1bb8d1" stop-opacity=".25"/><stop offset="1" stop-color="#1bb8d1" stop-opacity="0"/></linearGradient></defs>
              <line v-for="y in [35,70,105,140]" :key="y" x1="10" x2="670" :y1="y" :y2="y" stroke="var(--border)" stroke-dasharray="3 5"/>
              <path :d="chartArea" fill="url(#dashboard-area)"/><path :d="chartLine" fill="none" stroke="#13aeca" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
              <g v-for="(point, index) in trafficPoints" :key="point.key" class="traffic-point"><circle class="traffic-node" :cx="point.x" :cy="point.y" r="4" vector-effect="non-scaling-stroke"/><circle class="traffic-hit-area" :cx="point.x" :cy="point.y" r="15" tabindex="0" :aria-label="`${date(point.timestamp)} ${bytes(point.amount)}`" @pointerenter="showChartPoint(index)" @mouseover="showChartPoint(index)" @focus="showChartPoint(index)"/></g>
              <Transition name="chart-fade"><g v-if="chartHover && activeTraffic" class="chart-tooltip" :transform="chartTooltipTransform" pointer-events="none"><line class="chart-tooltip-guide" :x1="Number(activeTraffic.x) < 510 ? 0 : 164" :y1="Number(activeTraffic.y) < 62 ? 0 : 74" :x2="Number(activeTraffic.x) < 510 ? -14 : 176" :y2="Number(activeTraffic.y) < 62 ? -16 : 84"/><rect width="164" height="74" rx="10"/><text class="tooltip-date" x="12" y="18">{{ date(activeTraffic.timestamp) }}</text><text x="12" y="37">上传 {{ bytes(activeTraffic.u) }}</text><text x="12" y="53">下载 {{ bytes(activeTraffic.d) }}</text><text class="tooltip-total" x="12" y="69">总计 {{ bytes(activeTraffic.amount) }}</text></g></Transition>
            </svg>
            <div class="traffic-axis" :style="{ gridTemplateColumns: `repeat(${trafficPoints.length}, minmax(0, 1fr))` }" aria-hidden="true"><span v-for="point in trafficPoints" :key="point.key">{{ point.label }}</span></div>
            <div class="chart-hint"><span v-if="trafficHasData">悬浮折线节点查看当日流量</span><span v-else>最近 7 天暂无流量记录</span></div>
          </section>
          <section class="panel"><header><h2>{{ t('dashboard.recentOrders') }}</h2><RouterLink to="/orders">{{ t('common.more') }}</RouterLink></header><div class="table-wrap"><table><thead><tr><th>订单号</th><th>套餐</th><th>金额</th><th>状态</th><th>创建时间</th></tr></thead><tbody><tr v-for="order in orders.slice(0, 4)" :key="order.trade_no"><td><RouterLink :to="`/order/${order.trade_no}`">#{{ order.trade_no }}</RouterLink></td><td>{{ order.plan?.name || order.plan_name || '-' }}</td><td>{{ money(order.total_amount ?? order.total) }}</td><td><span class="status success">{{ orderStatus(order.status) }}</span></td><td>{{ date(order.created_at, true) }}</td></tr><tr v-if="!orders.length"><td colspan="5" class="empty-cell">{{ t('common.empty') }}</td></tr></tbody></table></div></section>
        </div>
        <aside class="panel notice-panel"><header><h2>{{ t('dashboard.notices') }}</h2><span>{{ notices.length }}</span></header><button v-for="notice in notices.slice(0, 6)" :key="notice.id" type="button" class="notice-item" @click="openNotice(notice, $event)"><i/><div><strong>{{ notice.title }}</strong><time>{{ date(notice.created_at, true) }}</time></div><Icon name="chevron"/></button><p v-if="!notices.length" class="page-state">{{ t('common.empty') }}</p></aside>
      </div>
      <section class="panel dashboard-node-panel dashboard-node-table-panel">
        <header><div><h2>节点状态</h2><p>{{ content('dashboard.servers.description', '实时查看可用节点与最近心跳') }}</p></div><div class="dashboard-node-summary"><NodeStatusLegend/>{{ onlineServerCount }} / {{ servers.length }} 在线<RouterLink to="/servers">查看全部</RouterLink></div></header>
        <div class="table-wrap">
          <table class="server-table">
            <thead><tr><th>节点</th><th>协议</th><th>倍率</th><th>状态</th><th>在线状态检查时间</th></tr></thead>
            <tbody>
              <tr v-for="server in servers.slice(0, 6)" :key="server.id"><td><strong>{{ server.name }}</strong></td><td>{{ server.type || '-' }}</td><td>{{ server.rate || 1 }}×</td><td><span :class="['node-status', serverStatus(server).key]"><i/>{{ serverStatus(server).label }}</span></td><td>{{ date(server.last_check_at, true) }}</td></tr>
              <tr v-if="!servers.length"><td colspan="5" class="empty-cell">暂无节点</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </PageState>

    <Teleport to="body">
      <div v-if="selectedNotice" class="modal-backdrop notice-modal-backdrop" role="presentation" @click.self="closeNotice">
        <article ref="noticeModal" class="modal notice-modal" role="dialog" aria-modal="true" aria-labelledby="notice-modal-title" tabindex="-1" @keydown.esc.stop="closeNotice">
          <header><div><span class="notice-modal-kicker">公告详情</span><h2 id="notice-modal-title">{{ selectedNotice.title }}</h2><p>{{ date(selectedNotice.created_at, true) }}</p></div><button class="icon-button" type="button" aria-label="关闭公告" @click="closeNotice"><Icon name="x"/></button></header>
          <div v-if="noticeTags(selectedNotice).length" class="notice-modal-tags"><span v-for="tag in noticeTags(selectedNotice)" :key="tag">{{ tag }}</span></div>
          <div class="notice-modal-body knowledge-body" v-html="renderRichText(selectedNotice.content || selectedNotice.body || selectedNotice.message || '暂无公告内容', selectedNotice.title)"/>
          <footer><button class="button primary" type="button" @click="closeNotice">知道了</button></footer>
        </article>
      </div>
    </Teleport>
  </div>
</template>

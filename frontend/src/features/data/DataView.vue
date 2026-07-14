<script setup lang="ts">
/* eslint-disable vue/no-v-html -- renderRichText applies a strict DOM allowlist before rendering. */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { commerceApi, serviceApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import Icon from '@/shared/Icon.vue'
import NodeStatusLegend from '@/shared/NodeStatusLegend.vue'
import { displayNodeFlag } from '@/shared/catalog'
import { bytes, date, money } from '@/shared/format'
import { aggregateTrafficByDay } from '@/shared/traffic'
import { renderRichText } from '@/shared/rich-text'
import { content } from '@/shared/content'

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const inviteDetails = ref<any[]>([])
const detailsLoading = ref(false)
const expandedInviteCode = ref<string | null>(null)
const inviteDetailsLoaded = ref(false)
const inviteSaving = ref(false)
const couponCode = ref('')
const couponSaving = ref(false)
const couponRemovingId = ref<string | number | null>(null)
const couponError = ref('')
const message = ref('')
const knowledgeKeyword = ref('')
const chartFocus = ref(0)
const chartHover = ref(false)
const selectedServerId = ref<string | number | null>(null)
const selectedKnowledge = ref<any>(null)
const knowledgeModal = ref<HTMLElement | null>(null)
let knowledgeOpener: HTMLElement | null = null
let previousBodyOverflow = ''
const kind = computed(() => String(route.meta.kind))

const meta = computed<Record<string, [string, string]>>(() => ({
  invite: ['我的邀请', content('invite.description', '生成邀请码，点击邀请码查看对应的被邀请人信息与返佣记录。')],
  traffic: ['流量明细', content('traffic.description', '按日期查看近 14 天的上传、下载与总用量。')],
  servers: ['节点状态', content('servers.description', '实时查看可用节点、倍率与最近心跳。')],
  knowledge: ['使用文档', content('knowledge.description', '客户端配置、订阅导入与常见问题。')],
  gifts: ['优惠券', content('gifts.description', '保存账号优惠券，购买订阅时将自动验证并使用。')],
}))

const loaders: Record<string, () => Promise<any>> = {
  invite: serviceApi.invites,
  traffic: () => serviceApi.traffic({ days: 14 }),
  servers: serviceApi.servers,
  knowledge: () => serviceApi.knowledge({ keyword: knowledgeKeyword.value.trim() || undefined }),
  gifts: commerceApi.savedCoupons,
}

async function load() {
  loading.value = true
  error.value = ''
  couponError.value = ''
  message.value = ''
  chartFocus.value = 0
  chartHover.value = false
  selectedServerId.value = null
  selectedKnowledge.value = null
  expandedInviteCode.value = null
  inviteDetails.value = []
  inviteDetailsLoaded.value = false
  try {
    data.value = await (loaders[kind.value] || serviceApi.knowledge)()
    if (kind.value === 'servers') {
      const serverRows = Array.isArray(data.value) ? data.value : data.value?.data || []
      selectedServerId.value = serverRows[0]?.id ?? null
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function createInvite() {
  inviteSaving.value = true
  try {
    await serviceApi.createInvite()
    message.value = '邀请码已生成，点击邀请码即可查询对应的被邀请人。'
    await load()
  } catch (e: any) {
    error.value = e.message
  } finally {
    inviteSaving.value = false
  }
}

async function toggleInviteDetails(code: string) {
  if (expandedInviteCode.value === code) {
    expandedInviteCode.value = null
    return
  }

  expandedInviteCode.value = code
  if (inviteDetailsLoaded.value) return
  detailsLoading.value = true
  try {
    const result = await serviceApi.inviteDetails({ current: 1, page_size: 50 })
    inviteDetails.value = Array.isArray(result) ? result : result?.data || []
    inviteDetailsLoaded.value = true
  } catch (e: any) {
    error.value = e.message
  } finally {
    detailsLoading.value = false
  }
}

async function saveCoupon() {
  const code = couponCode.value.trim()
  if (!code || couponSaving.value || couponRemovingId.value !== null) return
  couponSaving.value = true
  couponError.value = ''
  message.value = ''
  try {
    await commerceApi.saveCoupon(code)
    data.value = await commerceApi.savedCoupons()
    couponCode.value = ''
    message.value = '优惠券已验证并保存到当前账号'
  } catch (e: any) {
    couponError.value = e.message
  } finally {
    couponSaving.value = false
  }
}

async function removeCoupon(couponId: string | number) {
  if (couponSaving.value || couponRemovingId.value !== null) return
  couponRemovingId.value = couponId
  couponError.value = ''
  message.value = ''
  try {
    await commerceApi.removeSavedCoupon(couponId)
    data.value = savedCoupons.value.filter((coupon) => String(coupon.id) !== String(couponId))
    message.value = '已删除账号保存的优惠券'
  } catch (e: any) {
    couponError.value = e.message
  } finally {
    couponRemovingId.value = null
  }
}

function couponValue(coupon: any): string {
  return Number(coupon?.type) === 2 ? `减免 ${Number(coupon.value || 0)}%` : `减免 ${money(coupon?.value || 0)}`
}

function buyWithSavedCoupon() {
  void router.push('/plans')
}

onMounted(load)
watch(kind, () => {
  knowledgeKeyword.value = ''
  void load()
})
watch(selectedKnowledge, async (value) => {
  if (value) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    knowledgeModal.value?.focus()
    return
  }

  document.body.style.overflow = previousBodyOverflow
  knowledgeOpener?.focus()
  knowledgeOpener = null
})
onBeforeUnmount(() => { document.body.style.overflow = previousBodyOverflow })

const rows = computed<any[]>(() => {
  if (kind.value === 'invite') return data.value?.codes || []
  if (kind.value === 'knowledge' && data.value && !Array.isArray(data.value)) {
    return Object.values(data.value).flatMap((group: any) => Array.isArray(group) ? group : [])
  }
  if (Array.isArray(data.value)) return data.value
  return data.value?.data || data.value?.records || []
})
const savedCoupons = computed<any[]>(() => {
  if (kind.value !== 'gifts' || !data.value) return []
  if (Array.isArray(data.value)) return data.value
  if (Array.isArray(data.value.data)) return data.value.data
  return [data.value]
})
const knowledgeGroups = computed(() => {
  if (kind.value !== 'knowledge' || !data.value || Array.isArray(data.value)) return []
  return Object.entries(data.value).map(([category, items]) => ({ category, items: Array.isArray(items) ? items : [] }))
})
const selectedServer = computed(() => rows.value.find((server) => String(server.id) === String(selectedServerId.value)) || rows.value[0] || null)
const onlineServerCount = computed(() => rows.value.filter((server) => serverStatus(server).key === 'online').length)

const inviteStats = computed(() => data.value?.stat || [])
const trafficRows = computed(() => aggregateTrafficByDay(rows.value, 14))
const trafficMax = computed(() => Math.max(1, ...trafficRows.value.map((item) => item.amount)))
const trafficTotal = computed(() => trafficRows.value.reduce((sum, item) => sum + item.amount, 0))
const trafficHasData = computed(() => trafficRows.value.some((item) => item.amount > 0))
const chartPoints = computed(() => trafficRows.value.map((item, index) => {
  const width = Math.max(1, trafficRows.value.length - 1)
  return { ...item, x: 28 + index / width * 624, y: 134 - item.amount / trafficMax.value * 98 }
}))
const chartLine = computed(() => chartPoints.value.map((item, index) => `${index ? 'L' : 'M'} ${item.x} ${item.y}`).join(' '))
const chartArea = computed(() => chartPoints.value.length ? `${chartLine.value} L 652 134 L 28 134 Z` : '')
const activeTraffic = computed(() => chartPoints.value[chartFocus.value] || chartPoints.value.at(-1))
const chartTooltipTransform = computed(() => {
  const point = activeTraffic.value
  if (!point) return 'translate(8 10)'
  const x = Number(point.x)
  const y = Number(point.y)
  const left = x > 510 ? x - 176 : x + 14
  const top = y < 62 ? y + 16 : y - 84
  return `translate(${Math.min(508, Math.max(8, left))} ${Math.min(76, Math.max(6, top))})`
})

function showChartPoint(index: number) {
  chartFocus.value = index
  chartHover.value = true
}

function inviteDetailsFor(code: string) {
  return inviteDetails.value.filter((item) => item.invited_user?.invite_code === code)
}

function serverStatus(server: any) {
  if (!server.is_online) return { key: 'offline', label: '离线' }
  if (server.last_check_at && Date.now() / 1000 - Number(server.last_check_at) > 180) return { key: 'warming', label: '待确认' }
  return { key: 'online', label: '在线' }
}
function openKnowledge(item: any, event: MouseEvent) {
  knowledgeOpener = event.currentTarget as HTMLElement
  selectedKnowledge.value = item
}
function closeKnowledge() { selectedKnowledge.value = null }
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading">
      <div><h1>{{ meta[kind]?.[0] }}</h1><p>{{ meta[kind]?.[1] }}</p></div>
      <form v-if="kind === 'knowledge'" class="knowledge-search page-actions" @submit.prevent="load">
        <input v-model="knowledgeKeyword" type="search" aria-label="搜索使用文档" placeholder="搜索文档标题或内容"/>
        <button class="button primary" type="submit" :disabled="loading">{{ loading ? '搜索中…' : '搜索' }}</button>
        <button v-if="knowledgeKeyword" class="button secondary" type="button" @click="knowledgeKeyword=''; load()">清除</button>
      </form>
      <div v-else-if="kind === 'invite'" class="page-actions">
        <button class="button primary" :disabled="inviteSaving" @click="createInvite">{{ inviteSaving ? '生成中…' : '生成邀请码' }}</button>
      </div>
    </div>
    <p v-if="message" class="form-message success">{{ message }}</p>

    <section v-if="kind === 'traffic'" class="panel traffic-panel">
      <header><div><h2>每日流量趋势</h2><p>最近 14 天，按日汇总上传、下载与总用量</p></div><span class="traffic-total"><Icon name="chart" :size="17" /> {{ bytes(trafficTotal) }}</span></header>
      <div v-if="trafficHasData" class="traffic-chart-wrap">
        <svg class="traffic-chart" viewBox="0 0 680 156" preserveAspectRatio="xMidYMid meet" role="img" aria-label="每日流量折线图" @mouseleave="chartHover = false">
          <defs><linearGradient id="traffic-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3155ee" stop-opacity=".28"/><stop offset="1" stop-color="#3155ee" stop-opacity="0"/></linearGradient></defs>
          <line v-for="y in [36, 70, 104, 134]" :key="y" x1="28" x2="652" :y1="y" :y2="y" stroke="var(--border)" stroke-dasharray="3 5"/>
          <path :d="chartArea" fill="url(#traffic-area)"/>
          <path :d="chartLine" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          <g v-for="(point, index) in chartPoints" :key="point.key" class="traffic-point">
            <circle class="traffic-node" :cx="point.x" :cy="point.y" r="4" vector-effect="non-scaling-stroke"/>
            <circle class="traffic-hit-area" :cx="point.x" :cy="point.y" r="15" tabindex="0" :aria-label="`${date(point.timestamp)} ${bytes(point.amount)}`" @pointerenter="showChartPoint(index)" @mouseover="showChartPoint(index)" @focus="showChartPoint(index)"/>
          </g>
          <Transition name="chart-fade"><g v-if="chartHover && activeTraffic" class="chart-tooltip" :transform="chartTooltipTransform" pointer-events="none">
            <line class="chart-tooltip-guide" :x1="Number(activeTraffic.x) < 510 ? 0 : 164" :y1="Number(activeTraffic.y) < 62 ? 0 : 74" :x2="Number(activeTraffic.x) < 510 ? -14 : 176" :y2="Number(activeTraffic.y) < 62 ? -16 : 84"/><rect width="164" height="74" rx="10"/><text class="tooltip-date" x="12" y="18">{{ date(activeTraffic.timestamp) }}</text><text x="12" y="37">上传 {{ bytes(activeTraffic.u) }}</text><text x="12" y="53">下载 {{ bytes(activeTraffic.d) }}</text><text class="tooltip-total" x="12" y="69">总计 {{ bytes(activeTraffic.amount) }}</text>
          </g></Transition>
        </svg>
        <div class="traffic-axis" :style="{ gridTemplateColumns: `repeat(${trafficRows.length}, minmax(0, 1fr))` }" aria-hidden="true"><span v-for="day in trafficRows" :key="day.key">{{ day.label }}</span></div>
        <div class="chart-hint">悬浮折线节点查看当日流量</div>
      </div>
      <div v-else class="page-state">最近 14 天暂无流量记录</div>
    </section>

    <section v-else-if="kind === 'servers'" class="panel dashboard-node-panel server-directory-panel">
      <header><div><h2>节点状态</h2><p>实时查看当前线路与可用节点</p></div><div class="dashboard-node-summary"><NodeStatusLegend/>{{ onlineServerCount }} / {{ rows.length }} 在线</div></header>
      <div v-if="selectedServer" class="dashboard-node-body">
        <div class="dashboard-node-list">
          <button v-for="server in rows" :key="server.id" type="button" :class="['dashboard-node-item', { active: String(server.id) === String(selectedServer.id) }]" @click="selectedServerId = server.id">
            <span class="dashboard-node-code" aria-hidden="true"><img :src="displayNodeFlag(server)" alt=""/></span><span><strong>{{ server.name }}</strong><small>{{ server.type || '-' }} · {{ serverStatus(server).label }}</small></span><em :class="serverStatus(server).key">{{ Number(server.rate || 1).toFixed(1) }}×</em>
          </button>
        </div>
        <div class="dashboard-node-detail">
          <div class="dashboard-route-head"><span><i :class="serverStatus(selectedServer).key"/>当前线路</span><small>自动选择</small></div>
          <h3>{{ selectedServer.name }}</h3><p>已为你选择当前可用的优选节点</p>
          <div class="dashboard-pulse" aria-hidden="true"><i class="dashboard-route-line"/><span class="dashboard-pulse-ring ring-one"/><span class="dashboard-pulse-ring ring-two"/><span class="dashboard-pulse-core"/></div>
          <div class="dashboard-node-metrics"><div><small>连接状态</small><strong>{{ serverStatus(selectedServer).label }}</strong></div><div><small>流量倍率</small><strong>{{ Number(selectedServer.rate || 1).toFixed(1) }}×</strong></div><div><small>连接协议</small><strong>{{ String(selectedServer.type || '-').toUpperCase() }}</strong></div></div>
        </div>
      </div>
      <div v-else class="page-state">暂无可用节点</div>
    </section>

    <section v-else-if="kind === 'knowledge'" class="panel knowledge-index">
      <div v-for="group in knowledgeGroups" :key="group.category" class="knowledge-category">
        <header><div><span class="knowledge-kicker">文档分类</span><h2>{{ group.category }}</h2></div><span class="knowledge-count">{{ group.items.length }} 篇</span></header>
        <div class="knowledge-list">
          <button v-for="item in group.items" :key="item.id" type="button" class="knowledge-item" @click="openKnowledge(item, $event)">
            <span class="knowledge-item-icon"><Icon name="book" :size="18"/></span><span class="knowledge-item-copy"><strong>{{ item.title }}</strong></span><span class="knowledge-item-meta"><time>{{ date(item.updated_at, true) }}</time><Icon name="chevron" :size="16"/></span>
          </button>
        </div>
      </div>
      <div v-if="!knowledgeGroups.length" class="page-state">{{ knowledgeKeyword ? '暂无匹配文档' : '暂无文档' }}</div>
    </section>

    <template v-else>
      <section v-if="kind === 'invite'" class="stats-strip"><div><small>已注册用户</small><strong>{{ inviteStats[0] || 0 }}</strong></div><div><small>有效佣金</small><strong>{{ money(inviteStats[1] || 0) }}</strong></div><div><small>佣金比例</small><strong>{{ inviteStats[3] || 0 }}%</strong></div></section>
      <section v-if="kind === 'gifts'" class="panel gift-redeem">
        <form class="copy-row" @submit.prevent="saveCoupon"><input v-model.trim="couponCode" placeholder="输入管理员创建的优惠码" autocomplete="off"/><button class="button primary" :disabled="couponSaving || couponRemovingId !== null || !couponCode">{{ couponSaving ? '验证中' : '验证并保存' }}</button></form>
        <div v-if="savedCoupons.length" class="saved-coupon-list">
          <div v-for="coupon in savedCoupons" :key="coupon.id" class="coupon-preview"><div><strong>{{ coupon.name || '已保存优惠券' }}</strong><p>{{ couponValue(coupon) }} · 优惠码 {{ coupon.code }} · 有效期至 {{ date(coupon.ended_at) }}</p></div><div class="coupon-preview-actions"><button class="button secondary" type="button" :disabled="couponSaving || couponRemovingId !== null" @click="removeCoupon(coupon.id)">{{ String(couponRemovingId) === String(coupon.id) ? '删除中' : '删除' }}</button></div></div>
          <button class="button primary" type="button" :disabled="couponSaving || couponRemovingId !== null" @click="buyWithSavedCoupon">购买套餐</button>
        </div>
        <p v-else class="muted">当前账号尚未保存优惠券。</p>
        <p v-if="couponError" class="form-message error">{{ couponError }}</p>
      </section>
      <section v-if="kind !== 'gifts'" class="panel data-list">
        <template v-for="(item, index) in rows" :key="item.id || item.code || index">
          <article v-if="kind === 'invite'" class="invite-entry" :class="{ expanded: expandedInviteCode === item.code }">
            <button type="button" class="invite-code-button" :aria-expanded="expandedInviteCode === item.code" @click="toggleInviteDetails(item.code)">
              <div><strong>{{ item.code }}</strong><p>访问 {{ item.pv || 0 }} 次 · 点击查看对应被邀请人</p></div><div><span v-if="!item.status" class="status success">可用</span><Icon name="chevron" :class="{ rotated: expandedInviteCode === item.code }"/></div>
            </button>
            <Transition name="inline-expand">
              <div v-if="expandedInviteCode === item.code" class="invite-inline-details">
                <div v-if="detailsLoading" class="inline-loading">正在查询…</div>
                <template v-else-if="inviteDetailsFor(item.code).length"><div v-for="detail in inviteDetailsFor(item.code)" :key="detail.id" class="invite-person"><div><strong>{{ detail.invited_user?.email || '已删除用户' }}</strong><small>订单 #{{ detail.trade_no }} · 返佣 {{ money(detail.get_amount) }}</small></div><time>{{ date(detail.invited_user?.joined_at || detail.created_at, true) }}</time></div></template>
                <div v-else class="inline-empty">暂无该邀请码对应的被邀请人返佣记录</div>
              </div>
            </Transition>
          </article>
          <article v-else class="data-list-row"><div><strong>{{ item.name || item.title || item.email || item.code || `记录 ${index + 1}` }}</strong><div v-if="kind === 'knowledge'" class="knowledge-body" v-html="renderRichText(item.body, item.title)"/><p v-else>{{ item.category?.name || item.message || item.ip || item.description || '' }}</p></div><div><time>{{ date(item.created_at || item.updated_at, true) }}</time></div></article>
        </template>
        <div v-if="!rows.length" class="page-state">暂无数据</div>
      </section>
    </template>
  </PageState>
  <Teleport to="body">
    <Transition name="knowledge-dialog">
      <div v-if="selectedKnowledge" class="modal-backdrop knowledge-modal-backdrop" role="presentation" @click.self="closeKnowledge">
        <article ref="knowledgeModal" class="modal knowledge-modal" role="dialog" aria-modal="true" :aria-labelledby="`knowledge-modal-${selectedKnowledge.id}`" tabindex="-1" @keydown.esc.stop="closeKnowledge">
          <header><div><span class="notice-modal-kicker">{{ selectedKnowledge.category || '使用文档' }}</span><h2 :id="`knowledge-modal-${selectedKnowledge.id}`">{{ selectedKnowledge.title }}</h2><p>{{ date(selectedKnowledge.updated_at, true) }}</p></div><button class="icon-button" type="button" aria-label="关闭文档" @click="closeKnowledge"><Icon name="x"/></button></header>
          <div class="knowledge-modal-body knowledge-body" v-html="renderRichText(selectedKnowledge.body, selectedKnowledge.title)"/>
          <footer><button class="button primary" type="button" @click="closeKnowledge">关闭文档</button></footer>
        </article>
      </div>
    </Transition>
  </Teleport>
</template>

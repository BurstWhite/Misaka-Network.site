<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { serviceApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import Icon from '@/shared/Icon.vue'
import { bytes, date, money } from '@/shared/format'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const inviteDetails = ref<any[]>([])
const detailsOpen = ref(false)
const detailsLoading = ref(false)
const inviteSaving = ref(false)
const giftCode = ref('')
const giftSaving = ref(false)
const message = ref('')
const chartFocus = ref(0)
const kind = computed(() => String(route.meta.kind))
const meta: Record<string, [string, string]> = {
  invite: ['我的邀请', '生成邀请码，并查看被邀请用户的脱敏账户与返佣记录。'],
  traffic: ['流量明细', '按日期查看近一个月的上传、下载与总用量。'],
  servers: ['节点状态', '实时查看可用节点、倍率与最近心跳。'],
  knowledge: ['使用文档', '客户端配置与常见问题。'],
  gifts: ['礼品卡', '兑换礼品卡并查看历史记录。'],
}
const loaders: Record<string, () => Promise<any>> = { invite: serviceApi.invites, traffic: serviceApi.traffic, servers: serviceApi.servers, knowledge: serviceApi.knowledge, gifts: serviceApi.giftHistory }

async function load() {
  loading.value = true
  error.value = ''
  message.value = ''
  detailsOpen.value = false
  try { data.value = await (loaders[kind.value] || serviceApi.knowledge)() } catch (e: any) { error.value = e.message } finally { loading.value = false }
}

async function createInvite() {
  inviteSaving.value = true
  try { await serviceApi.createInvite(); message.value = '邀请码已生成，可点击“查询被邀请人”查看明细'; await load() } catch (e: any) { error.value = e.message } finally { inviteSaving.value = false }
}

async function loadInviteDetails() {
  detailsLoading.value = true
  try {
    const result = await serviceApi.inviteDetails({ current: 1, page_size: 50 })
    inviteDetails.value = Array.isArray(result) ? result : result?.data || []
    detailsOpen.value = true
  } catch (e: any) { error.value = e.message } finally { detailsLoading.value = false }
}

async function redeemGift() {
  if (!giftCode.value.trim()) return
  giftSaving.value = true
  try { await serviceApi.redeemGift(giftCode.value.trim()); giftCode.value = ''; message.value = '礼品卡兑换成功'; await load() } catch (e: any) { error.value = e.message } finally { giftSaving.value = false }
}

onMounted(load)
watch(kind, load)

const rows = computed<any[]>(() => {
  if (kind.value === 'invite') return data.value?.codes || []
  if (kind.value === 'knowledge' && data.value && !Array.isArray(data.value)) return Object.values(data.value).flatMap((group: any) => Array.isArray(group) ? group : [])
  if (Array.isArray(data.value)) return data.value
  return data.value?.data || data.value?.records || []
})
const inviteStats = computed(() => data.value?.stat || [])
const trafficRows = computed(() => rows.value.slice().sort((a, b) => Number(a.record_at || 0) - Number(b.record_at || 0)))
const trafficMax = computed(() => Math.max(1, ...trafficRows.value.map((item) => Number(item.u || 0) + Number(item.d || 0))))
const chartPoints = computed(() => trafficRows.value.map((item, index) => {
  const width = Math.max(1, trafficRows.value.length - 1)
  const amount = Number(item.u || 0) + Number(item.d || 0)
  return { ...item, amount, x: 28 + index / width * 624, y: 134 - amount / trafficMax.value * 98 }
}))
const chartLine = computed(() => chartPoints.value.map((item, index) => `${index ? 'L' : 'M'} ${item.x} ${item.y}`).join(' '))
const chartArea = computed(() => chartPoints.value.length ? `${chartLine.value} L 652 134 L 28 134 Z` : '')
const activeTraffic = computed(() => chartPoints.value[chartFocus.value] || chartPoints.value.at(-1))
const activeTrafficLabel = computed(() => activeTraffic.value ? `${date(activeTraffic.value.record_at)} · ${bytes(activeTraffic.value.amount)}` : '暂无流量记录')

function serverStatus(server: any) {
  if (!server.is_online) return { key: 'offline', label: '离线' }
  if (server.last_check_at && Date.now() / 1000 - Number(server.last_check_at) > 180) return { key: 'warming', label: '待确认' }
  return { key: 'online', label: '在线' }
}
function tags(value: unknown): string[] { return Array.isArray(value) ? value : String(value || '').split(',').filter(Boolean) }
function plainText(value: unknown): string { return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() }
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading"><div><h1>{{ meta[kind]?.[0] }}</h1><p>{{ meta[kind]?.[1] }}</p></div><div v-if="kind === 'invite'" class="page-actions"><button class="button secondary" :disabled="detailsLoading" @click="loadInviteDetails">{{ detailsLoading ? '查询中…' : '查询被邀请人' }}</button><button class="button primary" :disabled="inviteSaving" @click="createInvite">{{ inviteSaving ? '生成中…' : '生成邀请码' }}</button></div></div>
    <p v-if="message" class="form-message success">{{ message }}</p>

    <section v-if="kind === 'traffic'" class="panel traffic-panel"><header><div><h2>每日流量趋势</h2><p>{{ activeTrafficLabel }}</p></div><span class="traffic-total"><Icon name="chart" :size="17" /> {{ bytes(trafficRows.reduce((sum, item) => sum + Number(item.u || 0) + Number(item.d || 0), 0)) }}</span></header><div v-if="chartPoints.length" class="traffic-chart-wrap"><svg class="traffic-chart" viewBox="0 0 680 156" role="img" aria-label="每日流量折线图"><defs><linearGradient id="traffic-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3155ee" stop-opacity=".28"/><stop offset="1" stop-color="#3155ee" stop-opacity="0"/></linearGradient></defs><line v-for="y in [36, 70, 104, 134]" :key="y" x1="28" x2="652" :y1="y" :y2="y" stroke="var(--border)" stroke-dasharray="3 5"/><path :d="chartArea" fill="url(#traffic-area)"/><path :d="chartLine" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><g v-for="(point, index) in chartPoints" :key="point.record_at" class="traffic-point" @mouseenter="chartFocus = index" @focus="chartFocus = index"><circle :cx="point.x" :cy="point.y" r="index === chartFocus ? 6 : 4"/><circle :cx="point.x" :cy="point.y" r="13" fill="transparent" tabindex="0"/></g></svg><div class="traffic-dates"><button v-for="(point, index) in chartPoints" :key="point.record_at" :class="{ active: index === chartFocus }" @click="chartFocus = index"><Icon name="calendar" :size="14" /><span>{{ date(point.record_at).slice(5) }}</span><strong>{{ bytes(point.amount) }}</strong></button></div></div><div v-else class="page-state">暂无流量记录</div></section>

    <section v-else-if="kind === 'servers'" class="panel server-panel"><header><div><h2>节点状态列表</h2><p>绿色为在线，黄色表示心跳等待确认，红色表示离线。</p></div><span class="server-count">{{ rows.length }} 个节点</span></header><div class="table-wrap"><table class="server-table"><thead><tr><th>节点</th><th>协议</th><th>倍率</th><th>状态</th><th>标签</th><th>最后检查</th></tr></thead><tbody><tr v-for="server in rows" :key="server.id"><td><strong>{{ server.name }}</strong></td><td>{{ server.type || '-' }}</td><td>{{ server.rate || 1 }}×</td><td><span :class="['node-status', serverStatus(server).key]"><i/>{{ serverStatus(server).label }}</span></td><td><span v-for="tag in tags(server.tags)" :key="tag" class="tag">{{ tag }}</span><span v-if="!tags(server.tags).length">-</span></td><td>{{ date(server.last_check_at, true) }}</td></tr><tr v-if="!rows.length"><td colspan="6" class="empty-cell">暂无节点</td></tr></tbody></table></div></section>

    <template v-else>
      <section v-if="kind === 'invite'" class="stats-strip"><div><small>已注册用户</small><strong>{{ inviteStats[0] || 0 }}</strong></div><div><small>有效佣金</small><strong>{{ money(inviteStats[1] || 0) }}</strong></div><div><small>佣金比例</small><strong>{{ inviteStats[3] || 0 }}%</strong></div></section>
      <section v-if="kind === 'invite' && detailsOpen" class="panel invite-details"><header><div><h2>被邀请人信息</h2><p>账号已脱敏，展示其带来返佣的订单记录。</p></div><button class="icon-button" aria-label="关闭邀请明细" @click="detailsOpen = false"><Icon name="x" /></button></header><div class="table-wrap"><table><thead><tr><th>被邀请人</th><th>订单号</th><th>订单金额</th><th>返佣金额</th><th>加入时间</th></tr></thead><tbody><tr v-for="item in inviteDetails" :key="item.id"><td>{{ item.invited_user?.email || '已删除用户' }}</td><td>#{{ item.trade_no }}</td><td>{{ money(item.order_amount) }}</td><td>{{ money(item.get_amount) }}</td><td>{{ date(item.invited_user?.joined_at || item.created_at, true) }}</td></tr><tr v-if="!inviteDetails.length"><td colspan="5" class="empty-cell">暂无产生返佣的被邀请人记录</td></tr></tbody></table></div></section>
      <section v-if="kind === 'gifts'" class="panel gift-redeem"><form class="copy-row" @submit.prevent="redeemGift"><input v-model.trim="giftCode" placeholder="输入礼品卡兑换码" autocomplete="off"/><button class="button primary" :disabled="giftSaving">{{ giftSaving ? '兑换中' : '立即兑换' }}</button></form></section>
      <section class="panel data-list"><article v-for="(item, index) in rows" :key="item.id || item.code || index"><div><strong>{{ item.name || item.title || item.email || item.code || `记录 ${index + 1}` }}</strong><p v-if="kind === 'knowledge'" class="knowledge-body">{{ plainText(item.body) }}</p><p v-else>{{ item.category?.name || item.message || item.ip || item.description || (kind === 'invite' ? `访问 ${item.pv || 0} 次` : '') }}</p></div><div><span v-if="kind === 'invite' && item.status === 0" class="status success">可用</span><time>{{ date(item.created_at || item.updated_at, true) }}</time></div></article><div v-if="!rows.length" class="page-state">暂无数据</div></section>
    </template>
  </PageState>
</template>

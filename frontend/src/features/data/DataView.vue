<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { serviceApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import { bytes, date, money } from '@/shared/format'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const data = ref<any>(null)
const inviteSaving = ref(false)
const giftCode = ref('')
const giftSaving = ref(false)
const message = ref('')
const kind = computed(() => String(route.meta.kind))
const meta: Record<string, [string, string]> = { invite: ['我的邀请', '管理邀请码并查看返佣记录'], traffic: ['流量明细', '近一个月的流量使用记录'], servers: ['节点状态', '查看可用节点和连接倍率'], knowledge: ['使用文档', '客户端配置与常见问题'], gifts: ['礼品卡', '兑换礼品卡并查看历史记录'] }
const loaders: Record<string, () => Promise<any>> = { invite: serviceApi.invites, traffic: serviceApi.traffic, servers: serviceApi.servers, knowledge: serviceApi.knowledge, gifts: serviceApi.giftHistory }

async function load() {
  loading.value = true; error.value = ''; message.value = ''
  try { data.value = await (loaders[kind.value] || serviceApi.knowledge)() } catch (e: any) { error.value = e.message } finally { loading.value = false }
}
async function createInvite() { inviteSaving.value = true; try { await serviceApi.createInvite(); message.value = '邀请码已生成'; await load() } catch (e: any) { error.value = e.message } finally { inviteSaving.value = false } }
async function redeemGift() { if (!giftCode.value.trim()) return; giftSaving.value = true; try { await serviceApi.redeemGift(giftCode.value.trim()); giftCode.value = ''; message.value = '礼品卡兑换成功'; await load() } catch (e: any) { error.value = e.message } finally { giftSaving.value = false } }
onMounted(load); watch(kind, load)

const rows = computed<any[]>(() => {
  if (kind.value === 'invite') return data.value?.codes || []
  if (kind.value === 'knowledge' && data.value && !Array.isArray(data.value)) return Object.values(data.value).flatMap((group: any) => Array.isArray(group) ? group : [])
  if (Array.isArray(data.value)) return data.value
  return data.value?.data || data.value?.records || []
})
const inviteStats = computed(() => data.value?.stat || [])
function plainText(value: unknown): string { return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() }
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading"><div><h1>{{ meta[kind]?.[0] }}</h1><p>{{ meta[kind]?.[1] }}</p></div><button v-if="kind === 'invite'" class="button primary" :disabled="inviteSaving" @click="createInvite">生成邀请码</button></div>
    <p v-if="message" class="form-message success">{{ message }}</p>
    <section v-if="kind === 'invite'" class="stats-strip"><div><small>已注册用户</small><strong>{{ inviteStats[0] || 0 }}</strong></div><div><small>有效佣金</small><strong>{{ money(inviteStats[1] || 0) }}</strong></div><div><small>佣金比例</small><strong>{{ inviteStats[3] || 0 }}%</strong></div></section>
    <section v-if="kind === 'gifts'" class="panel gift-redeem"><form class="copy-row" @submit.prevent="redeemGift"><input v-model.trim="giftCode" placeholder="输入礼品卡兑换码" autocomplete="off"/><button class="button primary" :disabled="giftSaving">{{ giftSaving ? '兑换中' : '立即兑换' }}</button></form></section>
    <section class="panel data-list"><article v-for="(item, index) in rows" :key="item.id || item.code || index"><div><strong>{{ item.name || item.title || item.email || item.code || (kind === 'traffic' ? '流量记录' : `记录 ${index + 1}`) }}</strong><p v-if="kind === 'knowledge'" class="knowledge-body">{{ plainText(item.body) }}</p><p v-else>{{ item.category?.name || item.message || item.ip || item.server_name || item.description || (kind === 'invite' ? `访问 ${item.pv || 0} 次` : '') }}</p></div><div><span v-if="kind === 'servers'" :class="['status', item.is_online ? 'success' : '']">{{ item.is_online ? '在线' : '离线' }}</span><span v-if="kind === 'servers' && item.rate">倍率 {{ item.rate }}</span><span v-if="kind === 'traffic'">{{ bytes(Number(item.u || 0) + Number(item.d || 0)) }}</span><span v-if="kind === 'invite' && item.status === 0" class="status success">可用</span><time>{{ date(item.record_at || item.created_at || item.updated_at || item.last_check_at, true) }}</time></div></article><div v-if="!rows.length" class="page-state">暂无数据</div></section>
  </PageState>
</template>

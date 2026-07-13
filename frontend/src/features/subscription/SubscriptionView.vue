<script setup lang="ts">
/* eslint-disable vue/no-v-html -- renderRichText applies a strict DOM allowlist before rendering. */
import { computed, onMounted, ref } from 'vue'
import { userApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import Icon from '@/shared/Icon.vue'
import { bytes, date } from '@/shared/format'
import { clientImportUrl, type SupportedClient } from '@/shared/client-import'
import { renderRichText } from '@/shared/rich-text'

const loading = ref(true)
const error = ref('')
const message = ref('')
const data = ref<any>({})

async function load() {
  loading.value = true
  error.value = ''
  try { data.value = await userApi.subscribe() } catch (e: any) { error.value = e.message } finally { loading.value = false }
}

onMounted(load)

const subscribeUrl = () => data.value.subscribe_url || data.value.url || ''
const used = computed(() => Number(data.value.u || 0) + Number(data.value.d || 0))
const total = computed(() => Number(data.value.transfer_enable || data.value.plan?.transfer_enable || 0))
const progress = computed(() => total.value ? Math.min(100, used.value / total.value * 100) : 0)
const plan = computed(() => data.value.plan || {})
const planFeatures = computed(() => [
  ['总流量', plan.value.transfer_enable ? bytes(Number(plan.value.transfer_enable) * 1024 ** 3) : bytes(total.value)],
  ['速率限制', plan.value.speed_limit ? `${plan.value.speed_limit} Mbps` : '不限速'],
  ['设备数量', plan.value.device_limit ? `${plan.value.device_limit} 台` : '不限'],
  ['下次重置', data.value.next_reset_at ? date(data.value.next_reset_at) : `每月 ${data.value.reset_day || '-'} 日`],
])

async function copy() {
  const value = subscribeUrl()
  if (!value) return
  try { await navigator.clipboard.writeText(value); message.value = '订阅地址已复制' } catch { message.value = '浏览器不支持自动复制，请手动复制订阅地址' }
}

function openClient(client: SupportedClient) {
  const url = subscribeUrl()
  if (!url) { message.value = '当前没有可用订阅地址'; return }
  window.location.assign(clientImportUrl(client, url))
}

async function reset() {
  try { data.value.subscribe_url = await userApi.resetSecurity(); message.value = '订阅地址已重置，旧地址已失效' } catch (e: any) { error.value = e.message }
}
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading"><div><h1>我的订阅</h1><p>管理订阅、查看套餐权益，并一键导入常用客户端。</p></div></div>

    <section class="subscription-overview">
      <div class="plan-summary">
        <span class="plan-icon"><Icon name="card" :size="28" /></span>
        <div><small>当前套餐</small><h2>{{ plan.name || '暂无有效套餐' }}</h2><p>有效期至 {{ date(data.expired_at) }}</p></div>
      </div>
      <div class="usage-summary"><div><span>已用流量</span><strong>{{ bytes(used) }}</strong></div><div><span>剩余流量</span><strong>{{ bytes(Math.max(0, total - used)) }}</strong></div><div class="progress"><i :style="{ width: `${progress}%` }" /></div><small>{{ progress.toFixed(1) }}% 已使用，共 {{ bytes(total) }}</small></div>
    </section>

    <section class="detail-grid subscription-grid">
      <article class="panel plan-details"><header><div><h2>套餐内容</h2><p>当前套餐的可用权益</p></div><span class="plan-badge">进行中</span></header><div class="feature-list"><div v-for="([label, value]) in planFeatures" :key="label"><span>{{ label }}</span><strong>{{ value }}</strong></div></div><div v-if="plan.content" class="plan-content knowledge-body" v-html="renderRichText(plan.content, plan.name)"/></article>
      <article class="panel subscription-address"><header><div><h2>订阅地址</h2><p>地址泄露后请立即重置。</p></div><button class="link-button danger" @click="reset">重置地址</button></header><div class="copy-row"><input readonly :value="subscribeUrl() || '暂无订阅地址'" aria-label="订阅地址"/><button class="button primary" @click="copy">复制地址</button></div><div class="client-import-grid"><button class="client-import" type="button" @click="openClient('clash')"><Icon name="download"/><span><strong>Clash Verge</strong><small>桌面客户端</small></span><em>一键导入</em></button><button class="client-import" type="button" @click="openClient('shadowrocket')"><Icon name="rocket"/><span><strong>Shadowrocket</strong><small>iPhone / iPad</small></span><em>一键导入</em></button><button class="client-import" type="button" @click="openClient('v2rayn')"><Icon name="bolt"/><span><strong>v2rayN</strong><small>Windows 客户端</small></span><em>一键导入</em></button></div><p v-if="message" class="form-message success">{{ message }}</p></article>
    </section>
  </PageState>
</template>

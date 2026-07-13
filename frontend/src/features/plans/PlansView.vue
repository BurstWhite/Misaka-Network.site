<script setup lang="ts">
/* eslint-disable vue/no-v-html -- renderRichText applies a strict DOM allowlist before rendering. */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { commerceApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import { date, money, periods } from '@/shared/format'
import { parsePlanFeatures } from '@/shared/catalog'
import { renderRichText } from '@/shared/rich-text'

const router = useRouter()
const route = useRoute()
const loading = ref(true)
const error = ref('')
const plans = ref<any[]>([])
const selected = ref<any>(null)
const period = ref('month_price')
const coupon = ref(String(route.query.coupon || ''))
const checkedCoupon = ref<any>(null)
const couponChecking = ref(false)
const couponError = ref('')
const submitting = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try { plans.value = await commerceApi.plans() || [] } catch (e: any) { error.value = e.message } finally { loading.value = false }
}

onMounted(load)

const prices = computed(() => selected.value
  ? Object.entries(periods).filter(([key]) => selected.value[key] !== null && selected.value[key] !== undefined && Number(selected.value[key]) > 0)
  : [])

watch(selected, (plan) => {
  if (plan && !prices.value.some(([key]) => key === period.value)) period.value = String(prices.value[0]?.[0] || '')
})
watch([selected, period, coupon], () => {
  checkedCoupon.value = null
  couponError.value = ''
})

const selectedPrice = computed(() => Number(selected.value?.[period.value] || 0))
const couponDiscount = computed(() => {
  if (!checkedCoupon.value) return 0
  const value = Number(checkedCoupon.value.value || 0)
  const discount = Number(checkedCoupon.value.type) === 2 ? Math.floor(selectedPrice.value * value / 100) : value
  return Math.min(selectedPrice.value, Math.max(0, discount))
})

function planTraffic(plan: any): string {
  const value = Number(plan?.transfer_enable || 0)
  return `${(Number.isFinite(value) ? value : 0).toFixed(2)} GB`
}

function planFeatures(plan: any): string[] {
  const capacity = Number(plan.capacity_limit)
  return [
    plan.speed_limit ? `${plan.speed_limit} Mbps 速率` : '不限速',
    plan.device_limit ? `${plan.device_limit} 台设备同时在线` : '不限设备数量',
    ...(Number.isFinite(capacity) && capacity > 0 ? [`剩余 ${capacity} 个名额`] : []),
    plan.reset_traffic_method === 2 ? '套餐流量不重置' : '套餐流量按策略重置',
  ]
}

async function checkCoupon(): Promise<boolean> {
  const code = coupon.value.trim()
  if (!selected.value || !period.value || !code) return false
  couponChecking.value = true
  couponError.value = ''
  try {
    checkedCoupon.value = await commerceApi.coupon({ code, plan_id: selected.value.id, period: period.value })
    return true
  } catch (e: any) {
    checkedCoupon.value = null
    couponError.value = e.message
    return false
  } finally {
    couponChecking.value = false
  }
}

async function create(manual = false) {
  if (!selected.value || !period.value) return
  const code = coupon.value.trim()
  if (code && checkedCoupon.value?.code !== code && !(await checkCoupon())) return
  submitting.value = true
  error.value = ''
  try {
    const tradeNo = await commerceApi.createOrder({ plan_id: selected.value.id, period: period.value, coupon_code: checkedCoupon.value?.code || undefined })
    const id = typeof tradeNo === 'string' ? tradeNo : tradeNo?.trade_no
    if (!id) throw new Error('订单号缺失')
    if (manual) await commerceApi.manualSubmit(id)
    await router.push(`/order/${id}`)
  } catch (e: any) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading"><div><h1>购买订阅</h1><p>选择适合你的套餐和付款周期。</p></div></div>
    <div class="plans-grid">
      <article v-for="plan in plans" :key="plan.id" :class="['plan-card', { selected: selected?.id === plan.id }]" @click="selected = plan">
        <h2>{{ plan.name }}</h2>
        <strong>{{ money(plan.month_price) }}<small>/ 月</small></strong>
        <p>{{ planTraffic(plan) }} 流量</p>
        <ul v-if="parsePlanFeatures(plan.content).length" class="plan-content-features"><li v-for="feature in parsePlanFeatures(plan.content)" :key="feature.feature" :class="{ unsupported: !feature.support }">{{ feature.support ? '✓' : '×' }} {{ feature.feature }}</li></ul>
        <div v-else-if="plan.content" class="plan-card-content knowledge-body" v-html="renderRichText(plan.content, plan.name)"/>
        <ul v-else><li v-for="feature in planFeatures(plan)" :key="feature">{{ feature }}</li></ul>
        <button class="button" :class="selected?.id === plan.id ? 'primary' : 'secondary'">{{ selected?.id === plan.id ? '已选择' : '选择套餐' }}</button>
      </article>
    </div>
    <div v-if="selected" class="modal-backdrop" @click.self="selected = null">
      <section class="modal plan-modal">
        <header><div><h2>配置订阅</h2><p>{{ selected.name }}</p></div><button class="icon-button" @click="selected = null">×</button></header>
        <div class="plan-modal-summary"><span>{{ planTraffic(selected) }}</span><span>{{ selected.speed_limit ? `${selected.speed_limit} Mbps` : '不限速' }}</span><span>{{ selected.device_limit ? `${selected.device_limit} 台设备` : '不限设备' }}</span></div>
        <ul v-if="parsePlanFeatures(selected.content).length" class="plan-content plan-content-features"><li v-for="feature in parsePlanFeatures(selected.content)" :key="feature.feature" :class="{ unsupported: !feature.support }">{{ feature.support ? '✓' : '×' }} {{ feature.feature }}</li></ul>
        <div v-else-if="selected.content" class="plan-content knowledge-body" v-html="renderRichText(selected.content, selected.name)"/>
        <label>付款周期<select v-model="period"><option v-for="([key, label]) in prices" :key="key" :value="key">{{ label }} · {{ money(selected[key]) }}</option></select></label>
        <label>优惠券（选填）<span class="copy-row"><input v-model.trim="coupon" placeholder="输入优惠码" /><button class="button secondary" type="button" :disabled="couponChecking || !coupon" @click="checkCoupon">{{ couponChecking ? '验证中' : '验证' }}</button></span></label>
        <div v-if="checkedCoupon" class="coupon-preview"><div><strong>{{ checkedCoupon.name || '优惠券有效' }}</strong><p>减免 {{ money(couponDiscount) }}，优惠后 {{ money(selectedPrice - couponDiscount) }} · 有效期至 {{ date(checkedCoupon.ended_at) }}</p></div></div>
        <p v-if="couponError" class="form-message error">{{ couponError }}</p>
        <p v-if="error" class="form-message error">{{ error }}</p>
        <footer><button class="button secondary" :disabled="submitting" @click="create(true)">人工提交订单</button><button class="button primary" :disabled="submitting" @click="create(false)">{{ submitting ? '正在提交' : '下单' }}</button></footer>
      </section>
    </div>
  </PageState>
</template>

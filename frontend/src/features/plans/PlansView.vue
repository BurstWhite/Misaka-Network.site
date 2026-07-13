<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'; import { useRouter } from 'vue-router'; import { commerceApi } from '@/api/services'; import PageState from '@/shared/PageState.vue'; import { bytes, money, periods } from '@/shared/format'; import { stripMarkup } from '@/shared/catalog'
const router=useRouter(), loading=ref(true), error=ref(''), plans=ref<any[]>([]), selected=ref<any>(null), period=ref('month_price'), coupon=ref(''), submitting=ref(false)
async function load(){loading.value=true;try{plans.value=await commerceApi.plans()||[]}catch(e:any){error.value=e.message}finally{loading.value=false}} onMounted(load)
const prices=computed(()=>selected.value?Object.entries(periods).filter(([key])=>selected.value[key] !== null && selected.value[key] !== undefined && Number(selected.value[key]) > 0):[])
watch(selected, (plan) => { if (plan && !prices.value.some(([key]) => key === period.value)) period.value = String(prices.value[0]?.[0] || '') })
async function create(manual=false){if(!selected.value || !period.value)return;submitting.value=true;error.value='';try{const tradeNo=await commerceApi.createOrder({plan_id:selected.value.id,period:period.value,coupon_code:coupon.value||undefined}); const id=typeof tradeNo==='string'?tradeNo:tradeNo?.trade_no; if(!id)throw new Error('订单号缺失'); if(manual)await commerceApi.manualSubmit(id); await router.push(`/order/${id}`)}catch(e:any){error.value=e.message}finally{submitting.value=false}}
function planFeatures(plan:any): string[] {
  const features:string[] = []
  const speed = Number(plan.speed_limit)
  const devices = Number(plan.device_limit)
  const capacity = Number(plan.capacity_limit)
  if (Number(plan.transfer_enable) > 0) features.push(bytes(plan.transfer_enable) + ' 流量')
  features.push(Number.isFinite(speed) && speed > 0 ? speed + ' Mbps 速率' : '不限速')
  features.push(Number.isFinite(devices) && devices > 0 ? devices + ' 台设备' : '设备不限')
  if (Number.isFinite(capacity) && capacity > 0) features.push('剩余 ' + capacity + ' 个名额')
  return features
}
function planSummary(plan:any): string {
  const content = stripMarkup(plan.content)
  return content ? (content.length > 80 ? content.slice(0, 80) + '…' : content) : planFeatures(plan).slice(0, 2).join(' · ')
}
</script>
<template><PageState :loading="loading" :error="error" @retry="load"><div class="page-heading"><div><h1>购买订阅</h1><p>选择适合你的套餐和付款周期。</p></div></div><div class="plans-grid"><article v-for="plan in plans" :key="plan.id" :class="['plan-card',{selected:selected?.id===plan.id}]" @click="selected=plan"><h2>{{ plan.name }}</h2><strong>{{ money(plan.month_price) }}<small>/ 月</small></strong><p>{{ planSummary(plan) }}</p><ul><li v-for="feature in planFeatures(plan)" :key="feature">{{ feature }}</li></ul><button class="button" :class="selected?.id===plan.id?'primary':'secondary'">{{ selected?.id===plan.id?'已选择':'选择套餐' }}</button></article></div><div v-if="selected" class="modal-backdrop" @click.self="selected=null"><section class="modal"><header><div><h2>配置订阅</h2><p>{{ selected.name }}</p></div><button class="icon-button" @click="selected=null">×</button></header><label>付款周期<select v-model="period"><option v-for="([key,label]) in prices" :key="key" :value="key">{{ label }} · {{ money(selected[key]) }}</option></select></label><label>优惠券（选填）<input v-model="coupon" placeholder="输入优惠码" /></label><p v-if="error" class="form-message error">{{ error }}</p><footer><button class="button secondary" :disabled="submitting" @click="create(true)">人工提交订单</button><button class="button primary" :disabled="submitting" @click="create(false)">{{ submitting?'正在提交':'下单' }}</button></footer></section></div></PageState></template>

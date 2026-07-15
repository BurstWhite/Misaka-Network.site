<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { guestApi } from '@/api/services'
import { runtimeConfig } from '@/app/config'
import { useAuthStore } from '@/stores/auth'
import Icon from '@/shared/Icon.vue'
import { bytes, money } from '@/shared/format'
import { displayNodeCode, displayRate, extractRows, stripMarkup } from '@/shared/catalog'
import { content } from '@/shared/content'
import backgroundPosterUrl from '@/assets/misaka-background.webp'
import type { PublicNode, PublicPlan } from '@/shared/catalog'

const yearly = ref(true)
const menuOpen = ref(false)
const observer = ref<IntersectionObserver>()
const landingLoading = ref(true)
const planError = ref('')
const nodeError = ref('')
const nodes = ref<PublicNode[]>([])
const plans = ref<PublicPlan[]>([])
const selectedNodeId = ref<string | number | null>(null)
const videoSrc = ref('')
const backgroundVideoUrl = 'https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260508_064122_c4750c0e-7476-4b44-94a2-a85a65c63bf2.mp4'
const auth = useAuthStore()
const registerEnabled = computed(() => auth.registerEnabled)

const features = computed(() => [
  [content('landing.feature.nodes.title', '全球节点'), content('landing.feature.nodes.description', '智能选择更稳定的连接路径，在不同网络环境下保持顺畅。'), 'globe'],
  [content('landing.feature.subscribe.title', '一键订阅'), content('landing.feature.subscribe.description', '复制订阅或直接唤起客户端，减少繁琐的手动配置。'), 'bolt'],
  [content('landing.feature.usage.title', '用量清晰'), content('landing.feature.usage.description', '流量、到期时间与在线状态集中呈现，重要信息一眼可见。'), 'chart'],
])

const selectedNode = computed(() => nodes.value.find((node) => String(node.id) === String(selectedNodeId.value)) || nodes.value[0] || null)
const landingStatus = computed(() => {
  if (landingLoading.value) return '正在同步公开状态'
  if (nodeError.value) return '节点状态暂不可用'
  return nodes.value.length ? String(nodes.value.length) + ' 个公开节点' : '暂无公开节点'
})

function errorMessage(reason: unknown, fallback: string): string {
  return String((reason as { message?: string })?.message || fallback)
}

async function loadCatalog() {
  landingLoading.value = true
  planError.value = ''
  nodeError.value = ''
  const [planResult, nodeResult] = await Promise.allSettled([guestApi.plans(), guestApi.servers()])

  if (planResult.status === 'fulfilled') {
    plans.value = extractRows<PublicPlan>(planResult.value)
  } else {
    plans.value = []
    planError.value = errorMessage(planResult.reason, '公开套餐暂时无法加载')
  }

  if (nodeResult.status === 'fulfilled') {
    nodes.value = extractRows<PublicNode>(nodeResult.value)
    selectedNodeId.value = nodes.value[0]?.id ?? null
  } else {
    nodes.value = []
    selectedNodeId.value = null
    nodeError.value = errorMessage(nodeResult.reason, '公开节点暂时无法加载')
  }

  landingLoading.value = false
}

function selectNode(node: PublicNode) {
  selectedNodeId.value = node.id
}

function scrollToSection(id: string) {
  menuOpen.value = false
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function nodeStatus(node: PublicNode | null | undefined) {
  if (!node?.is_online) return { key: 'offline', label: '离线' }
  if (node.last_check_at && Date.now() / 1000 - Number(node.last_check_at) > 300) return { key: 'warming', label: '待确认' }
  return { key: 'online', label: '在线' }
}

function nodeDescriptor(node: PublicNode): string {
  const tags = Array.isArray(node.tags) ? node.tags.filter(Boolean).slice(0, 2).join(' · ') : ''
  return tags || String(node.type || '线路').toUpperCase() + ' · ' + nodeStatus(node).label
}

function planPrice(plan: PublicPlan): string {
  const value = yearly.value ? plan.year_price : plan.month_price
  return value === null || value === undefined || value === '' ? '暂未开放' : money(value)
}

function planItems(plan: PublicPlan): string[] {
  const items: string[] = []
  const transfer = Number(plan.transfer_enable)
  const speed = Number(plan.speed_limit)
  const devices = Number(plan.device_limit)
  const capacity = Number(plan.capacity_limit)
  if (Number.isFinite(transfer) && transfer > 0) items.push(bytes(transfer * 1024 ** 3) + ' 流量')
  items.push(Number.isFinite(speed) && speed > 0 ? speed + ' Mbps 速率' : '不限速')
  items.push(Number.isFinite(devices) && devices > 0 ? devices + ' 台设备' : '设备不限')
  if (Number.isFinite(capacity) && capacity > 0) items.push('剩余 ' + capacity + ' 个名额')
  return items
}

function planDescription(plan: PublicPlan): string {
  const content = stripMarkup(plan.content)
  if (content) return content.length > 86 ? content.slice(0, 86) + '…' : content
  const facts = planItems(plan).slice(0, 2)
  return facts.length ? facts.join(' · ') : '具体权益以当前套餐配置为准。'
}

function isFeaturedPlan(plan: PublicPlan): boolean {
  return (Array.isArray(plan.tags) ? plan.tags : []).some((tag) => /推荐|featured/i.test(String(tag)))
}

function loadBackgroundVideo() {
  const connection = (navigator as Navigator & { connection?: { saveData?: boolean } }).connection
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || connection?.saveData) return
  videoSrc.value = backgroundVideoUrl
}

onMounted(() => {
  observer.value = new IntersectionObserver((entries) => {
    entries.forEach((entry) => entry.isIntersecting && entry.target.classList.add('is-visible'))
  }, { threshold: 0.12 })
  document.querySelectorAll('.landing .reveal').forEach((element) => observer.value?.observe(element))
  if (document.readyState === 'complete') loadBackgroundVideo()
  else window.addEventListener('load', loadBackgroundVideo, { once: true })
  void auth.loadConfig()
  void loadCatalog()
})
onBeforeUnmount(() => {
  observer.value?.disconnect()
  window.removeEventListener('load', loadBackgroundVideo)
})
</script>

<template>
  <main class="landing">
    <div class="ambient" aria-hidden="true">
      <video autoplay loop muted playsinline preload="none" :poster="backgroundPosterUrl" :src="videoSrc || undefined" />
      <div class="ambient-shade" />
    </div>
    <div class="guide guide-left" aria-hidden="true" /><div class="guide guide-right" aria-hidden="true" />
    <svg class="noise-filter" aria-hidden="true"><filter id="misaka-noise"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" stitchTiles="stitch"/><feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 .35 0"/><feComposite in2="SourceGraphic" operator="in" result="noise"/><feBlend in="SourceGraphic" in2="noise" mode="multiply"/></filter></svg>

    <nav class="nav wrap">
      <a class="logo" href="#top" :aria-label="runtimeConfig.appName" @click.prevent="scrollToSection('top')">
        <span class="logo-mark"><i/><i/></span><span>{{ runtimeConfig.appName }}</span>
      </a>
      <div class="nav-links" :class="{ open: menuOpen }">
        <a href="#network" @click.prevent="scrollToSection('network')">网络</a><a href="#features" @click.prevent="scrollToSection('features')">能力</a><a href="#plans" @click.prevent="scrollToSection('plans')">套餐</a><a href="#support" @click.prevent="scrollToSection('support')">支持</a>
      </div>
      <RouterLink class="pill pill-light nav-cta" to="/login">进入控制台 <span>→</span></RouterLink>
      <button class="menu-button" type="button" aria-label="打开菜单" @click="menuOpen=!menuOpen"><Icon :name="menuOpen ? 'x' : 'menu'" :size="18" /></button>
    </nav>

    <section id="top" class="hero wrap">
      <div class="hero-badge"><span/> {{ content('landing.hero.badge', '全新 Misaka 用户中心') }}</div>
      <h1>{{ content('landing.hero.title', '连接世界。') }}<br><span>{{ content('landing.hero.accent', '轻盈无界') }}</span></h1>
      <p>{{ runtimeConfig.description || '可靠、清晰、触手可及的全球网络连接。' }}。{{ content('landing.hero.suffix', '从购买套餐到导入订阅，一切都在一个安静而高效的工作台中完成。') }}</p>
      <div class="hero-actions"><RouterLink class="pill pill-light" to="/login">立即开始 <span>→</span></RouterLink></div>
      <small>支持主流桌面与移动客户端</small>
    </section>

    <div class="system-strip">
      <div class="wrap system-inner"><div><span class="system-dot"/> <b>Misaka</b><span>网络</span><span>订阅</span><span>节点</span><span>帮助</span></div><div><Icon name="globe" :size="14"/> {{ landingStatus }}</div></div>
    </div>

    <section id="network" class="network-section wrap reveal">
      <div class="window liquid-glass">
        <header class="window-bar"><div><i/><i/><i/></div><span>Misaka Network — 公开状态</span><span class="live"><i/> {{ landingStatus }}</span></header>
        <div class="network-body">
          <aside>
            <div class="side-brand"><span class="logo-mark small"><i/><i/></span> Misaka</div>
            <RouterLink class="connect-button" to="/login"><Icon name="bolt" :size="16"/> 登录连接</RouterLink>
            <a class="active"><Icon name="dashboard" :size="17"/> 概览</a><a><Icon name="card" :size="17"/> 我的订阅</a><a><Icon name="server" :size="17"/> 节点状态</a><a><Icon name="receipt" :size="17"/> 订单</a><a><Icon name="ticket" :size="17"/> 技术支持</a>
          </aside>
          <div class="node-list">
            <div class="search"><Icon name="globe" :size="15"/> 搜索节点</div>
            <div v-if="landingLoading" class="node-list-state">正在同步节点状态…</div>
            <template v-else>
              <button v-for="node in nodes" :key="node.id" type="button" class="node-entry" :class="{ selected: String(node.id) === String(selectedNode && selectedNode.id) }" @click="selectNode(node)">
                <span class="flag">{{ displayNodeCode(node) }}</span><span><b>{{ node.name || '未命名节点' }}</b><small>{{ nodeDescriptor(node) }}</small></span><span class="latency">{{ displayRate(node.rate) }}</span>
              </button>
            </template>
            <div v-if="!landingLoading && !nodes.length" class="node-list-state">{{ nodeError || '暂无公开节点，请登录后查看可用线路。' }}</div>
          </div>
          <div v-if="selectedNode" class="network-detail">
            <div class="detail-head"><div><span :class="['status-dot', nodeStatus(selectedNode).key]"/> 当前线路</div><span>公开状态</span></div>
            <h2>{{ selectedNode.name || '公开节点' }} · {{ displayNodeCode(selectedNode) }}</h2><p>{{ nodeStatus(selectedNode).label }} · {{ String(selectedNode.type || '线路').toUpperCase() }}</p>
            <div class="pulse-map" :aria-label="String(selectedNode.name || '节点') + ' 状态示意'"><span class="ring r1"/><span class="ring r2"/><span class="core"/><i class="route route-one"/><i class="route route-two"/></div>
            <div class="metrics"><div><span>连接状态</span><strong>{{ nodeStatus(selectedNode).label }}</strong></div><div><span>线路倍率</span><strong>{{ displayRate(selectedNode.rate) }}</strong></div><div><span>连接协议</span><strong>{{ String(selectedNode.type || '—').toUpperCase() }}</strong></div></div>
            <RouterLink class="detail-button" to="/login">登录查看完整配置 <span>→</span></RouterLink>
          </div>
          <div v-else class="network-detail network-empty">
            <div class="detail-head"><div><span class="status-dot offline"/> 当前线路</div><span>暂无数据</span></div>
            <h2>暂无公开节点</h2><p>登录后查看与你的套餐匹配的线路。</p>
            <RouterLink class="detail-button" to="/login">进入控制台 <span>→</span></RouterLink>
          </div>
        </div>
      </div>
    </section>

    <section id="features" class="feature-intro wrap reveal">
      <div><div class="eyebrow"><span/> {{ content('landing.feature.kicker', '核心体验') }} <em>{{ content('landing.feature.tag', '为连接而生') }}</em></div><h2>{{ content('landing.feature.title', '复杂留在背后。') }}<br>{{ content('landing.feature.accent', '连接只需一步。') }}</h2><p>{{ content('landing.feature.description', 'Misaka Network 将线路、订阅、设备与支持集中到一个清晰的界面中，让每次连接都简单、透明、可掌控。') }}</p></div>
      <div class="triage liquid-glass">
        <header><span>公开节点状态</span><small>{{ landingLoading ? '同步中' : nodeError ? '同步失败' : '实时摘要' }}</small></header>
        <article v-for="node in nodes.slice(0, 4)" :key="node.id"><span class="flag">{{ displayNodeCode(node) }}</span><span><b>{{ node.name || '未命名节点' }}</b><small>{{ nodeStatus(node).label }} · {{ String(node.type || '线路').toUpperCase() }}</small></span><span class="triage-status" :class="nodeStatus(node).key">{{ displayRate(node.rate) }}</span></article>
        <div v-if="!landingLoading && !nodes.length" class="triage-empty">{{ nodeError || '暂无公开节点状态' }}</div>
      </div>
    </section>

    <section class="feature-grid wrap reveal">
      <article v-for="feature in features" :key="feature[0]" class="liquid-glass"><div class="feature-icon"><Icon :name="feature[2]" :size="21"/></div><h3>{{ feature[0] }}</h3><p>{{ feature[1] }}</p><span>了解更多 →</span></article>
    </section>

    <section class="protocols wrap reveal"><p>{{ content('landing.protocols.title', '兼容你熟悉的客户端与连接方式') }}</p><div><b>Clash</b><b>Shadowrocket</b><b>v2rayN</b><b>Surge</b><b>Stash</b><b>sing-box</b></div></section>

    <section id="plans" class="pricing reveal">
      <div class="pricing-title"><span>{{ content('landing.plans.kicker', '选择适合你的连接方式') }}</span><h2>{{ content('landing.plans.title', '清晰套餐。') }}<br><i>{{ content('landing.plans.accent', '自由抵达') }}</i></h2></div>
      <div v-if="landingLoading" class="landing-data-state">正在读取公开套餐…</div>
      <div v-else-if="planError" class="landing-data-state"><p>{{ planError }}</p><RouterLink to="/login">登录后查看可用套餐 <span>→</span></RouterLink></div>
      <div v-else-if="!plans.length" class="landing-data-state"><p>当前暂无可售套餐。</p><RouterLink to="/login">进入控制台查看 <span>→</span></RouterLink></div>
      <div v-else class="plan-grid">
        <article v-for="plan in plans" :key="plan.id" :class="['plan-card', { featured: isFeaturedPlan(plan) }]">
          <small>{{ plan.name || '未命名套餐' }}</small><h3>{{ planPrice(plan) }}</h3><p>{{ planDescription(plan) }}</p><ul><li v-for="item in planItems(plan)" :key="item"><span>✓</span>{{ item }}</li></ul><RouterLink :to="registerEnabled ? '/register' : '/login'">{{ registerEnabled ? '查看套餐详情' : '登录查看套餐详情' }} <span>→</span></RouterLink>
        </article>
      </div>
      <div class="billing-toggle"><span :class="{ active: !yearly }">月付</span><button type="button" :class="{ active: yearly }" :aria-pressed="yearly" @click="yearly=!yearly"><i/></button><span :class="{ active: yearly }">年付</span></div>
      <p class="pricing-note">当前显示 {{ yearly ? '年付' : '月付' }}公开价格，完整权益以套餐详情为准</p>
    </section>

    <section id="support" class="final-cta wrap reveal liquid-glass">
      <div class="cta-glow"/><div class="logo-mark large"><i/><i/></div><h2>{{ content('landing.cta.title', '少一点等待。') }}<br>{{ content('landing.cta.accent', '多一点抵达。') }}</h2><p>{{ registerEnabled ? content('landing.cta.description', '创建你的 Misaka Network 账户，让可靠连接成为每天最自然的一部分。') : '登录现有账户，继续使用你的 Misaka Network 连接。' }}</p><div><RouterLink class="pill pill-light" :to="registerEnabled ? '/register' : '/login'">{{ registerEnabled ? '创建账户' : '登录账户' }} <span>→</span></RouterLink><RouterLink class="pill pill-ghost" to="/login">已有账户</RouterLink></div>
    </section>

    <footer class="footer wrap"><a class="logo" href="#top" @click.prevent="scrollToSection('top')"><span class="logo-mark small"><i/><i/></span><span>{{ runtimeConfig.appName }}</span></a><p>{{ content('landing.footer.tagline', 'Reliable global connectivity.') }}</p><span>© {{ new Date().getFullYear() }} {{ runtimeConfig.appName }}</span></footer>
  </main>
</template>

<style scoped>
.landing{--cyan:#61e7ff;--blue:#3478f6;position:relative;min-height:100vh;overflow:hidden;background:#090a0d;color:#fff;font-family:Inter,"SF Pro Display","PingFang SC",system-ui,sans-serif}.landing *{box-sizing:border-box}.ambient{position:fixed;inset:0;z-index:0;pointer-events:none}.ambient video{width:100%;height:100%;object-fit:cover;opacity:.32;filter:saturate(.72) hue-rotate(8deg)}.ambient-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(7,8,11,.45),#090a0d 78%),radial-gradient(circle at 50% 0,transparent,rgba(9,10,13,.45) 70%)}.wrap{width:min(1152px,calc(100% - 48px));margin-inline:auto;position:relative;z-index:2}.guide{position:fixed;inset-block:0;z-index:1;width:1px;background:rgba(255,255,255,.08);pointer-events:none}.guide-left{left:calc(50% - 576px)}.guide-right{right:calc(50% - 576px)}.noise-filter{position:absolute;width:0;height:0}.nav{height:86px;display:flex;align-items:center;justify-content:space-between;animation:nav-in .7s ease-out both}.logo{display:flex;align-items:center;gap:11px;font-size:15px;font-weight:700}.logo-mark{position:relative;width:28px;height:25px;display:inline-block}.logo-mark i{position:absolute;left:5px;top:0;width:8px;height:24px;border-radius:5px 5px 2px 2px;background:linear-gradient(180deg,var(--cyan),var(--blue));transform:rotate(-38deg)}.logo-mark i+i{left:16px;transform:rotate(38deg);background:linear-gradient(180deg,#b3f4ff,#5d6dff)}.logo-mark.small{transform:scale(.76)}.logo-mark.large{transform:scale(1.7);margin-bottom:20px}.nav-links{display:flex;gap:34px}.nav-links a{font-size:13px;font-weight:600;color:rgba(255,255,255,.62);transition:.2s}.nav-links a:hover{color:#fff}.pill{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:44px;padding:0 20px;border-radius:999px;font-size:13px;font-weight:650;transition:.2s;border:1px solid transparent}.pill span{transition:transform .2s}.pill:hover span{transform:translateX(2px)}.pill-light{background:#fff;color:#090a0d}.pill-light:hover{background:#edfaff;transform:translateY(-1px)}.pill-ghost{border-color:rgba(255,255,255,.14);background:rgba(255,255,255,.025);color:#fff}.pill-ghost:hover{background:rgba(255,255,255,.07)}.menu-button{display:none;width:42px;height:42px;border-radius:50%;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:#fff;place-items:center}.hero{padding:90px 0 104px;text-align:center;display:flex;flex-direction:column;align-items:center}.hero-badge{display:flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid rgba(255,255,255,.11);border-radius:999px;background:rgba(255,255,255,.03);font-size:11px;color:rgba(255,255,255,.65);animation:hero-in .8s .15s cubic-bezier(.22,1,.36,1) both}.hero-badge span,.eyebrow>span{width:6px;height:6px;border-radius:50%;background:var(--cyan);box-shadow:0 0 10px var(--cyan)}.hero h1{margin:25px 0 0;font-size:clamp(52px,7vw,88px);line-height:.93;letter-spacing:0;font-weight:650;animation:hero-in .9s .25s cubic-bezier(.22,1,.36,1) both}.hero h1 span,.pricing-title i{font-style:normal;color:transparent;background:linear-gradient(90deg,#061122 0%,#163e71 18%,#adf6ff 40%,#28d7ff 53%,#234c7b 75%,#081424 100%);background-size:200% auto;background-clip:text;-webkit-background-clip:text;filter:url(#misaka-noise);animation:shiny 6s linear infinite}.hero>p{width:min(590px,100%);margin:28px auto 0;color:rgba(255,255,255,.56);font-size:16px;line-height:1.75;animation:hero-in .8s .45s both}.hero-actions{display:flex;gap:10px;margin-top:29px;animation:hero-in .8s .6s both}.hero>small{margin-top:15px;color:rgba(255,255,255,.32);font-size:11px;animation:hero-in .8s .7s both}.system-strip{position:relative;z-index:2;height:41px;border-block:1px solid rgba(255,255,255,.09);background:rgba(0,0,0,.32);backdrop-filter:blur(14px)}.system-inner{height:100%;display:flex;align-items:center;justify-content:space-between;font-size:11px;color:rgba(255,255,255,.48)}.system-inner>div{display:flex;align-items:center;gap:20px}.system-inner b{color:#fff}.system-dot,.status-dot,.live i{width:6px;height:6px;border-radius:50%;background:#35df9a;box-shadow:0 0 9px #35df9a}.network-section{padding-block:75px 110px}.liquid-glass{position:relative;overflow:hidden;background:rgba(255,255,255,.025);backdrop-filter:blur(14px);box-shadow:inset 0 1px 1px rgba(255,255,255,.11)}.liquid-glass:before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;background:linear-gradient(180deg,rgba(255,255,255,.38),rgba(255,255,255,.07) 35%,rgba(255,255,255,.04) 65%,rgba(255,255,255,.28));-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}.window{border-radius:16px;background:rgba(10,12,17,.82);box-shadow:0 40px 100px rgba(0,0,0,.45)}.window-bar{height:44px;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;padding:0 15px;border-bottom:1px solid rgba(255,255,255,.08);font-size:10px;color:rgba(255,255,255,.43)}.window-bar>div{display:flex;gap:7px}.window-bar>div i{width:9px;height:9px;border-radius:50%;background:#ff5f57}.window-bar>div i:nth-child(2){background:#febc2e}.window-bar>div i:nth-child(3){background:#28c840}.window-bar .live{justify-self:end;align-items:center;gap:7px;color:#78ddb5}.network-body{display:grid;grid-template-columns:210px 280px 1fr;height:510px}.network-body aside{padding:20px 13px;border-right:1px solid rgba(255,255,255,.07);background:rgba(0,0,0,.18)}.side-brand{display:flex;align-items:center;font-size:13px;font-weight:700;margin:0 8px 20px}.connect-button{width:100%;height:38px;display:flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:8px;background:#fff;color:#111;font-size:11px;font-weight:700;margin-bottom:15px}.network-body aside>a{height:38px;padding:0 12px;border-radius:7px;display:flex;align-items:center;gap:11px;color:rgba(255,255,255,.48);font-size:11px}.network-body aside>a.active{background:rgba(255,255,255,.09);color:#fff}.node-list{border-right:1px solid rgba(255,255,255,.07)}.search{height:60px;display:flex;align-items:center;gap:8px;padding:0 18px;border-bottom:1px solid rgba(255,255,255,.07);color:rgba(255,255,255,.34);font-size:11px}.node-list article{height:78px;padding:0 16px;display:flex;align-items:center;gap:11px;border-bottom:1px solid rgba(255,255,255,.05)}.node-list article.selected{background:rgba(75,191,255,.08);box-shadow:inset 2px 0 var(--cyan)}.flag{width:32px;height:25px;border-radius:6px;display:grid;place-items:center;background:rgba(255,255,255,.08);font-size:9px;font-weight:700;color:rgba(255,255,255,.7)}.node-list article div{min-width:0;flex:1}.node-list b,.node-list small{display:block}.node-list b{font-size:11px}.node-list small{font-size:9px;color:rgba(255,255,255,.35);margin-top:6px}.latency{font-size:9px;color:#63dfae}.network-detail{position:relative;padding:23px 28px;overflow:hidden}.detail-head{display:flex;justify-content:space-between;font-size:9px;color:rgba(255,255,255,.4)}.detail-head>div{display:flex;align-items:center;gap:7px}.network-detail h2{margin:30px 0 5px;font-size:24px;letter-spacing:0}.network-detail>p{margin:0;color:rgba(255,255,255,.4);font-size:11px}.pulse-map{position:relative;height:190px;margin:5px 0}.core,.ring{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);border-radius:50%}.core{width:12px;height:12px;background:#fff;box-shadow:0 0 25px var(--cyan),0 0 60px var(--blue)}.ring{border:1px solid rgba(97,231,255,.25);animation:pulse 3s ease-out infinite}.r1{width:70px;height:70px}.r2{width:130px;height:130px;animation-delay:1s}.route{position:absolute;height:1px;width:110px;left:50%;top:50%;transform-origin:left;background:linear-gradient(90deg,var(--cyan),transparent)}.route-one{transform:rotate(25deg)}.route-two{transform:rotate(200deg)}.metrics{display:grid;grid-template-columns:repeat(3,1fr);border-block:1px solid rgba(255,255,255,.07)}.metrics div{padding:15px 5px}.metrics div+div{border-left:1px solid rgba(255,255,255,.07);padding-left:15px}.metrics span,.metrics strong{display:block}.metrics span{font-size:9px;color:rgba(255,255,255,.36)}.metrics strong{font-size:16px;margin-top:6px}.metrics small{font-size:8px;color:rgba(255,255,255,.4)}.detail-button{width:100%;height:40px;margin-top:16px;border:1px solid rgba(255,255,255,.1);border-radius:8px;background:rgba(255,255,255,.06);color:#fff;font-size:10px}.detail-button span{float:right}.feature-intro{padding:100px 0;display:grid;grid-template-columns:1fr 1fr;gap:90px;align-items:center}.eyebrow{display:flex;align-items:center;gap:9px;font-size:11px;color:rgba(255,255,255,.65)}.eyebrow em{font-style:normal;border:1px solid rgba(255,255,255,.1);border-radius:99px;padding:4px 8px;color:rgba(255,255,255,.34)}.feature-intro h2,.final-cta h2{font-size:clamp(40px,5vw,62px);line-height:1.02;letter-spacing:0;margin:22px 0 0;font-weight:600}.feature-intro>div>p,.final-cta>p{max-width:510px;color:rgba(255,255,255,.47);line-height:1.75;font-size:14px;margin-top:23px}.triage{border-radius:16px;padding:20px}.triage header{display:flex;justify-content:space-between;padding-bottom:15px;font-size:11px}.triage header small{color:rgba(255,255,255,.34)}.triage article{display:grid;grid-template-columns:32px 1fr 80px;gap:11px;align-items:center;padding:12px 10px;border-top:1px solid rgba(255,255,255,.06)}.triage b,.triage small{display:block}.triage b{font-size:11px}.triage small{font-size:9px;color:rgba(255,255,255,.35);margin-top:4px}.load{height:4px;background:rgba(255,255,255,.08);border-radius:99px}.load i{display:block;height:100%;background:linear-gradient(90deg,var(--blue),var(--cyan));border-radius:inherit}.feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;padding:30px 0 100px}.feature-grid article{min-height:260px;border-radius:15px;padding:25px;display:flex;flex-direction:column}.feature-icon{width:42px;height:42px;border:1px solid rgba(255,255,255,.1);border-radius:10px;display:grid;place-items:center;color:var(--cyan)}.feature-grid h3{margin:35px 0 10px;font-size:18px}.feature-grid p{margin:0;color:rgba(255,255,255,.43);font-size:12px;line-height:1.7}.feature-grid article>span{margin-top:auto;font-size:10px;color:rgba(255,255,255,.68)}.protocols{padding:70px 0 100px;border-top:1px solid rgba(255,255,255,.08);text-align:center}.protocols p{font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.28)}.protocols div{margin-top:35px;display:flex;justify-content:space-between;gap:20px}.protocols b{font-size:14px;color:rgba(255,255,255,.36);transition:.2s}.protocols b:hover{color:#fff}.pricing{position:relative;z-index:2;padding:55px 24px 90px}.pricing-title{text-align:center}.pricing-title>span{font-size:11px;color:rgba(255,255,255,.38)}.pricing-title h2{font-size:clamp(54px,8vw,118px);line-height:.87;margin:28px 0 0;font-weight:750;letter-spacing:0}.plan-grid{width:min(1100px,100%);margin:70px auto 0;display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.plan-card{min-height:510px;padding:40px 26px 28px;border:1px solid rgba(255,255,255,.45);border-radius:32px;background:linear-gradient(135deg,rgba(0,0,0,.72),rgba(10,12,15,.42));backdrop-filter:blur(15px);display:flex;flex-direction:column;transition:.5s cubic-bezier(.22,1,.36,1)}.plan-card:hover,.plan-card.featured{transform:translateY(-8px);border-color:rgba(97,231,255,.65);box-shadow:0 18px 60px rgba(14,164,217,.08)}.plan-card>small{color:rgba(255,255,255,.55)}.plan-card h3{font-size:36px;margin:8px 0 0;font-weight:500}.plan-card>p{min-height:46px;margin:20px 0 34px;color:rgba(255,255,255,.4);font-size:12px;line-height:1.6}.plan-card ul{list-style:none;margin:0;padding:0}.plan-card li{display:flex;gap:11px;align-items:center;margin-bottom:17px;font-size:12px;color:rgba(255,255,255,.72)}.plan-card li span{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.11);font-size:10px}.plan-card>a{margin:auto auto 0;padding:11px 22px;border-radius:99px;background:#fff;color:#000;font-size:11px;font-weight:700}.billing-toggle{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:28px;color:rgba(255,255,255,.35);font-size:11px}.billing-toggle span.active{color:#fff}.billing-toggle button{width:50px;height:27px;border:0;border-radius:99px;background:#fff;padding:0;position:relative}.billing-toggle button i{position:absolute;left:4px;top:4px;width:19px;height:19px;border-radius:50%;background:#050505;transition:.25s}.billing-toggle button.active{background:rgba(255,255,255,.18)}.billing-toggle button.active i{transform:translateX(23px);background:#fff}.pricing-note{text-align:center;color:rgba(255,255,255,.25);font-size:10px;margin-top:14px}.final-cta{margin-block:50px 110px;min-height:500px;border-radius:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:70px 30px}.cta-glow{position:absolute;inset:0;background:radial-gradient(500px circle at 50% 0,rgba(97,231,255,.16),transparent 70%);pointer-events:none}.final-cta>p{margin-inline:auto}.final-cta>div:last-child{display:flex;gap:10px;margin-top:22px}.footer{min-height:100px;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:25px;color:rgba(255,255,255,.32);font-size:10px}.footer p{margin-right:auto}.reveal{opacity:0;transform:translateY(28px);transition:opacity .75s ease,transform .75s cubic-bezier(.22,1,.36,1)}.reveal.is-visible{opacity:1;transform:none}@keyframes nav-in{from{opacity:0;transform:translateY(-10px)}}@keyframes hero-in{from{opacity:0;transform:translateY(20px)}}@keyframes shiny{from{background-position:-200% center}to{background-position:200% center}}@keyframes pulse{0%{opacity:.8;transform:translate(-50%,-50%) scale(.5)}to{opacity:0;transform:translate(-50%,-50%) scale(1.5)}}
@media(max-width:900px){.guide{display:none}.network-body{grid-template-columns:165px 220px 1fr}.feature-intro{gap:45px}.plan-grid{display:flex;width:100vw;overflow-x:auto;scroll-snap-type:x mandatory;margin-left:-24px;padding:10px 24px 20px}.plan-card{flex:0 0 320px;scroll-snap-align:center}.pricing-title h2{font-size:58px}.protocols div{flex-wrap:wrap;justify-content:center}.protocols b{width:28%}}
@media(max-width:700px){.wrap{width:min(100% - 32px,1152px)}.nav{height:72px}.nav .logo>span:last-child{display:none}.nav-cta{display:none}.menu-button{display:grid}.nav-links{position:absolute;left:0;right:0;top:64px;display:none;flex-direction:column;gap:0;padding:8px;border:1px solid rgba(255,255,255,.1);border-radius:12px;background:rgba(10,11,14,.95);backdrop-filter:blur(18px)}.nav-links.open{display:flex}.nav-links a{padding:13px}.hero{padding:72px 0 80px}.hero h1{font-size:50px}.hero>p{font-size:14px}.system-inner>div:first-child span:not(.system-dot){display:none}.network-section{width:calc(100% - 16px);padding:35px 0 80px}.window-bar{grid-template-columns:1fr auto}.window-bar>span:nth-child(2){display:none}.network-body{grid-template-columns:82px 1fr;height:470px}.network-body aside{padding:15px 8px}.side-brand{justify-content:center;margin-inline:0}.side-brand:not(.logo-mark){font-size:0}.connect-button{font-size:0}.connect-button svg{margin:0}.network-body aside>a{justify-content:center;font-size:0;padding:0}.node-list{border-right:0}.network-detail{display:none}.feature-intro{grid-template-columns:1fr;padding:75px 0;gap:40px}.feature-grid{grid-template-columns:1fr;padding-bottom:75px}.feature-grid article{min-height:220px}.protocols{padding:55px 0 70px}.protocols b{width:40%}.pricing{padding-inline:16px}.plan-grid{margin-left:-16px;padding-inline:16px}.pricing-title h2{font-size:48px}.final-cta{min-height:430px;margin-block:20px 80px}.final-cta>div:last-child{flex-direction:column;width:100%}.footer{flex-wrap:wrap;gap:8px;padding:24px 0}.footer p{width:100%;order:3}.footer>span{margin-left:auto}}
@media(prefers-reduced-motion:reduce){.landing *{animation-duration:.01ms!important;transition-duration:.01ms!important}.reveal{opacity:1;transform:none}}
@media (min-width: 1200px) {
  .wrap { width: min(1360px, calc(100% - 48px)); }
  .guide-left { left: calc(50% - 680px); }
  .guide-right { right: calc(50% - 680px); }
  .nav { height: 98px; }
  .logo { font-size: 17px; }
  .nav-links { gap: 42px; }
  .nav-links a { font-size: 14px; }
  .pill { min-height: 48px; padding-inline: 24px; font-size: 14px; }
  .hero { height: calc(100svh - 144px); padding: 40px 0; justify-content: center; }
  .hero h1 { font-size: 100px; }
  .hero > p { width: 640px; font-size: 17px; }
  .hero > small { font-size: 12px; }
  .system-strip { height: 46px; }
  .system-inner { font-size: 12px; }
  .network-section { padding-block: 88px 124px; }
  .window-bar { height: 48px; font-size: 11px; }
  .network-body { height: 550px; grid-template-columns: 225px 310px 1fr; }
  .feature-intro { padding-block: 120px; }
  .feature-grid { padding-bottom: 120px; }
  .protocols { padding-block: 86px 120px; }
}
@media (min-width: 701px) and (max-width: 1199px) {
  .hero { height: calc(100svh - 127px); padding: 32px 0; justify-content: center; }
}
@media (max-width: 700px) {
  .hero { height: calc(100svh - 113px); padding: 32px 0; justify-content: center; }
}

.node-entry {
  width: 100%;
  height: 78px;
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 16px;
  border: 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.node-entry > span:nth-child(2) { min-width: 0; flex: 1; }
.node-entry.selected { background: rgba(75,191,255,.08); box-shadow: inset 2px 0 var(--cyan); }
.node-entry:hover { background: rgba(255,255,255,.05); }
.node-entry:focus-visible { outline: 1px solid var(--cyan); outline-offset: -2px; }
.node-list-state { min-height: 78px; padding: 16px; display: grid; place-items: center; color: rgba(255,255,255,.35); font-size: 10px; line-height: 1.6; text-align: center; }
.status-dot.offline { background: #e05267; box-shadow: 0 0 9px rgba(224,82,103,.8); }
.status-dot.warming { background: #efb82d; box-shadow: 0 0 9px rgba(239,184,45,.8); }
.network-empty { display: flex; flex-direction: column; }
.network-empty .detail-button { margin-top: auto; }
.triage article { grid-template-columns: 32px minmax(0, 1fr) auto; }
.triage-status { color: #63dfae; font-size: 9px; font-weight: 700; }
.triage-status.offline { color: #e58b9a; }
.triage-status.warming { color: #efc55b; }
.triage-empty { padding: 24px 10px 8px; color: rgba(255,255,255,.35); font-size: 10px; text-align: center; }
.landing-data-state { width: min(1100px, calc(100% - 32px)); min-height: 230px; margin: 70px auto 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; border: 1px dashed rgba(255,255,255,.2); border-radius: 24px; color: rgba(255,255,255,.45); font-size: 12px; text-align: center; }
.landing-data-state p { margin: 0; }
.landing-data-state a { color: var(--cyan); font-weight: 700; }
.landing-data-state a span { transition: transform .2s; }
.landing-data-state a:hover span { display: inline-block; transform: translateX(2px); }

@media (max-width: 700px) {
  .node-entry { height: 68px; padding-inline: 11px; }
  .node-list-state { min-height: 68px; padding-inline: 10px; }
  .landing-data-state { width: calc(100% - 16px); margin-top: 40px; }
}
</style>

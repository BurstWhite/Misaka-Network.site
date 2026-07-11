<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Icon from '@/shared/Icon.vue'
import DropdownMenu from '@/shared/DropdownMenu.vue'
import { runtimeConfig } from '@/app/config'
import { setCurrencySymbol } from '@/app/config'
import { userApi } from '@/api/services'
import { useThemeStore, type ThemeMode } from '@/stores/theme'
import { useLocaleStore } from '@/stores/locale'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const theme = useThemeStore()
const locale = useLocaleStore()
const auth = useAuthStore()
const mobileOpen = ref(false)
const nav = computed(() => [
  ['dashboard', 'dashboard', '/dashboard'], ['card', 'subscription', '/subscription'], ['cart', 'plans', '/plans'],
  ['receipt', 'orders', '/orders'], ['users', 'invite', '/invite'], ['ticket', 'tickets', '/tickets'],
  ['chart', 'traffic', '/traffic'], ['server', 'servers', '/servers'], ['book', 'docs', '/knowledge'],
  ['gift', 'gifts', '/gifts'], ['user', 'profile', '/profile'],
])
const themeOptions = computed(() => [
  { value: 'system', label: t('theme.system'), icon: 'monitor' }, { value: 'light', label: t('theme.light'), icon: 'sun' }, { value: 'dark', label: t('theme.dark'), icon: 'moon' },
])
function logOut() { auth.logout(); router.push('/login') }
onMounted(async () => {
  theme.start(); auth.loadUser()
  try { setCurrencySymbol((await userApi.config()).currency_symbol) } catch { /* optional user settings */ }
})
</script>

<template>
  <div class="app-shell">
    <aside :class="['sidebar', { open: mobileOpen }]">
      <div class="brand"><span class="brand-mark"><i /><i /></span><span>{{ runtimeConfig.appName }}</span><button class="icon-button sidebar-close" type="button" aria-label="关闭菜单" @click="mobileOpen = false"><Icon name="x" /></button></div>
      <nav class="sidebar-nav">
        <RouterLink v-for="([icon, label, path]) in nav" :key="path" :to="path" @click="mobileOpen = false"><Icon :name="icon" /><span>{{ t(`nav.${label}`) }}</span></RouterLink>
      </nav>
      <div class="sidebar-user"><div class="avatar">{{ (auth.user?.email || 'M').slice(0, 1).toUpperCase() }}</div><div><strong>{{ auth.user?.email?.split('@')[0] || 'Misaka 用户' }}</strong><small>{{ auth.user?.email || runtimeConfig.version }}</small></div><button class="icon-button" :aria-label="t('common.logout')" @click="logOut"><Icon name="logout" /></button></div>
    </aside>
    <button v-if="mobileOpen" class="sidebar-backdrop" aria-label="关闭菜单" @click="mobileOpen = false" />
    <main class="main-shell">
      <header class="topbar">
        <button class="icon-button mobile-menu" type="button" aria-label="打开菜单" @click="mobileOpen = true"><Icon name="menu" /></button>
        <div class="topbar-spacer" />
        <DropdownMenu :model-value="locale.locale" label="语言" icon="globe" :options="locale.options" @update:model-value="locale.setLocale" />
        <DropdownMenu :model-value="theme.mode" :label="t('theme.label')" :icon="theme.mode === 'system' ? 'monitor' : theme.mode" :options="themeOptions" @update:model-value="theme.setMode($event as ThemeMode)" />
      </header>
      <section class="page"><RouterView /></section>
    </main>
  </div>
</template>

import { defineStore } from 'pinia'
import i18n, { localeLabels } from '@/i18n'

export const useLocaleStore = defineStore('locale', {
  state: () => ({ locale: localStorage.getItem('misaka.locale') || 'zh-CN' }),
  getters: { options: () => Object.entries(localeLabels).map(([value, label]) => ({ value, label })) },
  actions: {
    setLocale(locale: string) {
      this.locale = locale
      localStorage.setItem('misaka.locale', locale)
      i18n.global.locale.value = locale as any
      document.documentElement.lang = locale
    },
  },
})

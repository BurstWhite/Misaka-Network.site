import { defineStore } from 'pinia'
import { authApi, userApi } from '@/api/services'
import { clearToken, readToken, saveToken } from './auth-token'

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null as any, loading: false }),
  getters: { authenticated: () => Boolean(readToken()) },
  actions: {
    async login(payload: { email: string; password: string }) {
      const result = await authApi.login(payload)
      saveToken(result.auth_data || result.token || result)
      await this.loadUser()
    },
    async loadUser() {
      if (!readToken()) return false
      try { this.user = await userApi.info(); return true } catch { clearToken(); this.user = null; return false }
    },
    logout() { clearToken(); this.user = null },
  },
})

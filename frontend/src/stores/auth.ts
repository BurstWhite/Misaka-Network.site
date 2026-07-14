import { defineStore } from 'pinia'
import { authApi, userApi } from '@/api/services'
import { clearToken, readToken, saveToken } from './auth-token'

let userLoadPromise: Promise<boolean> | null = null

export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null as any, config: null as any, loading: false }),
  getters: { authenticated: () => Boolean(readToken()) },
  actions: {
    async login(payload: { email: string; password: string }) {
      const result = await authApi.login(payload)
      await this.loginResult(result)
    },
    async loginResult(result: any) {
      saveToken(result.auth_data || result.token || result)
      await this.loadUser()
    },
    async register(payload: object) {
      const result = await authApi.register(payload)
      saveToken(result.auth_data || result.token || result)
      await this.loadUser()
    },
    async loadConfig() {
      if (this.config) return this.config
      try { this.config = await authApi.config(); return this.config } catch { this.config = {}; return this.config }
    },
    async loadUser() {
      if (!readToken()) return false
      if (userLoadPromise) return userLoadPromise
      this.loading = true
      userLoadPromise = (async () => {
        try { this.user = await userApi.info(); return true } catch { clearToken(); this.user = null; return false }
      })()
      try { return await userLoadPromise } finally { userLoadPromise = null; this.loading = false }
    },
    logout() { clearToken(); this.user = null },
  },
})

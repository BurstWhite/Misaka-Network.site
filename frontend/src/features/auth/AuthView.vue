<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/services'

const { t } = useI18n(); const route = useRoute(); const router = useRouter(); const auth = useAuthStore()
const mode = computed(() => String(route.meta.mode || 'login'))
const form = reactive({ email: '', password: '', email_code: '', invite_code: '' })
const loading = ref(false); const message = ref(''); const error = ref('')
async function submit() {
  loading.value = true; error.value = ''; message.value = ''
  try {
    if (mode.value === 'login') { await auth.login(form); await router.push(String(route.query.redirect || '/dashboard')) }
    else if (mode.value === 'register') { const result = await authApi.register(form); localStorage.setItem('misaka.access_token', result.auth_data || result.token || result); await router.push('/dashboard') }
    else { await authApi.forget(form); message.value = '密码已重置'; setTimeout(() => router.push('/login'), 800) }
  } catch (e: any) { error.value = e.message }
  finally { loading.value = false }
}
async function sendCode() { try { await authApi.sendCode({ email: form.email }); message.value = '验证码已发送' } catch (e: any) { error.value = e.message } }
</script>

<template><AuthLayout><form class="auth-form" @submit.prevent="submit"><div class="auth-title"><h2>{{ mode === 'login' ? t('auth.welcome') : t(`auth.${mode}`) }}</h2><p>{{ t('auth.intro') }}</p></div><label>{{ t('auth.email') }}<input v-model.trim="form.email" type="email" autocomplete="email" required /></label><label>{{ t('auth.password') }}<input v-model="form.password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required /></label><label v-if="mode !== 'login'">{{ t('auth.code') }}<span class="input-action"><input v-model="form.email_code" required /><button type="button" @click="sendCode">{{ t('auth.sendCode') }}</button></span></label><label v-if="mode === 'register'">{{ t('auth.invite') }}<input v-model="form.invite_code" /></label><p v-if="error" class="form-message error">{{ error }}</p><p v-if="message" class="form-message success">{{ message }}</p><button class="button primary wide" :disabled="loading">{{ loading ? t('common.loading') : t(`auth.${mode}`) }}</button><div class="auth-links"><template v-if="mode === 'login'"><RouterLink to="/register">{{ t('auth.register') }}</RouterLink><RouterLink to="/forgetpassword">{{ t('auth.forget') }}</RouterLink></template><RouterLink v-else to="/login">{{ t('auth.back') }}</RouterLink></div></form></AuthLayout></template>

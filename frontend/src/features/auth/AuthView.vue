<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/services'
import CaptchaWidget from './CaptchaWidget.vue'

const { t } = useI18n(); const route = useRoute(); const router = useRouter(); const auth = useAuthStore()
const mode = computed(() => String(route.meta.mode || 'login'))
const form = reactive({ email: '', password: '', email_code: '', invite_code: '' })
const captchaToken = ref('')
const loading = ref(false); const message = ref(''); const error = ref(''); const codeLoading = ref(false); const codeCooldown = ref(0)
const requiresEmailCode = computed(() => mode.value === 'forget' || Boolean(auth.config?.is_email_verify))
const requiresInvite = computed(() => mode.value === 'register' && Boolean(auth.config?.is_invite_force))
let cooldownTimer: number | undefined
onMounted(() => auth.loadConfig())
onBeforeUnmount(() => { if (cooldownTimer) window.clearInterval(cooldownTimer) })
async function captchaPayload() {
  if (!auth.config?.is_captcha) return {}
  if (auth.config.captcha_type === 'recaptcha-v3') {
    const grecaptcha = (window as any).grecaptcha
    if (!grecaptcha) throw new Error('人机验证尚未加载，请稍后重试')
    const token = await new Promise<string>((resolve) => grecaptcha.ready(async () => resolve(await grecaptcha.execute(auth.config.recaptcha_v3_site_key, { action: 'submit' }))))
    return { recaptcha_v3_token: token }
  }
  if (!captchaToken.value) throw new Error('请完成人机验证')
  return auth.config.captcha_type === 'turnstile' ? { turnstile_token: captchaToken.value } : { recaptcha_data: captchaToken.value }
}
async function submit() {
  loading.value = true; error.value = ''; message.value = ''
  try {
    await auth.loadConfig()
    const captcha = mode.value === 'login' ? {} : await captchaPayload()
    if (mode.value === 'login') { await authApi.login(form).then(async (result) => { await auth.loginResult(result) }); await router.push(String(route.query.redirect || '/dashboard')) }
    else if (mode.value === 'register') { await auth.register({ ...form, ...captcha }); await router.push('/dashboard') }
    else { await authApi.forget({ ...form, ...captcha }); message.value = '密码已重置'; setTimeout(() => router.push('/login'), 800) }
  } catch (e: any) { error.value = e.message }
  finally { loading.value = false }
}
async function sendCode() {
  if (!form.email || codeLoading.value || codeCooldown.value) return
  codeLoading.value = true; error.value = ''
  try {
    await auth.loadConfig()
    await authApi.sendCode({ email: form.email, ...await captchaPayload() }); message.value = '验证码已发送'; codeCooldown.value = 60
    cooldownTimer = window.setInterval(() => { codeCooldown.value -= 1; if (codeCooldown.value <= 0) { window.clearInterval(cooldownTimer); cooldownTimer = undefined } }, 1000)
  } catch (e: any) { error.value = e.message } finally { codeLoading.value = false }
}
</script>

<template><AuthLayout><form class="auth-form" @submit.prevent="submit"><div class="auth-title"><h2>{{ mode === 'login' ? t('auth.welcome') : t(`auth.${mode}`) }}</h2><p>{{ t('auth.intro') }}</p></div><label>{{ t('auth.email') }}<input v-model.trim="form.email" type="email" autocomplete="email" required /></label><label>{{ t('auth.password') }}<input v-model="form.password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" required /></label><label v-if="mode !== 'login' && requiresEmailCode">{{ t('auth.code') }}<span class="input-action"><input v-model="form.email_code" inputmode="numeric" pattern="[0-9]{6}" :required="requiresEmailCode" /><button type="button" :disabled="codeLoading || Boolean(codeCooldown)" @click="sendCode">{{ codeCooldown ? `${codeCooldown}s` : t('auth.sendCode') }}</button></span></label><label v-if="mode === 'register'">{{ t('auth.invite') }}<input v-model="form.invite_code" :required="requiresInvite" /></label><CaptchaWidget v-if="auth.config?.is_captcha" v-model="captchaToken" :type="auth.config.captcha_type" :site-key="auth.config.captcha_type === 'turnstile' ? auth.config.turnstile_site_key : auth.config.recaptcha_site_key"/><p v-if="error" class="form-message error">{{ error }}</p><p v-if="message" class="form-message success">{{ message }}</p><button class="button primary wide" :disabled="loading">{{ loading ? t('common.loading') : t(`auth.${mode}`) }}</button><div class="auth-links"><template v-if="mode === 'login'"><RouterLink v-if="auth.registerEnabled" to="/register">{{ t('auth.register') }}</RouterLink><RouterLink to="/forgetpassword">{{ t('auth.forget') }}</RouterLink></template><RouterLink v-else to="/login">{{ t('auth.back') }}</RouterLink></div></form></AuthLayout></template>

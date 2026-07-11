<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps<{ type: string; siteKey: string; action?: string; modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const root = ref<HTMLElement>()
const scriptUrls = new Set<string>()

function loadScript(url: string): Promise<void> {
  if (scriptUrls.has(url)) return Promise.resolve()
  const existing = document.querySelector(`script[src="${url}"]`)
  if (existing) { scriptUrls.add(url); return Promise.resolve() }
  return new Promise((resolve, reject) => {
    const script = document.createElement('script'); script.src = url; script.async = true; script.defer = true
    script.onload = () => { scriptUrls.add(url); resolve() }; script.onerror = () => reject(new Error('验证码加载失败'))
    document.head.appendChild(script)
  })
}

async function render() {
  if (!props.siteKey || props.type === 'recaptcha-v3') return
  await nextTick()
  if (!root.value || root.value.dataset.rendered) return
  if (props.type === 'turnstile') {
    await loadScript('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit')
    const turnstile = (window as any).turnstile
    if (turnstile) { turnstile.render(root.value, { sitekey: props.siteKey, callback: (token: string) => emit('update:modelValue', token), 'expired-callback': () => emit('update:modelValue', '') }); root.value.dataset.rendered = '1' }
  } else {
    await loadScript('https://www.google.com/recaptcha/api.js?render=explicit')
    const grecaptcha = (window as any).grecaptcha
    if (grecaptcha) { grecaptcha.render(root.value, { sitekey: props.siteKey, callback: (token: string) => emit('update:modelValue', token), 'expired-callback': () => emit('update:modelValue', '') }); root.value.dataset.rendered = '1' }
  }
}

onMounted(() => render().catch(() => undefined))
watch(() => [props.type, props.siteKey], () => render().catch(() => undefined))
</script>

<template><div v-if="type !== 'recaptcha-v3'" ref="root" class="captcha-widget" aria-label="人机验证" /></template>

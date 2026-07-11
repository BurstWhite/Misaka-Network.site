<script setup lang="ts">
import { runtimeConfig } from '@/app/config'
</script>
<template><main class="auth-shell"><section class="auth-brand"><div class="auth-brand-inner"><span class="brand-mark large"><i /><i /></span><h1>{{ runtimeConfig.appName }}</h1><p>{{ runtimeConfig.description }}</p><div class="auth-orbit"><span /><span /><span /></div></div></section><section class="auth-panel"><slot /></section></main></template>

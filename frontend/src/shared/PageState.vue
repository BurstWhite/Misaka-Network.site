<script setup lang="ts">
defineProps<{ loading?: boolean; error?: string; empty?: boolean }>()
defineEmits<{ retry: [] }>()
</script>
<template><div class="page-state-root"><div v-if="loading" class="page-state"><span class="spinner" />正在加载</div><div v-else-if="error" class="page-state error"><p>{{ error }}</p><button class="button secondary" @click="$emit('retry')">重试</button></div><div v-else-if="empty" class="page-state">暂无数据</div><slot v-else /></div></template>

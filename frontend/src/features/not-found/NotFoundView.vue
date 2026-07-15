<script setup lang="ts">
import { RouterLink } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
</script>

<template>
  <AuthLayout>
    <main class="not-found">
      <strong>404</strong>
      <h2>页面不存在</h2>
      <p>你访问的地址不可用，或注册功能当前已关闭。</p>
      <RouterLink class="button primary" to="/login">返回登录</RouterLink>
    </main>
  </AuthLayout>
</template>

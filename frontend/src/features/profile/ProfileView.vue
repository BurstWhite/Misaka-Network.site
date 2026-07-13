<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { userApi } from '@/api/services'
import PageState from '@/shared/PageState.vue'
import Icon from '@/shared/Icon.vue'
import { date } from '@/shared/format'

const loading = ref(true)
const error = ref('')
const message = ref('')
const user = ref<any>({})
const sessions = ref<any[]>([])
const passwordOpen = ref(false)
const passwordSaving = ref(false)
const passwordError = ref('')
const form = reactive({ remind_expire: false, remind_traffic: false })
const passwordForm = reactive({ old_password: '', new_password: '', confirm_password: '' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    [user.value, sessions.value] = await Promise.all([userApi.info(), userApi.sessions().catch(() => [])])
    form.remind_expire = Boolean(user.value.remind_expire)
    form.remind_traffic = Boolean(user.value.remind_traffic)
  } catch (e: any) { error.value = e.message } finally { loading.value = false }
}

async function save() {
  try {
    await userApi.update({ remind_expire: Number(form.remind_expire), remind_traffic: Number(form.remind_traffic) })
    message.value = '设置已保存'
  } catch (e: any) { error.value = e.message }
}

function closePassword() {
  passwordOpen.value = false
  passwordError.value = ''
  passwordForm.old_password = passwordForm.new_password = passwordForm.confirm_password = ''
}

async function password() {
  passwordError.value = ''
  if (passwordForm.new_password.length < 8) { passwordError.value = '新密码至少需要 8 位'; return }
  if (passwordForm.new_password !== passwordForm.confirm_password) { passwordError.value = '两次输入的新密码不一致'; return }
  passwordSaving.value = true
  try {
    await userApi.changePassword({ old_password: passwordForm.old_password, new_password: passwordForm.new_password })
    message.value = '密码已修改'
    closePassword()
  } catch (e: any) { passwordError.value = e.message } finally { passwordSaving.value = false }
}

async function removeSession(id: string) {
  try {
    await userApi.removeSession({ session_id: id })
    sessions.value = sessions.value.filter((session) => String(session.id) !== String(id))
    message.value = '设备已移除'
  } catch (e: any) { error.value = e.message }
}

onMounted(load)
</script>

<template>
  <PageState :loading="loading" :error="error" @retry="load">
    <div class="page-heading">
      <div><h1>个人中心</h1><p>{{ user.email }}</p></div>
      <button class="button secondary" type="button" @click="passwordOpen = true">修改密码</button>
    </div>
    <form class="panel form-panel profile-settings" @submit.prevent="save">
      <h2>通知设置</h2>
      <label class="toggle-row"><span><strong>到期邮件提醒</strong><small>订阅即将到期时发送邮件</small></span><input v-model="form.remind_expire" type="checkbox" /></label>
      <label class="toggle-row"><span><strong>流量邮件提醒</strong><small>流量接近用完时发送邮件</small></span><input v-model="form.remind_traffic" type="checkbox" /></label>
      <button class="button primary">保存设置</button>
    </form>
    <p v-if="message" class="form-message success">{{ message }}</p>
    <section class="panel sessions-panel">
      <header><div><h2>最近登录设备</h2><p>管理当前账号的活跃会话</p></div><span class="session-count">{{ sessions.length }} 台设备</span></header>
      <div class="session-list"><article v-for="session in sessions" :key="session.id" class="session-row"><div class="session-device"><span class="session-icon"><Icon name="monitor" :size="18" /></span><div><strong>{{ session.device || '历史会话' }} <em v-if="session.current" class="current-session">当前设备</em></strong><small><span v-if="session.current" class="session-online" />{{ session.ip || '历史记录未保存 IP' }}</small></div></div><div class="session-meta"><span><small>最后活动</small><time>{{ date(session.last_login_at || session.created_at, true) }}</time></span><button class="session-remove" type="button" @click="removeSession(session.id)"><Icon name="x" :size="14" />移除</button></div></article></div>
      <div v-if="!sessions.length" class="page-state">暂无活跃设备</div>
    </section>

    <div v-if="passwordOpen" class="modal-backdrop password-modal-backdrop" @click.self="closePassword">
      <form class="modal password-modal" role="dialog" aria-modal="true" aria-labelledby="password-title" @submit.prevent="password">
        <header><div><h2 id="password-title">修改密码</h2><p>修改后其他设备将退出登录。</p></div><button class="icon-button" type="button" aria-label="关闭" @click="closePassword"><Icon name="x" :size="18" /></button></header>
        <label>旧密码<input v-model="passwordForm.old_password" type="password" autocomplete="current-password" required /></label>
        <label>新密码<input v-model="passwordForm.new_password" type="password" autocomplete="new-password" minlength="8" required /></label>
        <label>确认新密码<input v-model="passwordForm.confirm_password" type="password" autocomplete="new-password" minlength="8" required /></label>
        <p v-if="passwordError" class="form-message error">{{ passwordError }}</p>
        <footer><button class="button secondary" type="button" @click="closePassword">取消</button><button class="button primary" :disabled="passwordSaving">{{ passwordSaving ? '修改中…' : '确认修改' }}</button></footer>
      </form>
    </div>
  </PageState>
</template>

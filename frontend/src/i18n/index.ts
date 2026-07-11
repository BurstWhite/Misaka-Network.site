import { createI18n } from 'vue-i18n'

export const localeLabels: Record<string, string> = {
  'zh-CN': '简体中文', 'zh-TW': '繁體中文', 'en-US': 'English', 'ja-JP': '日本語',
  'vi-VN': 'Tiếng Việt', 'ko-KR': '한국어', 'ru-RU': 'Русский', 'fa-IR': 'فارسی',
}

const zh = {
  nav: { dashboard: '仪表盘', subscription: '我的订阅', plans: '购买订阅', orders: '我的订单', invite: '我的邀请', tickets: '我的工单', traffic: '流量明细', docs: '使用文档', servers: '节点状态', profile: '个人中心', gifts: '礼品卡' },
  common: { loading: '正在加载', retry: '重试', save: '保存', cancel: '取消', confirm: '确认', details: '详情', empty: '暂无数据', logout: '退出登录', more: '查看全部', submit: '提交', close: '关闭' },
  theme: { system: '跟随系统', light: '亮色', dark: '暗色', label: '外观主题' },
  dashboard: { greeting: '晚上好，欢迎回来', subscription: '当前订阅', remaining: '剩余流量', reset: '下次重置', view: '查看订阅', renew: '续费', usage: '流量使用情况', notices: '公告', recentOrders: '最近订单', buy: '购买套餐', import: '导入客户端', ticket: '提交工单' },
  auth: { login: '登录', register: '注册', forget: '重置密码', email: '邮箱地址', password: '密码', code: '邮箱验证码', invite: '邀请码（选填）', sendCode: '发送验证码', back: '返回登录', welcome: '欢迎回来', intro: '登录后管理你的订阅、订单与技术支持。' },
}

const en = {
  nav: { dashboard: 'Dashboard', subscription: 'Subscription', plans: 'Buy a plan', orders: 'Orders', invite: 'Invites', tickets: 'Tickets', traffic: 'Traffic', docs: 'Guides', servers: 'Servers', profile: 'Account', gifts: 'Gift cards' },
  common: { loading: 'Loading', retry: 'Retry', save: 'Save', cancel: 'Cancel', confirm: 'Confirm', details: 'Details', empty: 'No data', logout: 'Log out', more: 'View all', submit: 'Submit', close: 'Close' },
  theme: { system: 'System', light: 'Light', dark: 'Dark', label: 'Appearance' },
  dashboard: { greeting: 'Good evening, welcome back', subscription: 'Current subscription', remaining: 'Remaining data', reset: 'Next reset', view: 'View subscription', renew: 'Renew', usage: 'Traffic usage', notices: 'Announcements', recentOrders: 'Recent orders', buy: 'Buy a plan', import: 'Import client', ticket: 'Create ticket' },
  auth: { login: 'Log in', register: 'Register', forget: 'Reset password', email: 'Email address', password: 'Password', code: 'Email code', invite: 'Invite code (optional)', sendCode: 'Send code', back: 'Back to login', welcome: 'Welcome back', intro: 'Manage subscriptions, orders, and support in one place.' },
}

const messages = Object.fromEntries(Object.keys(localeLabels).map((key) => [key, key === 'en-US' ? en : zh]))
const locale = localStorage.getItem('misaka.locale') || 'zh-CN'
document.documentElement.lang = locale

export default createI18n({ legacy: false, locale, fallbackLocale: 'zh-CN', messages })

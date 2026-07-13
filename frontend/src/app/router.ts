import { createRouter, createWebHashHistory } from 'vue-router'
import { readToken } from '@/stores/auth-token'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', component: () => import('@/features/landing/LandingView.vue'), meta: { public: true } },
    { path: '/login', component: () => import('@/features/auth/AuthView.vue'), meta: { public: true, mode: 'login' } },
    { path: '/register', component: () => import('@/features/auth/AuthView.vue'), meta: { public: true, mode: 'register' } },
    { path: '/forgetpassword', component: () => import('@/features/auth/AuthView.vue'), meta: { public: true, mode: 'forget' } },
    {
      path: '/', component: () => import('@/layouts/AppShell.vue'), children: [
        { path: 'dashboard', component: () => import('@/features/dashboard/DashboardView.vue') },
        { path: 'subscription', component: () => import('@/features/subscription/SubscriptionView.vue') },
        { path: 'plans', component: () => import('@/features/plans/PlansView.vue') },
        { path: 'plan/:id', component: () => import('@/features/plans/PlansView.vue') },
        { path: 'orders', component: () => import('@/features/orders/OrdersView.vue') },
        { path: 'order/:tradeNo', component: () => import('@/features/orders/OrderDetailView.vue') },
        { path: 'tickets', component: () => import('@/features/tickets/TicketsView.vue') },
        { path: 'invite', component: () => import('@/features/data/DataView.vue'), meta: { kind: 'invite' } },
        { path: 'traffic', component: () => import('@/features/data/DataView.vue'), meta: { kind: 'traffic' } },
        { path: 'servers', component: () => import('@/features/data/DataView.vue'), meta: { kind: 'servers' } },
        { path: 'knowledge', component: () => import('@/features/data/DataView.vue'), meta: { kind: 'knowledge' } },
        { path: 'gifts', component: () => import('@/features/data/DataView.vue'), meta: { kind: 'gifts' } },
        { path: 'profile', component: () => import('@/features/profile/ProfileView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ],
})

router.beforeEach((to) => {
  const loggedIn = Boolean(readToken())
  if (!to.meta.public && !loggedIn) return { path: '/login', query: { redirect: to.fullPath } }
  if (to.meta.public && loggedIn && to.path === '/login') return '/dashboard'
})

export default router

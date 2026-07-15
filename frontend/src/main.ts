import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './app/router'
import i18n from './i18n'
import { applyStoredTheme } from './stores/theme'
import { runtimeConfig } from './app/config'
import './styles/index.css'
import './styles/responsive-fixes.css'
import './styles/not-found.css'

document.documentElement.style.setProperty('--accent', runtimeConfig.theme.primaryColor || '#3155ee')
if (runtimeConfig.theme.backgroundUrl) document.documentElement.style.setProperty('--configured-background', `url(${JSON.stringify(runtimeConfig.theme.backgroundUrl)})`)
applyStoredTheme()

createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')

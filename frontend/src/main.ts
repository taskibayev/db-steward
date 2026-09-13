import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import { messages } from './locales'
import './style.css'
const app = createApp(App)
app.use(createPinia())
app.use(createI18n({ legacy: false, locale: 'ru', fallbackLocale: 'ru', messages }))
app.mount('#app')

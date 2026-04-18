import { createApp } from 'vue'
import { createHead } from '@unhead/vue/client'
import App from './App.vue'
import { router } from './router'
import './assets/styles/globals.css'

const app = createApp(App)
app.use(router)
app.use(createHead())
app.mount('#app')

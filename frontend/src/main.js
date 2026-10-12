import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import { installRequiredFieldHighlighter } from './utils/required-field-highlighter'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

installRequiredFieldHighlighter()

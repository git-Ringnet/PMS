import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import { installRequiredFieldHighlighter } from './utils/required-field-highlighter'
import { installBroadcastOrigin } from './utils/broadcast-origin'
import { installTranslator } from './utils/translator'

installBroadcastOrigin()

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

installRequiredFieldHighlighter()
installTranslator()

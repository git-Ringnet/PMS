import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Khởi tạo Laravel Echo kết nối với Laravel Reverb (Tự động thích ứng Local/Production)
const isHttps = window.location.protocol === 'https:'

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'pmsreverbkey',
  wsHost: window.location.hostname || '127.0.0.1',
  wsPort: 8090,
  wssPort: 443,
  forceTLS: isHttps,
  enabledTransports: ['ws', 'wss'],
})

export default echo

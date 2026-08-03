import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Khởi tạo Laravel Echo kết nối với Laravel Reverb (chạy ở port 8080)
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'pmsreverbkey',
  wsHost: window.location.hostname || '127.0.0.1',
  wsPort: 8090,
  wssPort: 8090,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
})

export default echo

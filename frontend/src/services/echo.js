import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Khởi tạo Laravel Echo kết nối với Laravel Reverb (Tự động thích ứng Local/Production)
const isHttps = window.location.protocol === 'https:'
const hostname = window.location.hostname || '127.0.0.1'
// Đảm bảo IPv4 trên môi trường localhost tránh lỗi phân giải ::1 của Windows
const wsHost = (hostname === 'localhost') ? '127.0.0.1' : hostname

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'pmsreverbkey',
  wsHost: wsHost,
  wsPort: 8090,
  wssPort: 443,
  forceTLS: isHttps,
  enabledTransports: ['ws', 'wss'],
})

export default echo

# Hướng dẫn khởi chạy Laravel Reverb WebSocket Server (Realtime)

Tài liệu này hướng dẫn cách cấu hình và chạy WebSocket Server (Laravel Reverb) phục vụ tính năng đồng bộ dữ liệu Realtime của hệ thống PMS ở cả môi trường **Local (HTTP)** và **Production (HTTPS/WSS)**.

---

## 1. Cấu hình cổng kết nối & Tự động nhận diện HTTP/HTTPS

Hệ thống sử dụng cổng chuyên dụng **`8090`** cho Reverb Server ở backend. Khi chạy Production HTTPS, Nginx/Apache sẽ đảm nhận SSL Termination và chuyển tiếp tín hiệu WSS sang Reverb.

* **Backend (`backend/.env`):**
  ```env
  BROADCAST_CONNECTION=reverb
  REVERB_APP_ID=803921
  REVERB_APP_KEY=pmsreverbkey
  REVERB_APP_SECRET=pmsreverbsecret
  REVERB_HOST="127.0.0.1"
  REVERB_PORT=8090
  REVERB_SCHEME=http
  ```

* **Frontend (`frontend/src/services/echo.js`):**
  *(Đã cấu hình tự động bật `forceTLS` và chuyển sang cổng WSS `443` khi truy cập qua `https://`)*
  ```javascript
  import Echo from 'laravel-echo'
  import Pusher from 'pusher-js'

  window.Pusher = Pusher

  const isHttps = window.location.protocol === 'https:'

  const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'pmsreverbkey',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname || '127.0.0.1',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT) || 8090,
    wssPort: Number(import.meta.env.VITE_REVERB_WSS_PORT) || (isHttps ? 443 : 8090),
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
  })

  export default echo
  ```

---

## 2. Hướng dẫn chạy ở môi trường Local (Phát triển)

Mở một cửa sổ CMD/Terminal mới tại thư mục `backend/` và chạy một trong hai lệnh sau:

* **Chạy thông thường:**
  ```bash
  php artisan reverb:start --host=0.0.0.0 --port=8090
  ```
* **Chạy chế độ Debug (Để xem log chi tiết các event được phát):**
  ```bash
  php artisan reverb:start --host=0.0.0.0 --port=8090 --debug
  ```

> [!TIP]
> Nếu bạn thay đổi bất kỳ cấu hình nào trong file `.env`, vui lòng nhấn `Ctrl + C` để tắt Reverb Server và chạy lại lệnh trên.

---

## 3. Hướng dẫn chạy ngầm trên Server Production (VPS)

Khi chạy trên VPS, bạn cần cấu hình để Reverb Server chạy ngầm liên tục và tự khởi động lại khi VPS bị reboot. Hãy chọn **một trong các cách** dưới đây:

### Cách 1: Sử dụng PM2 (Khuyên dùng - Đơn giản nhất)
Nếu server của bạn có sẵn Node.js/NPM, hãy dùng PM2 để quản lý process:

1. **Cài đặt PM2 toàn cục:**
   ```bash
   npm install -g pm2
   ```
2. **Khởi chạy Reverb Server chạy ngầm:**
   ```bash
   pm2 start "php artisan reverb:start --host=127.0.0.1 --port=8090" --name pms-reverb
   ```
3. **Thiết lập tự khởi động cùng hệ thống:**
   ```bash
   pm2 save
   pm2 startup
   ```
4. **Các lệnh quản lý hữu ích:**
   * Xem log realtime: `pm2 logs pms-reverb`
   * Khởi động lại: `pm2 restart pms-reverb`
   * Dừng chạy: `pm2 stop pms-reverb`

---

### Cách 2: Sử dụng Systemd Service (Cơ chế dịch vụ hệ thống của Linux VPS)
Cách này hoạt động độc lập không cần cài đặt Node.js/PM2 trên VPS.

1. **Tạo file cấu hình dịch vụ:**
   ```bash
   sudo nano /etc/systemd/system/pms-reverb.service
   ```
2. **Dán nội dung cấu hình sau (Sửa đường dẫn `WorkingDirectory` đúng với thực tế trên VPS):**
   ```ini
   [Unit]
   Description=Laravel Reverb WebSocket Server for PMS
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/pms/backend
   ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8090
   Restart=always
   RestartSec=3

   [Install]
   WantedBy=multi-user.target
   ```
3. **Kích hoạt dịch vụ:**
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable pms-reverb
   sudo systemctl start pms-reverb
   ```
4. **Các lệnh quản lý:**
   * Kiểm tra trạng thái: `sudo systemctl status pms-reverb`
   * Xem log lỗi: `journalctl -u pms-reverb.service -n 50 -f`
   * Khởi động lại: `sudo systemctl restart pms-reverb`

---

## 4. Cấu hình Reverse Proxy cho HTTPS (WSS trên Production)

Trình duyệt trên trang **HTTPS** sẽ tự động chặn mọi kết nối `ws://` (lỗi **Mixed Content**). Vì vậy, bạn cần mở SSL Reverse Proxy trên Nginx hoặc Apache để giải mã SSL và chuyển hướng sang Reverb nội bộ (`127.0.0.1:8090`).

### Cấu hình Nginx (Khuyên dùng)
Mở file Virtual Host Nginx của domain (`/etc/nginx/sites-available/your-domain.conf`):

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # Cấu hình SSL Certificate của bạn ở đây...

    # 1. Reverse Proxy WebSocket cho Reverb (/app path)
    location /app {
        proxy_pass http://127.0.0.1:8090;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # 2. Cấu hình ứng dụng Web chính (Laravel / Frontend dist)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Kiểm tra và nạp lại cấu hình Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

### Cấu hình Apache
Kích hoạt các module proxy cần thiết:
```bash
sudo a2enmod proxy proxy_http proxy_wstunnel
sudo systemctl restart apache2
```

Thêm vào cấu hình SSL VirtualHost (`/etc/apache2/sites-available/your-domain-ssl.conf`):
```apache
<VirtualHost *:443>
    ServerName your-domain.com

    # SSL Certificate Config...

    # Reverse Proxy WebSocket Reverb
    ProxyPass /app ws://127.0.0.1:8090/app
    ProxyPassReverse /app ws://127.0.0.1:8090/app
</VirtualHost>
```

Nạp lại Apache:
```bash
sudo systemctl reload apache2
```

---

## 5. Xử lý sự cố thường gặp (Troubleshooting)

* **Lỗi `WebSocket connection to 'wss://...' failed` (Mixed Content):**
  * **Nguyên nhân:** Website chạy HTTPS nhưng frontend cố gắng kết nối `ws://` trực tiếp cổng `8090` hoặc chưa cấu hình Reverse Proxy trên Nginx/Apache.
  * **Cách xử lý:** Đảm bảo đã cập nhật `echo.js` mới nhất và cài đặt Nginx/Apache Reverse Proxy ở **Mục 4**.

* **Lỗi `Gracefully terminating connections` ngay khi bật:**
  * **Nguyên nhân:** Cổng `8090` đang bị một ứng dụng khác chiếm dụng hoặc tiến trình Reverb cũ chưa tắt sạch.
  * **Cách xử lý:** Tìm và kill tiến trình cũ (`sudo kill -9 $(lsof -t -i:8090)`).

* **Đã bật Reverb nhưng giao diện không nhận được realtime:**
  * Nhấn `F12` kiểm tra tab Console và Network (lọc mục `WS` / WebSocket) xem mã lỗi kết nối.
  * Đảm bảo VPS đã chạy tiến trình Reverb ngầm (`pm2 status` hoặc `systemctl status pms-reverb`).

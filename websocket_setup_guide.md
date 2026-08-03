# Hướng dẫn khởi chạy Laravel Reverb WebSocket Server (Realtime)

Tài liệu này hướng dẫn cách cấu hình và chạy WebSocket Server (Laravel Reverb) phục vụ tính năng đồng bộ dữ liệu Realtime của hệ thống PMS ở cả môi trường **Local** và **Production (VPS)**.

---

## 1. Cấu hình cổng kết nối (Port 8090)
Hệ thống sử dụng cổng chuyên dụng **`8090`** để truyền nhận tín hiệu realtime nhằm tránh xung đột với các tiến trình chạy ngầm khác.

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
  ```javascript
  const echo = new Echo({
    broadcaster: 'reverb',
    key: 'pmsreverbkey',
    wsHost: window.location.hostname || '127.0.0.1',
    wsPort: 8090,
    wssPort: 8090,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
  })
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
> Nếu bạn thay đổi bất kỳ cấu hình nào trong file `.env`, vui lòng nhấn `Ctrl + C` để tắt Reverb Server và chạy lại lệnh trên, đồng thời restart lại cả lệnh `php artisan serve`.

---

## 3. Hướng dẫn chạy trên Server Production (VPS)

Khi chạy trên VPS, bạn cần cấu hình để Reverb Server chạy ngầm liên tục và tự khởi động lại khi VPS bị reboot. Hãy chọn **một trong các cách** dưới đây:

### Cách 1: Sử dụng PM2 (Khuyên dùng - Đơn giản nhất)
Nếu server của bạn có sẵn Node.js/NPM, hãy dùng PM2 để quản lý process:

1. **Cài đặt PM2 toàn cục:**
   ```bash
   npm install -g pm2
   ```
2. **Khởi chạy Reverb Server chạy ngầm:**
   ```bash
   pm2 start "php artisan reverb:start --host=0.0.0.0 --port=8090" --name pms-reverb
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
   ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8090
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

## 4. Xử lý sự cố thường gặp (Troubleshooting)

* **Lỗi `Gracefully terminating connections` ngay khi bật:**
  * **Nguyên nhân:** Cổng `8090` đang bị một ứng dụng khác chiếm dụng hoặc tiến trình Reverb cũ chưa được tắt sạch.
  * **Cách xử lý:** Tìm và kill tiến trình đang chiếm cổng `8090` hoặc đổi cổng sang cổng trống khác (ví dụ: `8095`) trong cả `.env`, `echo.js` và lệnh chạy.
* **Đã bật Reverb nhưng giao diện không nhận được realtime:**
  * Hãy nhấn `F12` kiểm tra tab Console và Network (mục WS) của trình duyệt xem có bị lỗi kết nối (Connection Refused) hay không.
  * Đảm bảo rằng bạn đã khởi động lại `php artisan serve` sau khi sửa file `.env`.

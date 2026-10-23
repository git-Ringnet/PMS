# Đa ngôn ngữ (Ngôn ngữ & Bản dịch)

Tài liệu cho developer / người triển khai. Người dùng cuối quản lý bản dịch tại **Cấu hình hệ thống › Ngôn ngữ**.

## Cách hoạt động

- Khóa của mỗi bản dịch là **chính chữ gốc tiếng Việt** đang hiển thị (không dùng key trong code), nên các module hiện có không phải sửa.
- Frontend: `src/utils/translator.js` theo dõi DOM (MutationObserver) và thay chữ gốc bằng bản dịch của ngôn ngữ đang chọn (nội dung chữ, `placeholder`, `title`, `aria-label`, `alert/confirm`). Khi dùng tiếng Việt mà không có chữ nào bị sửa thì lớp này không chạy.
- Hàm `t()` cũ trong `src/utils/i18n.js` vẫn dùng được; bản dịch trong DB được ưu tiên.
- Mẫu in / báo cáo: `TemplateRendererService::render()` dịch nhãn cố định **trước** khi đổ dữ liệu `{{...}}`. Ngôn ngữ in lấy theo `options['lang']` › header `X-Print-Language` › ngôn ngữ giao diện (`X-Language`).
- Dữ liệu nằm ở DB `pms_system`: `languages`, `translation_entries`, `translation_values` (`default_value` = bản dịch có sẵn, `value` = bản khách sạn tự sửa), `translation_locations` (vị trí để hiển thị cây màn hình).
- Quyền: `system.language.view | create | edit | delete`.

## Triển khai lên server

```bash
cd backend
php artisan migrate        # tạo bảng + quyền
php artisan i18n:sync      # nạp danh sách chữ và bản dịch tiếng Anh mặc định
```

Tắt toàn bộ tính năng (quay về như cũ): `I18N_ENABLED=false` trong `backend/.env`.

## Sau khi sửa / thêm giao diện

```bash
cd frontend && node scripts/extract-i18n.mjs     # quét lại chữ tiếng Việt trong src → backend/database/data/i18n/ui-strings.json
cd ../backend && php artisan i18n:sync --missing=en   # liệt kê chữ chưa có bản tiếng Anh
# bổ sung vào backend/database/data/i18n/en.json  ({"chữ gốc": "bản dịch"}) rồi:
php artisan i18n:sync
```

`i18n:sync` chạy lại bao nhiêu lần cũng được: chỉ thêm chữ/vị trí mới và cập nhật bản dịch mặc định, **không đè** bản dịch khách sạn đã tự sửa. Ngoài code, lệnh còn lấy chữ từ: Danh mục báo cáo (tên, nhóm, nhãn ô lọc), nội dung mẫu in (`templates`), và các danh mục có sẵn cột tiếng Anh (tình trạng phòng, trạng thái đặt phòng, tình trạng đăng ký, quốc tịch).

Thêm ngôn ngữ khác (vd tiếng Nhật): thêm trên giao diện, hoặc tạo `backend/database/data/i18n/ja.json` cùng định dạng với `en.json` rồi chạy `i18n:sync`.

## Quy tắc khi viết code mới

Áp dụng cho cả người và trợ lý AI (tóm tắt nằm trong `.agents/AGENTS.md`).

| Nên | Tránh | Lý do |
|---|---|---|
| Viết chữ tiếng Việt có dấu trực tiếp trong template/JS | Tự tạo hệ thống key riêng | Lớp dịch dùng chính chữ gốc làm khóa |
| `` `Còn ${n} phòng` `` hoặc `Còn {{ n }} phòng` | `'Còn ' + n + ' phòng'` | Hai cách đầu được quét thành mẫu câu `Còn {0} phòng` |
| Một câu = một chuỗi trọn vẹn | Ghép câu từ nhiều mảnh/thẻ | Mảnh nhỏ dịch rời rạc, sai ngữ pháp |
| `<option value="Phần">Phần</option>` | `<option>Phần</option>` | Tránh giá trị lưu bị đổi theo chữ hiển thị |
| Lấy dữ liệu từ state/API | Đọc `innerText`/`textContent` để xử lý logic | Chữ trên màn hình có thể đã được dịch |
| `data-no-translate` cho vùng dữ liệu người dùng nhập | | Dữ liệu trùng chữ trong từ điển sẽ bị dịch nhầm khi hiển thị |
| Dùng `t('nhom.key')` thì khai báo đủ `vi` và `en` | `t('key') \|\| 'Chữ dự phòng'` | `t()` thiếu key trả về chuỗi key nên vế `\|\|` không bao giờ chạy |
| Mẫu in: chữ cố định trọn câu, dữ liệu trong `{{...}}` | Ngắt giữa từ bằng `<br>` ("Thành<br>Tiền") | Mảnh chữ không dịch đúng nghĩa |

Checklist khi hoàn thành một màn hình / mẫu in:

1. `cd frontend && node scripts/extract-i18n.mjs`
2. `cd backend && php artisan i18n:sync --missing=en` → bổ sung bản dịch vào `database/data/i18n/en.json`
3. `php artisan i18n:sync`
4. Đổi sang English trên header, mở màn hình vừa làm kiểm tra không còn chữ tiếng Việt / không vỡ layout
5. Commit kèm `ui-strings.json` và `en.json`

## Giới hạn đã biết

- Câu ghép bằng nối chuỗi trong JS (`'Phòng ' + so`) không có mẫu tương ứng thì chỉ dịch được các phần đã biết.
- Dữ liệu do khách sạn nhập (tên công ty, outlet, món ăn...) không tự dịch; có thể nhập bản dịch cho đúng chữ đó trong trang quản lý.
- Chữ tiếng Việt không dấu chỉ được quét nếu nằm trong danh sách `UNACCENTED_LABELS` của `scripts/extract-i18n.mjs`.

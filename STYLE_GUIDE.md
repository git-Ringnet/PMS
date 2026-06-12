# 🎨 PMS Design System — Hướng Dẫn Đồng Bộ Giao Diện

> **Mục đích:** Đảm bảo tất cả các trang/module trong hệ thống PMS sử dụng **cùng một bảng màu, cùng kích thước, cùng phong cách** — nhất quán từ Sơ đồ Phòng đến Danh sách Công Việc, Phòng Trống, Kế Hoạch Phòng và các module tương lai.
>
> **Nguyên tắc:** Khi phát triển bất kỳ trang nào mới, hoặc sửa trang cũ — **ĐỌC FILE NÀY TRƯỚC** và tuân thủ nghiêm ngặt.

---

## 1. 🔵 Bảng Màu Chuẩn (Design Tokens)

### 1.1 Màu Nền Xanh Dương (Highlight Row / Occupied)

| Token                     | Mã Hex      | Dùng cho                                                    |
|---------------------------|-------------|-------------------------------------------------------------|
| `--pms-blue-row`          | `#c9eeff`   | Nền row phòng đã đặt (Occupied/InHouse) trong bảng & grid   |
| `--pms-blue-row-hover`    | `#8ecefa`   | Hover trên row/card phòng đã đặt                            |
| `--pms-blue-row-border`   | `#7ec0f3`   | Border cho card phòng đã đặt                                |
| `--pms-blue-light`        | `#e0f2fe`   | Nền rất nhạt (summary footer, stat panels, badge nhạt)      |

> **✅ Màu chuẩn nền row xanh là `#c9eeff`** — áp dụng cho tất cả các trang.
> **⚠️ KHÔNG sử dụng** `#a6dcfc` hay bất kỳ mã xanh nào khác cho nền row.

**Tailwind class chuẩn:**
```
Nền row occupied:     bg-[#c9eeff]
Hover row occupied:   hover:bg-[#8ecefa]
Border card occupied: border-[#7ec0f3]
Nền nhạt (summary):  bg-[#e0f2fe]
```

### 1.2 Màu Button Chính (Primary Action)

| Token                     | Mã Hex      | Dùng cho                                          |
|---------------------------|-------------|---------------------------------------------------|
| `--pms-btn-primary`       | `#3b82f6`   | Button chính: Tìm kiếm, Xem, Submit, Lưu          |
| `--pms-btn-primary-hover` | `#2563eb`   | Hover state của button chính                       |
| `--pms-btn-primary-text`  | `#ffffff`   | Text trên button chính                             |

> **⚠️ KHÔNG sử dụng `#60a5fa` hay `#2B7FFF`** cho button action chính.
> Tất cả button hành động chính (Tìm kiếm, Xem, Submit…) **phải dùng `bg-blue-500` (`#3b82f6`)** + `hover:bg-blue-600` (`#2563eb`).

**Tailwind class chuẩn cho button chính:**
```html
class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold 
       transition-colors shadow-sm cursor-pointer border-none"
```

### 1.3 Màu Button Phụ (Secondary Action)

| Token                        | Mã Hex      | Dùng cho                                      |
|------------------------------|-------------|-----------------------------------------------|
| `--pms-btn-secondary-bg`     | `#e0f2fe`   | Nền button phụ (Xuất Excel, View nhẹ…)         |
| `--pms-btn-secondary-hover`  | `#bae6fd`   | Hover button phụ                               |
| `--pms-btn-secondary-text`   | `#0369a1`   | Text button phụ                                |
| `--pms-btn-secondary-border` | `#7dd3fc`   | Border button phụ                              |

**Tailwind class chuẩn cho button phụ:**
```html
class="px-4 py-1.5 bg-sky-100 hover:bg-sky-200 text-sky-700 border border-sky-200 
       rounded-lg text-xs font-bold cursor-pointer transition-all shadow-xs"
```

### 1.4 Bảng Màu Trạng Thái Phòng

| Trạng thái       | Nền              | Text            | Border          |
|-------------------|------------------|-----------------|-----------------|
| InHouse/Occupied  | `#c9eeff`        | `#0369a1`       | `#7dd3fc`       |
| Guaranteed        | `#dcfce7`        | `#15803d`       | `#bbf7d0`       |
| Reservation       | `#fef3c7`        | `#b45309`       | `#fde68a`       |
| Late Checkout     | `#fef9c3`        | `#854d0e`       | `#fef08a`       |
| Allotment         | `#ffedd5`        | `#9a3412`       | `#fed7aa`       |
| None Guaranteed   | `#1e293b`        | `#ffffff`       | `slate-700`     |
| Checkout          | `#ffb74d`        | dark            | —               |
| Maintenance       | `#e57373`        | dark            | —               |
| Dirty             | `#e8a87c`        | dark            | —               |

### 1.5 Màu Nền Trang / Container

| Vị trí              | Mã Hex / Class     | Ghi chú                                |
|----------------------|--------------------|-----------------------------------------|
| Body background      | `#f0f4f8`          | Đã set trong `style.css`                |
| Main content area    | `bg-slate-100`     | Nền khu vực chính phía sau content      |
| Card / Panel         | `bg-white`         | Nền container trắng                     |
| Header bar           | `bg-white`         | Nền thanh nav trên cùng                 |
| Sub-nav bar          | `bg-slate-50`      | Nền thanh menu cấp 2                    |
| Active sub-tab badge | `bg-[#bdecfe]`     | Nền badge tab đang active + text sky    |
| Table header         | `bg-slate-100`     | Nền cột tiêu đề bảng                   |

---

## 2. 📐 Quy Tắc Kích Thước & Tỷ Lệ

### 2.1 Font Size Chuẩn

| Ngữ cảnh                  | Tailwind Class     | Pixel   | Ghi chú                             |
|----------------------------|--------------------|---------|--------------------------------------|
| Body text mặc định         | `text-xs`          | 12px    | Dùng cho hầu hết text trong bảng    |
| Header bảng                | `text-xs`          | 12px    | Font-bold hoặc font-black           |
| Sub-label nhỏ              | `text-[10px]`      | 10px    | AV, OCC, thống kê nhỏ, weekday      |
| Micro text                 | `text-[9px]`       | 9px     | Legend, % trong OCC summary          |
| Room number (grid card)    | `text-[20px]`      | 20px    | Số phòng trên card sơ đồ            |
| Room number (table)        | `text-[13px]`      | 13px    | Số phòng trong bảng danh sách        |
| Sub-nav tab text           | `text-[13px]`      | 13px    | Menu tab cấp 2                       |
| Top nav menu               | `text-[13.5px]`    | 13.5px  | Menu chính header                    |
| Button text                | `text-xs`          | 12px    | Mọi button hành động                 |

### 2.2 Chiều Cao Hàng Bảng (Table Row)

| Loại hàng          | Class   | Pixel   |
|---------------------|---------|---------|
| Header row          | `h-8`   | 32px    |
| Data row tiêu chuẩn | `h-9`   | 36px    |
| Timeline row        | `h-[38px]` | 38px |
| Compact row         | `h-8`   | 32px    |

### 2.3 Layout Header

| Thành phần       | Chiều cao | Class              |
|-------------------|-----------|--------------------|
| Top header bar    | 48px      | `h-12`             |
| Sub-nav tabs bar  | 44px      | `h-11`             |
| Content area      | fill      | `flex-1 overflow-hidden` |

### 2.4 Padding & Spacing Chuẩn

| Ngữ cảnh              | Class    | Ghi chú                         |
|------------------------|----------|----------------------------------|
| Content padding        | `p-4`   | Mọi page/panel đều dùng `p-4`   |
| Table cell padding     | `p-2`   | Cell bảng chuẩn                  |
| Compact cell padding   | `p-1`   | Cell nhỏ (timeline, matrix)      |
| Gap giữa sections      | `gap-4` | Khoảng cách giữa filter/table    |
| Gap giữa controls      | `gap-3` | Khoảng cách giữa button/input    |

---

## 3. 📏 Quy Tắc Kích Thước Trang (QUAN TRỌNG)

> **Vấn đề phát hiện:** Trang Sơ đồ Phòng hiển thị kích thước vừa mắt, nhưng các trang khác (Phòng Trống, Kế Hoạch Phòng, D.S Công Việc) hiển thị nhỏ hơn — giống như bị zoom nhỏ.

### 3.1 Nguyên nhân & Giải pháp

Mọi trang con (AvailableRoomsPage, RoomPlanPage, ShiftWorkPage) đều được nhúng bên trong `RoomMapPage.vue` qua wrapper:

```html
<div class="flex-1 p-4 bg-slate-100 overflow-hidden">
  <AvailableRoomsPage />
</div>
```

Và bên trong mỗi trang con lại có container riêng:
```html
<div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col ...">
```

**Quy tắc chung:**
1. **Wrapper ngoài** (trong RoomMapPage): Dùng `flex-1 p-4 bg-slate-100 overflow-hidden` — **KHÔNG thêm class thay đổi scale/font-size ở đây**
2. **Container trong** (trong mỗi page con): Dùng `flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full`
3. **Base font-size**: Để mặc định (`text-xs` cho bảng = 12px). **KHÔNG dùng `text-[10px]` hay `text-[11px]`** làm base cho toàn bảng.
4. **Table base class chuẩn**: `text-xs text-left border-collapse table-fixed`

### 3.2 Table Min-Width Chuẩn

| Loại bảng                          | min-width    | Ghi chú                                   |
|-------------------------------------|--------------|--------------------------------------------|
| Bảng danh sách tiêu chuẩn          | `min-w-[1250px]` | Arrivals, Departures, DS đơn giản      |
| Bảng có nhiều cột chi tiết         | `min-w-[1400px]` | Room Map table view                    |
| Bảng timeline / matrix rộng        | `min-w-[1500px]` | Noshow, Shuttle khi có nhiều cột       |
| Bảng phòng trống 30 ngày           | Tự tính theo colgroup | Cột cố định + cột ngày          |

### 3.3 Room Grid Card (Sơ đồ Phòng)

```
Grid: grid-cols-12
Card min-height: min-h-[110px]
Card padding: p-3.5
Card border-radius: rounded-xl
Room number: text-[20px] font-black
Room type: text-[12px] font-bold
Gap between cards: gap-3
```

---

## 4. 🧩 Component Pattern Chuẩn

### 4.1 Filter Bar (Thanh Lọc)

```html
<div class="flex items-center justify-between shrink-0">
  <!-- Left: inputs + buttons -->
  <div class="flex items-center gap-3">
    <!-- Date input -->
    <div class="relative flex items-center border border-slate-200 rounded-lg bg-slate-50 
                px-3 py-1.5 shadow-sm text-xs font-semibold text-slate-700">
      ...
    </div>
    <!-- Primary button -->
    <button class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg 
                   text-xs font-bold cursor-pointer border-none shadow-sm transition-all">
      Xem / Tìm kiếm
    </button>
  </div>
  <!-- Right: legend/badges -->
</div>
```

### 4.2 Data Table

```html
<table class="w-full text-left border-collapse text-xs table-fixed min-w-[1250px]">
  <thead>
    <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9">
      <th class="p-2 border-r border-slate-200 ...">Column</th>
    </tr>
  </thead>
  <tbody>
    <tr class="border-b border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer h-9">
      <td class="p-2 border-r border-slate-200 ...">Data</td>
    </tr>
  </tbody>
</table>
```

### 4.3 Toggle Switch

```html
<div class="flex items-center gap-1.5 select-none">
  <span class="text-[10px] text-slate-500 font-extrabold uppercase">Label</span>
  <label class="relative inline-flex items-center cursor-pointer">
    <input type="checkbox" v-model="value" class="sr-only peer">
    <div class="w-8 h-4.5 bg-slate-200 rounded-full peer 
                peer-checked:after:translate-x-full peer-checked:after:border-white 
                after:content-[''] after:absolute after:top-[2px] after:left-[2px] 
                after:bg-white after:border-slate-300 after:border after:rounded-full 
                after:h-3.5 after:w-3.5 after:transition-all 
                peer-checked:bg-blue-500">
    </div>
  </label>
</div>
```

### 4.4 Sub-Tab Navigation

```html
<div class="flex items-center border-b border-slate-200 px-4 bg-slate-50/50 shrink-0">
  <button
    class="py-3 px-4 text-xs font-bold transition-all relative border-b-2 whitespace-nowrap cursor-pointer"
    :class="isActive 
      ? 'border-sky-500 text-sky-600' 
      : 'border-transparent text-slate-700 hover:text-slate-900 hover:border-slate-300'"
  >
    Tab Name
  </button>
</div>
```

---

## 5. 🔧 Danh Sách File Cần Cập Nhật

### 5.1 `style.css` — Thêm CSS Variables

Thêm vào `@theme {}` block:
```css
/* PMS Unified Blue System */
--color-pms-blue-row: #c9eeff;
--color-pms-blue-row-hover: #8ecefa;
--color-pms-blue-row-border: #7ec0f3;
--color-pms-blue-light: #e0f2fe;
```

### 5.2 `ShiftWorkPage.vue` — Sửa Button & Row Color

**Button "Tìm kiếm" (dòng ~884):**
```diff
- class="px-4 py-1.5 bg-[#60a5fa] hover:bg-blue-500 text-white ..."
+ class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white ..."
```

**Row nền xanh (tất cả dòng dùng `#c9eeff` — khoảng 50 chỗ):**
```diff
- :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
+ :class="[room.roomNumber ? 'bg-[#c9eeff]' : 'bg-white']"
```

### 5.3 `AvailableRoomsPage.vue` — Đã OK

- Button "Xem" đã dùng `bg-blue-500` ✅
- Không có nền row xanh cần đổi ✅

### 5.4 `RoomPlanPage.vue` — Sửa Button View

**Button "View" (dòng ~200):**
```diff
- class="px-4 py-1.5 bg-[#c9eeff] hover:bg-[#8ecefa] text-sky-800 ..."
+ class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white ..."
```

> **Lý do:** Button "View" là button hành động chính, phải dùng màu Primary (`bg-blue-500`), không dùng màu nền nhạt (`#c9eeff`).

### 5.5 `RoomMapPage.vue` — Đã OK

- Room cards dùng `bg-[#c9eeff]` ✅
- Table rows dùng `bg-[#c9eeff]/80` ✅
- Button retry dùng `bg-blue-500` ✅

---

## 6. ✅ Checklist Khi Phát Triển Module Mới

Trước khi commit bất kỳ module/trang nào mới, kiểm tra:

- [ ] **Button chính** dùng `bg-blue-500 hover:bg-blue-600 text-white`
- [ ] **Button phụ** dùng `bg-sky-100 hover:bg-sky-200 text-sky-700 border-sky-200`
- [ ] **Nền row/card xanh** dùng `bg-[#c9eeff]` (hover: `bg-[#8ecefa]`)
- [ ] **KHÔNG dùng** `#a6dcfc`, `#60a5fa`, `#2B7FFF` cho bất kỳ element nào
- [ ] **Font base bảng** là `text-xs` (12px), không nhỏ hơn
- [ ] **Container trang** dùng pattern: `flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full`
- [ ] **Table header** dùng `bg-slate-100 border-b border-slate-200 text-slate-700 font-bold h-9`
- [ ] **Table row** dùng `border-b border-slate-200 hover:bg-slate-50 h-9`
- [ ] **Scrollbar** tự động từ `style.css` (6px, rounded, slate color)
- [ ] **Toggle switch** dùng `peer-checked:bg-blue-500` (không dùng màu khác)
- [ ] **Spacing nhất quán**: `p-4` cho container, `p-2` cho cell, `gap-3` cho controls

---

## 7. 📋 Tóm Tắt Thay Đổi Cần Làm Ngay

| #  | File                    | Vấn đề                                         | Sửa thành                           |
|----|-------------------------|-------------------------------------------------|--------------------------------------|
| 1  | `ShiftWorkPage.vue`     | Button "Tìm kiếm" dùng `bg-[#60a5fa]`          | `bg-blue-500 hover:bg-blue-600`     |
| 2  | `ShiftWorkPage.vue`     | ~50 chỗ nền row dùng `bg-[#c9eeff]`             | `bg-[#c9eeff]`                       |
| 3  | `RoomPlanPage.vue`      | Button "View" dùng `bg-[#c9eeff]` (quá nhạt)    | `bg-blue-500 hover:bg-blue-600 text-white` |
| 4  | `style.css`             | Thiếu CSS variables cho blue system              | Thêm 4 custom properties             |

---

## 8. 🎯 Quy Tắc Vàng

1. **Một màu xanh cho row highlight:** `#c9eeff` — KHÔNG CÓ NGOẠI LỆ
2. **Một màu xanh cho button action:** `#3b82f6` (blue-500) — KHÔNG CÓ NGOẠI LỆ  
3. **Kích thước nhất quán:** Mọi trang con đều render ở cùng scale, font-size, padding
4. **Đọc file này trước khi code** — mỗi khi tạo module mới hoặc sửa UI

---

*Cập nhật lần cuối: 12/06/2026*
*Áp dụng cho: PMS Frontend v4.0.x*

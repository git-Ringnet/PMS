# Tài liệu nghiệp vụ: Cơ cấu tổ chức, Phân quyền & Quản lý Nhân viên

> Tài liệu này tổng hợp lại toàn bộ nội dung nghiệp vụ từ file gốc "Phân quyền và tạo nhân viên", trình bày lại có cấu trúc để phục vụ việc đọc hiểu và triển khai (dùng làm input cho Codex / AI coding agent).

---

## 1. Tổng quan luồng nghiệp vụ

Luồng xử lý gồm 2 giai đoạn chính, phải làm **theo đúng thứ tự**:

1. **Thiết lập Cơ cấu tổ chức + Phân quyền** cho từng vị trí công việc (chức vụ).
2. **Tạo Nhân viên (User)** và gán vị trí công việc (đã được phân quyền ở bước 1) cho nhân viên đó.

```
Cơ cấu tổ chức (Bộ phận → Vị trí công việc)
        │
        ▼
Phân quyền cho Vị trí công việc (theo từng chi nhánh)
        │
        ▼
Tạo User / Nhân viên → gán Vị trí công việc → nhân viên thừa hưởng quyền
```

---

## 2. Module: Cơ cấu tổ chức

### 2.1. Khái niệm

- Danh sách **Bộ phận** (phòng ban) lấy dữ liệu từ bảng **Sp1304**.
- Mỗi Bộ phận có nhiều **Vị trí công việc / Chức vụ** con.
  - Ví dụ: Bộ phận **Lễ Tân** gồm:
    - Trưởng bộ phận — **FOM**
    - Giám sát bộ phận — **FOS**
    - Nhân viên — **FO**
- Mỗi Vị trí công việc có **phân quyền riêng biệt**.
  - Ví dụ: chức năng **Xóa Bill** chỉ được cấp quyền cho *Trưởng bộ phận*, **không** cấp cho *Nhân viên*.

### 2.2. Chức năng: Thêm vị trí công việc

- Thao tác: tại dòng tên Bộ phận → bấm dấu **(+)**.
- Nhập **Tên vị trí** cần thêm.
- Trường **Bộ phận** tự động hiển thị theo bộ phận đang thao tác (không cần chọn lại).

### 2.3. Cây cơ cấu tổ chức (theo màn hình mẫu)

Danh sách bộ phận & vị trí quan sát được trong hệ thống (ví dụ thực tế):

| Bộ phận | Vị trí công việc |
|---|---|
| Bộ Phận Kế Toán | Nhân viên thử việc, Kế toán kho - chi phí, Kế toán tổng hợp, Kế toán doanh thu |
| Bộ Phận Admin | Admin System |
| Bếp | Trưởng Bộ Phận, Nhân viên bếp |
| Bộ Phận Nhà Hàng | Nhân viên Nhà hàng, Trưởng nhà hàng |
| Bộ Phận Lễ Tân | Trưởng Bộ Phận (FOM), Nhân Viên Lễ Tân (FO) |
| Bộ Phận Quản Lý | Tổng giám đốc |
| Bộ Phận Buồng Phòng | Trưởng HK, Nhân viên buồng phòng |
| Bộ Phận Nhân Sự | Trưởng Bộ Phận |
| Bếp ăn nhân viên | (chưa liệt kê vị trí) |
| Bộ Phận Kỹ Thuật | Trưởng Bộ Phận, Nhân viên Kỹ Thuật |
| Marketing | (còn tiếp, bị cắt trong ảnh gốc) |

> Ghi chú: đây là dữ liệu mẫu quan sát từ ảnh chụp màn hình, không phải danh sách cố định/bắt buộc — hệ thống cho phép tạo bộ phận/vị trí tùy ý.

---

## 3. Module: Phân quyền cho Vị trí công việc

### 3.1. Bước 1 — Thêm ứng dụng cho vị trí công việc

Thao tác:
1. Chọn vị trí công việc cần chỉnh sửa.
2. Chọn **"Thêm ứng dụng"**.
3. Chọn ứng dụng cần thêm cho vị trí (ví dụ: **PROVISTA PMS**, **PROVISTA F&B / POS**).
4. Tích chọn **chi nhánh** được phép truy cập.
5. Chọn **Vị trí công việc** áp dụng cho từng chi nhánh (cùng 1 vị trí ở mỗi chi nhánh **có thể có role khác nhau**).
6. Chọn **Phân quyền chi tiết (Role)** — danh sách role lấy từ bảng **Sp8032**.
7. Bấm **"Cấu hình"** để vào chi tiết phân quyền theo module, theo từng chi nhánh cụ thể.

**Lưu trữ dữ liệu:**
- Chi tiết phân quyền cho từng module được lưu theo từng chi nhánh cụ thể tại bảng **Sp8032** và **Sp1604**.

**Giao diện mẫu — "Sửa ứng dụng":**

| Chi nhánh | Chọn (checkbox) | Vị trí công việc | Phân quyền |
|---|---|---|---|
| Chi nhánh HKT 1 | ✅ | FOM | [Cấu hình] |
| Chi nhánh HKT 2 | ✅ | FOM | [Cấu hình] |
| Chi nhánh HKT 3 | ☐ | (chưa chọn) | [Cấu hình] |
| Chi nhánh HKT 4 | ☐ | (chưa chọn) | [Cấu hình] |

→ Có nút **Lưu** / **Cancel**.

### 3.2. Bước 2 — Phân quyền chi tiết Màn hình & Chức năng cho Role

Thao tác:
1. Chọn vị trí công việc cần chỉnh sửa.
2. Tại ứng dụng cần điều chỉnh → chọn **"Sửa"**.
3. Chọn chức năng **"Cấu hình"** tại dòng chi nhánh cần điều chỉnh.

### 3.3. Màn hình "Phân Quyền" chi tiết

Giao diện gồm:
- **Danh sách Role** (bên trái) — ví dụ: Accountant, Administrator, Food and Beverage, FB Manager, Front Office, FOM, Fo Sup, HK, HKM, Support...
- **Bảng phân quyền Màn hình / Chức năng** (bên phải), theo từng **phân hệ** (module), ví dụ phân hệ **Reservation** gồm các màn hình con: Allotment, Bill, Booking, Company, Deposit, Guest Search, History Log, ...

Với mỗi màn hình, có 4 quyền thao tác dạng checkbox độc lập:

| Quyền | Ý nghĩa |
|---|---|
| **View** | Cho phép Role xem màn hình |
| **Add** | Cho phép Role thêm mới dữ liệu tại màn hình |
| **Edit** | Cho phép Role sửa dữ liệu tại màn hình |
| **Delete** | Cho phép Role xóa dữ liệu tại màn hình |

- Dữ liệu phân quyền chi tiết này lưu tại bảng **Sp1604**, gắn với **PMS chi nhánh cụ thể** (data theo từng chi nhánh riêng biệt).

**Ví dụ dữ liệu quan sát (Role = Administrator, phân hệ Reservation):**

| Màn hình | View | Add | Delete | Edit |
|---|---|---|---|---|
| Allotment | ✅ | ☐ | ☐ | ☐ |
| Bill | ✅ | ☐ | ☐ | ☐ |
| Booking | ✅ | ✅ | ✅ | ✅ |
| Company | ✅ | ✅ | ✅ | ✅ |
| Deposit | ✅ | ✅ | ☐ | ☐ |
| Guest Search | ✅ | ☐ | ☐ | ☐ |
| History Log | ✅ | ☐ | ☐ | ☐ |

### 3.4. Chức năng: Thêm Role mới

- Vị trí thao tác: tại màn hình Phân quyền → chọn **"Thêm"**.
- Nhập thông tin:
  - **Mã Role**
  - **Mô tả / Tên Role**
  - **Quyền thao tác ngày cũ** (checkbox **"Cho phép xóa"**): nếu bật, Role được phép thao tác trên dữ liệu ngày cũ, cụ thể gồm:
    - Xóa Bill
    - Thanh toán ngày cũ
    - Post Bill
    - Thanh toán chọn lại ngày cũ

### 3.5. Chức năng: Thêm màn hình mới

- Tại phân hệ cần tạo màn hình mới → chọn **"Thêm"**.
- Nhập thông tin:
  - **Mô tả đường dẫn**: tên màn hình theo quy định kỹ thuật (technical route/path name)
  - **Tên**: tên hiển thị của màn hình
  - **Màn hình**: phân hệ (module) đang được chọn để gắn màn hình mới vào

### 3.6. Chức năng: Copy phân quyền (đề xuất bổ sung)

- Mục đích: tạo nhanh phân quyền mới mà không phải check lại từng màn hình từ đầu.
- Thao tác: chọn phân quyền (role) cần copy → nhập tên quyền mới → bấm **"Tạo phân quyền"**.
- Đây là chức năng giúp tăng tốc độ thiết lập khi có nhiều role tương tự nhau.

---

## 4. Module: Quản lý Nhân viên (User)

> Sau khi hoàn tất cơ cấu tổ chức và phân quyền cho từng chức vụ, tiến hành **tạo nhân viên** và **gán phân quyền (vị trí công việc)** vừa tạo cho nhân viên đó.

### 4.1. Mục đích màn hình

Màn hình **Nhân viên** dùng để quản lý toàn bộ thông tin **user** của nhân viên có quyền truy cập hệ thống.

### 4.2. Danh sách nhân viên — các cột hiển thị

| Cột | Mô tả |
|---|---|
| Mã Nhân Viên | Mã định danh nhân viên (tự tăng) |
| Tên Nhân Viên | Họ tên |
| Vị Trí Công Việc | Chức vụ đã gán |
| Bộ phận | Bộ phận trực thuộc |
| Ngày sinh | Ngày sinh |
| Điện thoại | Số điện thoại |
| Email | Email đăng nhập |
| Địa chỉ | Địa chỉ |
| Xóa | Thao tác xóa nhân viên |

Màn hình danh sách có ô **Tìm kiếm** và nút **Thêm**.

### 4.3. Chức năng: Thêm User (Nhân viên mới)

Thao tác: chọn **"Thêm"** → hiển thị form **"Chỉnh Sửa Nhân Viên"** với 2 tab:
- Tab **Chỉnh Sửa Nhân Viên**
- Tab **Phân quyền đặc thù**

#### 4.3.1. Tab "Chỉnh Sửa Nhân Viên" — các trường thông tin

| Trường | Mô tả | Bắt buộc |
|---|---|---|
| Mã Nhân Viên | Mã tự tăng, hệ thống tự hiển thị | Tự động |
| Tên Nhân Viên | Họ tên nhân viên cần tạo user | Có |
| Bộ Phận | Chọn bộ phận — lấy dữ liệu từ phần Cơ cấu tổ chức | Có |
| Vị Trí Công Việc | Chỉ định vị trí (đã tạo phân quyền ở Cơ cấu tổ chức). Danh sách hiển thị **lọc theo Bộ phận đã chọn** (VD: chọn Bộ phận "Lễ Tân" → dropdown Vị trí công việc chỉ hiện các vị trí thuộc Lễ Tân, ví dụ "Trưởng Bộ Phận", "Nhân Viên Lễ Tân") | Có |
| Email | Email của user | **Bắt buộc** |
| Điện thoại | Số điện thoại | Không bắt buộc |
| Ngày sinh | Ngày sinh | Không bắt buộc |
| Ngày bắt đầu | Ngày bắt đầu làm việc | Không bắt buộc |
| Địa chỉ | Địa chỉ | Không bắt buộc |
| Người sử dụng (toggle) | Bật = user đang hoạt động. Khi nhân viên nghỉ việc → tắt toggle này để **khóa quyền truy cập** của user | — |
| Đặt Lại Mật Khẩu (nút) | Cho phép cập nhật lại mật khẩu cho user. Mật khẩu mặc định khi tạo mới: **lấy tự động theo email** của user | — |
| Chữ ký | Upload/thêm hình ảnh chữ ký, dùng cho chức năng **chữ ký điện tử** | Không bắt buộc |

**Lưu ý nghiệp vụ về Vị trí công việc:**
> Ví dụ: Nhân viên chọn Bộ phận là "Lễ Tân" → dropdown Vị trí công việc sẽ hiển thị các vị trí (role) thuộc Lễ Tân đã được thiết lập sẵn ở phần Cơ cấu tổ chức (ví dụ 2 vị trí: Trưởng Bộ Phận, Nhân Viên Lễ Tân).

#### 4.3.2. Tab "Phân quyền đặc thù"

Gồm 2 nhóm nội dung:

**A. Chi Nhánh**

| Cột | Mô tả |
|---|---|
| Chi Nhánh (checkbox) | Định nghĩa user được phép truy cập vào (các) chi nhánh nào |
| Tên Chi Nhánh | Tên chi nhánh |
| Chi Nhánh Chính (toggle) | Chọn chi nhánh **mặc định/ưu tiên hiển thị** khi user đăng nhập |

> Ví dụ: user được phân quyền vào 4 chi nhánh (HKT 1, 2, 3, 4) nhưng làm việc chủ yếu ở chi nhánh 3 → set "Chi nhánh chính" = Chi nhánh 3 để mặc định hiển thị khi đăng nhập.
>
> ⚠️ **Ghi chú kỹ thuật quan trọng:** Chức năng chỉ định chi nhánh chính này **hiện tại chưa có trên FE** của link Hotel (PMS) — cần bổ sung.

**B. Phân Quyền Kho cho User**

Danh sách các kho (checkbox, chọn nhiều), quan sát được:
- Kho bộ phận Bếp
- Kho bộ phận FO
- Kho bộ phận Kỹ Thuật
- Kho Thực phẩm
- Kho Công cụ dụng cụ
- Kho bộ phận HK
- Kho tổng văn phòng phẩm
- Kho bộ phận nhà hàng
- Kho bộ phận HR
- Kho SM
- Kho Tổng

> ⚠️ **Lưu ý phạm vi áp dụng quan trọng:**
> - Phần **"Phân quyền đặc thù"** (Chi nhánh + Phân quyền Kho) ở tab này **hiện đang chỉ dùng cho module ACC (Kế toán) và Mua hàng**, **không liên quan đến PMS**.
> - Riêng **PMS chỉ xét phân quyền chi nhánh theo cài đặt ở Cơ cấu tổ chức — Role** (tức là theo phần đã cấu hình ở Mục 3, không dùng theo phần Chi Nhánh ở tab Phân quyền đặc thù này).

---

## 5. Bảng dữ liệu liên quan (data tables tham chiếu)

| Bảng | Ý nghĩa / Dữ liệu lưu trữ |
|---|---|
| **Sp1304** | Danh sách Bộ phận (cơ cấu tổ chức) |
| **Sp8032** | Danh sách Role; lưu chi tiết phân quyền theo module cho từng chi nhánh cụ thể |
| **Sp1604** | Phân quyền chi tiết Màn hình/Chức năng (View, Add, Edit, Delete) của Role, theo từng chi nhánh cụ thể trong PMS |

---

## 6. Tóm tắt quy tắc nghiệp vụ quan trọng (Business Rules)

1. Một **Vị trí công việc** thuộc về đúng **một Bộ phận**, nhưng có thể được cấu hình phân quyền khác nhau ở mỗi **chi nhánh**.
2. Phân quyền được thiết lập ở **cấp Vị trí công việc** (chức vụ), sau đó **nhân viên thừa hưởng quyền** thông qua việc được gán vào vị trí công việc đó — không phân quyền trực tiếp cho từng cá nhân.
3. Phân quyền chi tiết chức năng theo 4 mức: **View / Add / Edit / Delete**, áp dụng cho từng màn hình, từng module, từng chi nhánh.
4. Có khái niệm **"Cho phép xóa"** riêng cho Role, kiểm soát việc thao tác trên **dữ liệu ngày cũ** (xóa bill, thanh toán ngày cũ, post bill, thanh toán chọn lại ngày cũ) — tách biệt với quyền Delete thông thường.
5. **Email** là trường bắt buộc khi tạo user, và được dùng làm **mật khẩu mặc định** ban đầu.
6. Toggle **"Người sử dụng"** dùng để khóa/mở quyền truy cập của user (thay vì xóa user khi nhân viên nghỉ việc).
7. **Phân quyền đặc thù (Chi nhánh + Kho)** ở màn hình Nhân viên chỉ áp dụng cho module **ACC** và **Mua hàng**; **PMS không dùng phần này** mà chỉ xét phân quyền chi nhánh theo Role đã cấu hình ở Cơ cấu tổ chức.
8. Chức năng **chỉ định chi nhánh chính** hiện **chưa được xây dựng ở FE** cho link Hotel — cần lưu ý khi triển khai/audit trạng thái hiện tại của hệ thống.
9. Đề xuất bổ sung chức năng **Copy phân quyền** để rút ngắn thời gian tạo Role mới dựa trên Role có sẵn.

---

## 7. Danh sách hình ảnh gốc tham chiếu (đính kèm trong tài liệu Word gốc)

| Ảnh | Nội dung |
|---|---|
| image1.png | Màn hình Cơ cấu tổ chức — cây Bộ phận/Vị trí, tab Ứng dụng (PMS, POS) đã gán cho vị trí |
| image2.png | Popup "Sửa ứng dụng" — chọn chi nhánh, vị trí công việc, cấu hình phân quyền theo chi nhánh |
| image3.png | Màn hình "Phân Quyền" — danh sách Role bên trái, bảng View/Add/Edit/Delete theo từng màn hình bên phải |
| image4.png | Popup "Chỉnh Sửa Nhân Viên" — form thông tin nhân viên + khu vực Chữ ký |
| image5.png | Danh sách Nhân viên (bảng tổng hợp toàn bộ user) |
| image6.png | Dropdown Vị trí công việc lọc theo Bộ phận đã chọn |
| image7.png | Tab "Phân quyền đặc thù" — bảng Chi nhánh + Chi nhánh chính, và Phân quyền Kho theo user |

---

## 8. Gợi ý cho việc triển khai (dành cho Codex / dev đọc hiểu)

- Cần 2 nhóm entity chính:
  - **Tổ chức**: `Department (Bộ phận)` → `Position (Vị trí công việc)` → `Application (Ứng dụng: PMS/POS/...)` → `Branch (Chi nhánh)` → `Role` → `Screen (Màn hình)` → `Permission (View/Add/Edit/Delete)`.
  - **Nhân sự**: `Employee/User` → liên kết `Position` (kế thừa quyền) + `Branch access` + `Warehouse permission (đặc thù, chỉ ACC/Mua hàng)`.
- Quan hệ nhiều-nhiều quan trọng cần model đúng:
  - 1 Position — nhiều Branch, và với mỗi Branch có thể có Role khác nhau (Position × Branch × Role).
  - 1 Role — nhiều Screen, mỗi Screen có 4 cờ quyền độc lập (View/Add/Edit/Delete).
  - 1 User — nhiều Branch (nhưng chỉ 1 Branch được đánh dấu "chính").
- Cần tách rõ 2 tầng phân quyền theo module để tránh nhầm lẫn khi code:
  - **PMS**: chỉ dùng phân quyền theo Cơ cấu tổ chức (Position × Branch × Role).
  - **ACC / Mua hàng**: dùng thêm lớp "Phân quyền đặc thù" ở màn hình User (Chi nhánh + Kho), độc lập với PMS.
- Trường mật khẩu mặc định = email → cần đảm bảo hash/generate hợp lý, không lưu plaintext.
- Cần làm rõ backlog: chức năng "chi nhánh chính" chưa có trên FE Hotel link — nên đánh dấu là task cần bổ sung, không phải bug.

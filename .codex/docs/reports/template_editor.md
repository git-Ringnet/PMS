# Trình thiết kế mẫu báo cáo

## Designer operations v2

- Đây là Designer block-based nội bộ của PMS; không sử dụng hoặc sao chép DevExpress/PDFme.
- Report Explorer liệt kê `header`, `detail`, `footer`, block cấp đầu và block lồng trong bố cục cột; phần tử ẩn vẫn có thể chọn lại từ Explorer.
- Lịch sử cục bộ giữ tối đa 60 snapshot và gom thay đổi trong 350ms; hỗ trợ Undo/Redo trên toolbar, `Ctrl/Cmd+Z`, `Ctrl/Cmd+Shift+Z` và `Ctrl/Cmd+Y`.
- Clipboard block hỗ trợ toolbar và `Ctrl/Cmd+C`, `Ctrl/Cmd+V`, `Ctrl/Cmd+D`; mọi ID block, block lồng, group, custom row/cell được cấp lại để tránh trùng ID/CSS selector.
- `locked=true` chỉ khóa đổi vị trí và xóa trong Designer; vẫn cho phép chỉnh thuộc tính. `visible=false` vẫn hiện mờ trên canvas/Explorer nhưng bị loại khỏi `content_html` và bản in.
- `visibleWhen` cho phép chọn tham số boolean của data source và `visibleWhenMode` chọn hiển thị khi đúng hoặc sai; renderer chỉ xử lý wrapper `pms-conditional-block` do Designer sinh ra.
- Toolbox có thêm `shape` với chiều cao, nền/viền theo Properties và `page-break` sinh cả `break-after: page` lẫn `page-break-after: always` để tương thích engine in hiện tại.
- Toolbox có thêm `shape` với chiều cao, nền/viền theo Properties và `page-break` sinh cả `break-after: page` lẫn `page-break-after: always` để tương thích engine in hiện tại.
- `visibleWhen` cho phép chọn tham số boolean của data source và `visibleWhenMode` chọn hiển thị khi đúng hoặc sai; renderer chỉ xử lý wrapper `pms-conditional-block` do Designer sinh ra.
- Các trường mở rộng được lưu trong `content_json`; template cũ không có `locked`/`visible` giữ nguyên hành vi.
- Không thay đổi API, database hoặc data source; renderer backend chỉ thêm nhánh điều kiện opt-in, báo cáo cũ không khai báo thuộc tính giữ nguyên.
- Không đặt mục tiêu clone DevExpress; các tính năng hiện tại chỉ phục vụ cấu hình block và giữ tương thích báo cáo legacy.

## Designer operations v2

- Đây là Designer block-based nội bộ của PMS; không sử dụng hoặc sao chép DevExpress/PDFme.
- Report Explorer liệt kê `header`, `detail`, `footer`, block cấp đầu và block lồng trong bố cục cột; phần tử ẩn vẫn có thể chọn lại từ Explorer.
- Lịch sử cục bộ giữ tối đa 60 snapshot và gom thay đổi trong 350ms; hỗ trợ Undo/Redo trên toolbar, `Ctrl/Cmd+Z`, `Ctrl/Cmd+Shift+Z` và `Ctrl/Cmd+Y`.
- Clipboard block hỗ trợ toolbar và `Ctrl/Cmd+C`, `Ctrl/Cmd+V`, `Ctrl/Cmd+D`; mọi ID block, block lồng, group, custom row/cell được cấp lại để tránh trùng ID/CSS selector.
- `locked=true` chỉ khóa đổi vị trí và xóa trong Designer; vẫn cho phép chỉnh thuộc tính. `visible=false` vẫn hiện mờ trên canvas/Explorer nhưng bị loại khỏi `content_html` và bản in.
- Các trường mở rộng được lưu trong `content_json`; template cũ không có `locked`/`visible` giữ nguyên hành vi.
- Không thay đổi API, database hoặc data source; renderer backend chỉ thêm nhánh điều kiện opt-in, báo cáo cũ không khai báo thuộc tính giữ nguyên.
- Không đặt mục tiêu clone DevExpress; các tính năng hiện tại chỉ phục vụ cấu hình block và giữ tương thích báo cáo legacy.

- Component dùng chung: `frontend/src/pages/config/components/hotel/TemplateEditorModal.vue`.
- `content_json` lưu cấu hình block; `content_html` được biên dịch từ các block khi chỉnh sửa và lưu mẫu.
- Khi chọn block, Designer xóa selection của ô trước đó; khi chọn ô static/detail, `selectedBlockId` được đồng bộ để toolbar và Properties cùng trỏ về đúng block.
- Custom cell và group header cell giữ cả style dạng top-level và style lồng legacy khi nạp `content_json`; group fallback chỉ chọn block, không hiển thị toolbar ô không có cấu hình thật.
- `style.fontSize` của từng block là nguồn hiển thị chính cho cả canvas thiết kế và báo cáo.
- Thanh điều chỉnh cỡ chữ hỗ trợ phạm vi `1px` đến `50px`, bước nhảy `1px`.
- Cỡ chữ của block được áp dụng cho các phần tử con bằng CSS có phạm vi theo ID block để CSS riêng của mẫu không ghi đè.
- Thay đổi này không sửa API, database, nguồn dữ liệu hoặc logic nghiệp vụ của báo cáo.
- Khi lưu phiên bản hoặc rollback, Designer phát sự kiện `pms:report-template-saved` kèm `templateId`.
- Report Viewer tải lại định nghĩa report, thông số trang và HTML từ template vừa lưu; report đã có dataset được render lại ngay.
- Response `execute`/`render` đồng bộ `page_size`, chiều giấy, bốn lề, tham số preview và thời điểm cập nhật vào tab đang mở.
- Canvas Designer tự chọn chế độ `Vừa trang` theo bề rộng vùng giữa khi mở mẫu, đổi khổ giấy/chiều hoặc đổi kích thước cửa sổ.
- Người dùng có thể chọn 50%, 75%, 100%, phóng to/thu nhỏ, hoặc ẩn riêng panel nguồn dữ liệu và panel thuộc tính để tăng vùng chỉnh sửa. Các thao tác này chỉ là trạng thái giao diện, không thay đổi template cho đến khi lưu phiên bản.
- Canvas Designer tự chọn chế độ `Vừa trang` theo bề rộng vùng giữa khi mở mẫu, đổi khổ giấy/chiều hoặc đổi kích thước cửa sổ.
- Người dùng có thể chọn 50%, 75%, 100%, phóng to/thu nhỏ, hoặc ẩn riêng panel nguồn dữ liệu và panel thuộc tính để tăng vùng chỉnh sửa. Các thao tác này chỉ là trạng thái giao diện, không thay đổi template cho đến khi lưu phiên bản.
# Static-table row/cell styles

## Static-table inspector on canvas

- Click an ô to load its properties into the common toolbar above the canvas; the sidebar deliberately does not list every cell.
- Ctrl/Cmd + click selects multiple cells. Scope applies a change to the active cell, selected cells, its row, its column, or the entire static table.
- Supported saved cell properties: font family/size/weight/style/underline, horizontal and vertical alignment, text/background color, line height, wrapping, four padding values, and all border sides.
- Copy/Paste style, Merge selected rectangular cells, Split merged cell, and the right-click context menu are available on the canvas.
- Merge writes `colspan`/`rowspan` into `content_json`; canvas and compiled report HTML both skip covered cells and render the same spans.

- Block `static-table` hỗ trợ định dạng riêng cho từng hàng và từng ô: độ đậm, căn lề, cỡ chữ, màu chữ và màu nền.
- Thứ tự kế thừa: style block → style hàng → style ô.
- Template cũ không có `row.style`/`cell.style` tiếp tục dùng định dạng block hiện có.
- Khi đặt `fontWeight=normal`, compiler loại thẻ `<b>`/`<strong>` ở phạm vi ô để lựa chọn chữ thường có hiệu lực.

# Detail-table column/custom-row styles

- Mỗi cột `table` có `headerStyle` và `cellStyle`: độ đậm, căn lề, cỡ chữ, màu chữ và màu nền.
- Header mặc định in đậm để giữ tương thích template cũ; `fontWeight=normal` được ưu tiên ở canvas và HTML runtime.
- Ô tiêu đề nhóm và ô hàng tùy chỉnh hỗ trợ `fontSize`/`fontWeight` ngoài màu, viền và căn lề hiện có.
- Style rỗng không ghi đè style cấp cha; style phần tử cụ thể dùng inline `!important` để ưu tiên hơn CSS font-size theo block.
- Thuộc tính chữ không áp dụng được được ẩn với block `spacer`, `divider` và `image`.
- Thanh thuộc tính chung phía trên canvas nạp đúng cấu hình của ô `static-table` được click; sidebar không liệt kê từng hàng/ô để bảng lớn vẫn thao tác được. Thanh nhanh vẫn áp dụng cho vùng chữ được chọn, header/data của `Detail Table` và ô tổng/nhóm.
- Inspector của Detail Table nạp và chỉnh đúng cấu hình ô được chọn: header dùng `columns[].header`, dòng dữ liệu dùng `columns[].value`, ô văn bản dùng `cell.content`, ô binding dùng `cell.binding`, và ô tổng hợp dùng `cell.aggregateField`. Ô đếm hiển thị biểu thức tổng hợp ở chế độ chỉ đọc.
- Danh sách `customRows` thu gọn theo từng hàng, hiển thị phạm vi và số ô; mở hàng để chỉnh phạm vi/điều kiện và chọn nhanh ô theo cột. Chọn ô trên canvas hoặc danh sách sẽ đưa cấu hình loại, nội dung/binding/trường tổng hợp, colspan, căn lề và định dạng vào Inspector; cấu trúc `customRows[].cells[]` không đổi.
- Thanh thuộc tính trên canvas đã bỏ nhóm Undo/Redo/Chép/Dán/Nhân bản; các phần tử được chọn dùng một thanh định dạng thống nhất gồm B/I/U, phông, cỡ chữ, màu, căn lề, line-height, padding và chiều cao phù hợp loại phần tử.
- Slider cỡ chữ dùng bước `0.5px`, áp dụng cho canvas và HTML báo cáo.
- Header band dùng chuẩn chung lấy từ `MINIBAR_INVOICES_BY_PRODUCT`: vùng thông tin khách sạn, divider, tiêu đề và kỳ báo cáo được đồng bộ font, căn lề, chiều cao và khoảng cách cho cả Designer preview và renderer.
- Database migration `2026_09_14_230000` đã chuẩn hóa `content_json.header` của 33 template báo cáo `*_STANDARD`/`*_REFERENCE` theo cấu trúc columns 30%/70% của mẫu minibar; migration `2026_09_14_230100` đồng bộ lại `content_html`. Nội dung tiêu đề, kỳ ngày, detail và footer từng báo cáo được giữ nguyên.
- Database migration `2026_09_14_230000` đã chuẩn hóa `content_json.header` của 33 template báo cáo `*_STANDARD`/`*_REFERENCE` theo cấu trúc columns 30%/70% của mẫu minibar; migration `2026_09_14_230100` đồng bộ lại `content_html`. Nội dung tiêu đề, kỳ ngày, detail và footer từng báo cáo được giữ nguyên.
- Slider cỡ chữ dùng bước `0.5px`, áp dụng cho canvas và HTML báo cáo.
- Header band dùng chuẩn chung lấy từ `MINIBAR_INVOICES_BY_PRODUCT`: vùng thông tin khách sạn, divider, tiêu đề và kỳ báo cáo được đồng bộ font, căn lề, chiều cao và khoảng cách cho cả Designer preview và renderer.
- Thanh thuộc tính trên canvas đã bỏ nhóm Undo/Redo/Chép/Dán/Nhân bản; các phần tử được chọn dùng một thanh định dạng thống nhất gồm B/I/U, phông, cỡ chữ, màu, căn lề, line-height, padding và chiều cao phù hợp loại phần tử.

# Text block styles

- Khi người dùng chỉnh căn lề, cỡ chữ, độ đậm hoặc màu chữ của block `text`, Designer lưu cờ `textStyleOverrides` tương ứng trong `content_json`.
- Cờ này tạo CSS có phạm vi theo ID block cho vỏ block và các phần tử nội dung; vì vậy thuộc tính áp dụng giống nhau trên canvas và HTML báo cáo, kể cả khi CSS mẫu có `h1`, `p` hoặc class riêng.
- Template cũ chưa được người dùng chỉnh không có cờ ghi đè và giữ nguyên giao diện hiện tại.

# Text block styles

- Khi người dùng chỉnh căn lề, cỡ chữ, độ đậm hoặc màu chữ của block `text`, Designer lưu cờ `textStyleOverrides` tương ứng trong `content_json`.
- Cờ này tạo CSS có phạm vi theo ID block cho vỏ block và phần tử nội dung trực tiếp; vì vậy thuộc tính áp dụng giống nhau trên canvas và HTML báo cáo, kể cả khi CSS mẫu có `h1`, `p` hoặc class riêng.
- Template cũ chưa được người dùng chỉnh không có cờ ghi đè và giữ nguyên giao diện hiện tại.

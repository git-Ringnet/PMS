// Phiếu ăn sáng (TC_LT_19, task 505): cấu hình mặc định, chọn dòng mặc định và HTML xem trước/in.

// Mặc định theo yêu cầu khách: khổ A4 in dọc, mỗi hàng 2 phiếu.
export const DEFAULT_COUPON_CONFIG = {
  columns: 2,
  minHeight: 220,
  fontSize: 'normal',
  roomFontSize: 'large',
  borderWidth: 2,
  borderStyle: 'solid',
  couponGap: 8,
  showLogo: true,
  showHotelName: true,
  customHotelName: '',
  titleLine1: 'PHIẾU ĂN SÁNG',
  titleLine2: 'BREAKFAST COUPON',
  showBookingInfo: true,
  showGuestName: false,
  showRoomNumber: true,
  showDate: true,
  roomLabel: 'Phòng / Room:',
  dateLabel: 'Ngày / Date:',
  showFooterNote: true,
  footerNote: '• Áp dụng cho khách lưu trú tại khách sạn / Applied to guest in-house only\n• Mỗi khách hàng sử dụng 01 phiếu trong 01 ngày / One coupon per guest per day\n• Chúc quý khách ngon miệng / Enjoy your meal'
}

/** Không chọn sẵn dòng nào; chỉ khi mở từ Room Map (có mã phòng) mới chọn các dòng của phòng đó. */
export function initialSelectedKeys(rows, hasRoomContext) {
  return hasRoomContext ? (rows || []).map(r => r.id) : []
}

export function resolveCouponConfig(saved) {
  return { ...DEFAULT_COUPON_CONFIG, ...(saved || {}) }
}

function fontSizeOfRoom(size) {
  return size === 'xlarge' ? '24px' : (size === 'normal' ? '17px' : '20px')
}

/**
 * HTML của tab xem trước: khổ A4 dọc, thanh công cụ (không in) có nút In. Không tự gọi hộp thoại in.
 */
export function buildCouponPreviewHtml({ coupons, cfg, hotelName = '', logoUrl = '', formatDate = d => d }) {
  const config = resolveCouponConfig(cfg)
  const hName = config.customHotelName?.trim() || hotelName || ''
  const hLogo = config.showLogo && logoUrl ? logoUrl : ''
  const footerLines = config.showFooterNote && config.footerNote
    ? config.footerNote.split('\n').filter(l => l.trim())
    : []

  const cards = (coupons || []).map(c => `
    <div class="coupon-card" style="min-height:${config.minHeight}px; border:${config.borderWidth}px ${config.borderStyle} #000; padding:10px; display:flex; flex-direction:column; justify-content:space-between; background:#fff; page-break-inside:avoid; break-inside:avoid; box-sizing:border-box;">
      <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #000; padding-bottom:6px; gap:8px;">
        <div style="min-width:70px; max-width:110px;">
          ${hLogo ? `<img src="${hLogo}" style="height:32px; max-width:100px; object-fit:contain;" />` : (config.showHotelName ? `<span style="font-weight:900; font-size:11px; text-transform:uppercase;">${hName}</span>` : '')}
        </div>
        <div style="text-align:right; flex:1;">
          ${config.titleLine1 ? `<div style="font-weight:900; text-transform:uppercase; font-size:13px; line-height:1.2;">${config.titleLine1}</div>` : ''}
          ${config.titleLine2 ? `<div style="font-weight:800; text-transform:uppercase; font-size:10px; color:#555; margin-top:2px;">${config.titleLine2}</div>` : ''}
        </div>
      </div>
      <div style="padding:10px 0; text-align:center; flex:1; display:flex; flex-direction:column; justify-content:center; gap:6px;">
        ${config.showBookingInfo ? `<div style="font-size:11px; font-weight:600; color:#333; border-bottom:1px dotted #888; padding-bottom:3px;">${c.booking_info}</div>` : ''}
        ${config.showGuestName && c.guest_name ? `<div style="font-size:11px; font-weight:700; color:#444;">Khách: ${c.guest_name}</div>` : ''}
        ${config.showRoomNumber ? `<div><span style="font-size:10px; color:#666; text-transform:uppercase; margin-right:4px;">${config.roomLabel}</span><span style="font-weight:900; font-size:${fontSizeOfRoom(config.roomFontSize)}; letter-spacing:1px;">${c.room_number}</span></div>` : ''}
        ${config.showDate ? `<div style="border-bottom:1px dotted #888; padding-bottom:4px;"><span style="font-size:10px; color:#666; text-transform:uppercase; margin-right:4px;">${config.dateLabel}</span><span style="font-weight:900; font-size:13px;">${formatDate(c.date)}</span></div>` : ''}
      </div>
      ${footerLines.length > 0 ? `
        <div style="font-size:8.5px; color:#555; font-style:italic; border-top:1px solid #ccc; padding-top:4px; line-height:1.3;">
          ${footerLines.map(l => `<div>${l}</div>`).join('')}
        </div>
      ` : ''}
    </div>
  `).join('')

  return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Xem trước phiếu ăn sáng</title>
  <style>
    @page { size: A4 portrait; margin: 8mm; }
    * { box-sizing: border-box; }
    body { font-family: Roboto, -apple-system, sans-serif; margin: 0; padding: 0; background: #e5e7eb; }
    .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: #1e3a5f; color: #fff; font-size: 13px; }
    .toolbar button { background: #0ea5e9; color: #fff; border: 0; border-radius: 6px; padding: 6px 16px; font-weight: 700; cursor: pointer; }
    .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 8mm; background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.25); }
    .coupon-grid { display: grid; grid-template-columns: repeat(${config.columns}, minmax(0, 1fr)); gap: ${config.couponGap}px; width: 100%; }
    @media print {
      body { background: #fff; }
      .toolbar { display: none; }
      .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
      .coupon-card { page-break-inside: avoid; break-inside: avoid; margin-bottom: 6px; }
    }
  </style>
</head>
<body>
  <div class="toolbar" data-testid="breakfast-preview-toolbar">
    <span>Xem trước phiếu ăn sáng - khổ A4 dọc, ${config.columns} phiếu mỗi hàng (${(coupons || []).length} phiếu)</span>
    <button type="button" onclick="window.print()">In phiếu</button>
  </div>
  <div class="sheet">
    <div class="coupon-grid">
      ${cards}
    </div>
  </div>
</body>
</html>`
}

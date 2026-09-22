<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { fetchBookings, fetchSystemDate, fetchNationalities } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const nationalityMap = ref({})
const foreignNationalityMap = ref({})

async function loadNationalities() {
  try {
    const res = await fetchNationalities()
    if (res.data?.success) {
      const list = res.data.data || []
      const natMap = {}
      const forMap = {}
      
      list.forEach(item => {
        const c2 = item.asm_code ? String(item.asm_code).trim().toUpperCase() : null
        const c3 = item.nationality_id ? String(item.nationality_id).trim().toUpperCase() : null
        const nameVi = item.asm_name || item.nationality_name || ''
        const nameEn = item.asm_description || item.nationality_name_en || ''
        const code3 = item.nationality_id || item.asm_code || '—'
        const labelFor = `${code3} - ${nameEn}`
        
        if (c2) {
          natMap[c2] = `${nameVi} ( ${nameVi} )`
          forMap[c2] = labelFor
        }
        if (c3) {
          natMap[c3] = `${nameVi} ( ${nameVi} )`
          forMap[c3] = labelFor
        }
      })
      nationalityMap.value = natMap
      foreignNationalityMap.value = forMap
    }
  } catch (err) {
    console.error('Lỗi tải danh mục quốc tịch:', err)
  }
}

function getNationalityName(code) {
  if (!code) return 'Việt Nam ( Việt Nam )'
  const cleanCode = String(code).trim().toUpperCase()
  return nationalityMap.value[cleanCode] || code
}

// State
const selectedDate = ref('24/07/2026')
const isoDate = ref('2026-07-24')
const datePickerInputRef = ref(null)

const searchTerm = ref('')
const sortKey = ref(null)
const sortDir = ref(1) // 1: asc, -1: desc

// Dropdown states
const isExportOpen = ref(false)
const isFilterOpen = ref(false)
const isColOpen = ref(false)

// Filter states
const filterSearchTerm = ref('')
const filters = ref({
  vat: false,
  noVat: false,
  children: false,
  passport: false,
  roomMove: false,
  inHouse: false
})

// Column Visibility State (true means VISIBLE - covers all 16 columns)
const colVisibility = ref({
  ma: true,
  phong: true,
  ten: true,
  gioitinh: true,
  ngaysinh: true,
  ngayden: true,
  ngaydi: true,
  quoctich: true,
  diachi: true,
  sogiayto: true,
  hochieu: true,
  ngayhethan: true,
  ngaynhapcanh: true,
  cuakhau: true,
  nlon: true,
  ghichuchuyen: true
})

const allColsChecked = computed({
  get() {
    return Object.values(colVisibility.value).every(val => val)
  },
  set(val) {
    Object.keys(colVisibility.value).forEach(k => {
      colVisibility.value[k] = val
    })
  }
})

// Active Filter Count Badge (Counts total checked filter checkboxes)
const activeFilterCount = computed(() => {
  return Object.values(filters.value).filter(Boolean).length
})

const tableClasses = computed(() => {
  return {
    'hide-ma': !colVisibility.value.ma,
    'hide-phong': !colVisibility.value.phong,
    'hide-ten': !colVisibility.value.ten,
    'hide-gioitinh': !colVisibility.value.gioitinh,
    'hide-ngaysinh': !colVisibility.value.ngaysinh,
    'hide-ngayden': !colVisibility.value.ngayden,
    'hide-ngaydi': !colVisibility.value.ngaydi,
    'hide-quoctich': !colVisibility.value.quoctich,
    'hide-diachi': !colVisibility.value.diachi,
    'hide-sogiayto': !colVisibility.value.sogiayto,
    'hide-hochieu': !colVisibility.value.hochieu,
    'hide-ngayhethan': !colVisibility.value.ngayhethan,
    'hide-ngaynhapcanh': !colVisibility.value.ngaynhapcanh,
    'hide-cuakhau': !colVisibility.value.cuakhau,
    'hide-nlon': !colVisibility.value.nlon,
    'hide-ghichuchuyen': !colVisibility.value.ghichuchuyen,
  }
})

// Table Data
const rowsData = ref([])
const selectedRows = ref([])
const checkAll = ref(false)

function toggleCheckAll() {
  if (checkAll.value) {
    selectedRows.value = filteredRows.value.map(r => r.id)
  } else {
    selectedRows.value = []
  }
}

// Helpers
function formatDateDisplay(dStr) {
  if (!dStr) return ''
  if (dStr.includes('/')) return dStr
  const dateObj = new Date(dStr)
  if (isNaN(dateObj.getTime())) return dStr
  const d = String(dateObj.getDate()).padStart(2, '0')
  const m = String(dateObj.getMonth() + 1).padStart(2, '0')
  const y = dateObj.getFullYear()
  return `${d}/${m}/${y}`
}

function parseDate(str) {
  if (!str) return null
  if (typeof str === 'string' && str.includes('/')) {
    const [d, m, y] = str.split('/')
    const year = y.split(' ')[0]
    return new Date(Number(year), Number(m) - 1, Number(d), 0, 0, 0, 0)
  }
  const dateObj = new Date(str)
  if (isNaN(dateObj.getTime())) return null
  return new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate(), 0, 0, 0, 0)
}


function handleDateInputChange(e) {
  const val = e.target.value
  if (val) {
    isoDate.value = val
    const [y, m, d] = val.split('-')
    selectedDate.value = `${d}/${m}/${y}`
    loadBookingData()
  }
}

function openDatePicker() {
  if (datePickerInputRef.value) {
    try {
      if (typeof datePickerInputRef.value.showPicker === 'function') {
        datePickerInputRef.value.showPicker()
      } else {
        datePickerInputRef.value.click()
      }
    } catch (err) {
      datePickerInputRef.value.click()
    }
  }
}

function copyDateToClipboard() {
  if (selectedDate.value) {
    navigator.clipboard.writeText(selectedDate.value)
    if (uiStore.showToast) {
      uiStore.showToast(`Đã sao chép ngày: ${selectedDate.value}`, 'success')
    } else {
      alert(`Đã sao chép: ${selectedDate.value}`)
    }
  }
}

function handleSort(key) {
  if (sortKey.value === key) {
    sortDir.value *= -1
  } else {
    sortKey.value = key
    sortDir.value = 1
  }
}

// Computed Filtered Rows
const filteredRows = computed(() => {
  let list = rowsData.value

  const targetDate = parseDate(selectedDate.value)
  if (!targetDate) return []
  targetDate.setHours(0, 0, 0, 0)
  const targetTime = targetDate.getTime()

  // 1. Lọc theo ngày xem và nghiệp vụ tình trạng phòng/chuyển phòng
  list = list.filter(r => {
    const arrDate = parseDate(r.arrivalDate || r.ngayDen)
    if (arrDate) arrDate.setHours(0, 0, 0, 0)
    const arrTime = arrDate ? arrDate.getTime() : null

    const depDate = parseDate(r.departureDate || r.ngayDi)
    if (depDate) depDate.setHours(0, 0, 0, 0)
    const depTime = depDate ? depDate.getTime() : null

    const status = Number(r.roomStatus)

    // A. Phòng cũ tình trạng 100 (STATUS_MOVED)
    if (status === 100) {
      if (r.isSameDayMoved) {
        // Vừa check in xong chuyển cùng ngày: không hiển thị phòng cũ (tình trạng 100)
        return false
      }
      if (r.isStayedThenMoved && arrTime === targetTime) {
        // Ngày check-in ban đầu: hiển thị phòng ban đầu
        return true
      }
      return false
    }

    // B. Phòng in-house (status = 1)
    if (status === 1) {
      // B.1. Phòng chuyển tới sau khi đã ở (isStayedThenMoved)
      if (r.isStayedThenMoved) {
        if (arrTime === targetTime) {
          // Ngày xem = ngày chuyển phòng (cũng là ngày in của phòng mới)
          // Chỉ hiển thị khi có tick chọn "Phòng Chuyển" (roomMove)
          return !!filters.value.roomMove
        }
        if (arrTime && depTime && arrTime < targetTime && targetTime < depTime) {
          // Khách đang ở trong những ngày kế tiếp
          return !!filters.value.inHouse
        }
        return false
      }

      // B.2. Phòng bình thường hoặc chuyển cùng ngày nhận phòng
      if (arrTime === targetTime) {
        // Ngày đến = ngày cần xem
        return true
      }
      if (arrTime && depTime && arrTime < targetTime && targetTime < depTime) {
        // Khách đang ở: ArrivalDate < ngày xem < CheckoutDate
        return !!filters.value.inHouse
      }
      return false
    }

    return false
  })

  // 2. Lọc theo VAT / No VAT
  if (filters.value.vat && !filters.value.noVat) {
    list = list.filter(r => r.hasVat)
  } else if (filters.value.noVat && !filters.value.vat) {
    list = list.filter(r => !r.hasVat)
  }

  // 3. Lọc Trẻ em
  if (filters.value.children) {
    list = list.filter(r => !r.nLon)
  }

  // 4. Lọc Passport
  if (filters.value.passport) {
    list = list.filter(r => !!r.hoChieu && String(r.hoChieu).trim() !== '')
  }

  // 5. Tìm kiếm nội bộ
  if (searchTerm.value.trim()) {
    const s = searchTerm.value.trim().toLowerCase()
    list = list.filter(r => `${r.ma} ${r.phong} ${r.ten} ${r.quocTich} ${r.soGiayTo} ${r.transferNote}`.toLowerCase().includes(s))
  }

  // 6. Sắp xếp (Sorting)
  if (sortKey.value) {
    list = [...list].sort((a, b) => {
      let va = a[sortKey.value]
      let vb = b[sortKey.value]
      if (sortKey.value === 'ngayDen' || sortKey.value === 'ngayDi') {
        va = parseDate(va)?.getTime() || 0
        vb = parseDate(vb)?.getTime() || 0
      } else {
        va = (va || '').toString().toLowerCase()
        vb = (vb || '').toString().toLowerCase()
      }
      return va < vb ? -sortDir.value : va > vb ? sortDir.value : 0
    })
  }

  return list
})

// Statistics
const totalUniqueRooms = computed(() => {
  const set = new Set(filteredRows.value.map(r => `${r.ma}_${r.phong}`))
  return set.size
})

const totalGuests = computed(() => filteredRows.value.length)

// Click outside handling for dropdowns
function closeDropdowns(e) {
  if (!e.target.closest('.dropdown-anchor')) {
    isExportOpen.value = false
    isFilterOpen.value = false
    isColOpen.value = false
  }
}

function toggleDropdown(name) {
  if (name === 'export') {
    if (selectedRows.value.length === 0) return
    isExportOpen.value = !isExportOpen.value
    isFilterOpen.value = false
    isColOpen.value = false
  } else if (name === 'filter') {
    isFilterOpen.value = !isFilterOpen.value
    isExportOpen.value = false
    isColOpen.value = false
  } else if (name === 'col') {
    isColOpen.value = !isColOpen.value
    isExportOpen.value = false
    isFilterOpen.value = false
  }
}

function exportToExcel(rows) {
  const headers = [
    'STT', 'HỌ TÊN', 'NGÀY SINH', 'GIỚI TÍNH', 'LOẠI KHÁCH', 'SỐ GIẤY TỜ', 'LOẠI GIẤY TỜ', 'QUỐC TỊCH',
    'ĐỊA CHỈ', 'PHƯỜNG/XÃ', 'QUẬN/HUYỆN', 'TP/TỈNH', 'KHÁCH SẠN', 'MÃ CHECKIN', 'SỐ PHÒNG', 'ĐƠN GIÁ',
    'NGÀY ĐẾN', 'NGÀY ĐI', 'NGÀY NHẬP CẢNH', 'MỤC ĐÍCH NHẬP CẢNH', 'CỬA KHẨU NHẬP CẢNH', 'TẠM TRÚ ĐẾN NGÀY',
    'NGHỀ NGHIỆP', 'GHI CHÚ', 'SỐ ĐIỆN THOẠI', 'NƠI LÀM VIỆC', 'LÝ DO LƯU TRÚ', 'THƯỜNG TRÚ', 'DÂN TỘC',
    'GHI CHÚ CHUYỂN PHÒNG'
  ]

  let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">`
  html += `<head><!--[if gte mso 9]><xml><x:Workbook><x:Worksheets><x:Worksheet><x:Name>Khai bao</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:Worksheet></x:Worksheets></x:Workbook></xml><![endif]--><meta charset="utf-8"/>`
  html += `<style>
    body, table, tr, th, td { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
    table { border-collapse: collapse; }
    th { font-weight: bold; background-color: #8ea9db; border: 0.5pt solid #000000; text-align: left; padding: 4px 6px; }
    td { border: 0.5pt solid #000000; padding: 4px 6px; }
  </style></head>`
  html += `<body><table style="border-collapse: collapse;"><thead><tr>`
  headers.forEach(h => {
    html += `<th style="background-color: #8ea9db; font-weight: bold; border: 0.5pt solid #000000;">${h}</th>`
  })
  html += `</tr></thead><tbody>`

  rows.forEach((r, idx) => {
    const isVnText = r.isVn ? 'Việt Nam' : 'Quốc tế'
    const genderDisplay = r.gioiTinh ? String(r.gioiTinh).replace(/^[A-Za-z0-9\s]*-\s*/, '') : ''
    const checkinId = r.ma ? String(r.ma).replace(/^[A-Za-z]+/, '') : String(r.bookingId || '')
    html += `<tr>`
    html += `<td style="text-align: center; border: 0.5pt solid #000000;">${idx + 1}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ten || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngaySinh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${genderDisplay}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${isVnText}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.soGiayTo || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.loaiGiayTo || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.quocTich || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.diaChi || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.phuongXa || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.quanHuyen || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.tpTinh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.hotelName || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${checkinId}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.phong || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.donGia || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngayDen || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngayDi || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngayNhapCanh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.mucDichNhapCanh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.cuaKhau || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.tamTruDen || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ngheNghiep || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ghiChu || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.soDienThoai || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.noiLamViec || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.lyDoLuuTru || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.thuongTru || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.danToc || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.transferNote || ''}</td>`
    html += `</tr>`
  })

  html += `</tbody></table></body></html>`

  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Khai_Bao_Luu_Tru_${new Date().toISOString().slice(0,10)}.xls`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function formatIsoDate(dateStr) {
  if (!dateStr) return ''
  const clean = String(dateStr).trim().split('T')[0].split(' ')[0]
  if (clean.includes('-')) {
    const parts = clean.split('-')
    if (parts[0].length === 4) return clean
    return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`
  }
  if (clean.includes('/')) {
    const parts = clean.split('/')
    if (parts[2].length === 4) {
      return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`
    }
  }
  return clean
}

function formatIsoDateTime(dateStr) {
  const iso = formatIsoDate(dateStr)
  return iso ? `${iso}T00:00:00` : ''
}

function exportToCsv(rows) {
  const headers = [
    'STT', 'Mã Đăng Ký', 'Phòng', 'Tên Khách', 'Giới Tính', 'Ngày Sinh',
    'Ngày Đến', 'Ngày Đi', 'Quốc Tịch', 'Địa chỉ', 'Số Giấy Tờ', 'Hộ chiếu',
    'Ngày hết hạn', 'Ngày Nhập Cảnh', 'Cửa Khẩu', 'N.Lớn', 'Ghi chú chuyển phòng'
  ]

  let csvContent = '\uFEFF' // BOM for Excel UTF-8 support
  csvContent += headers.map(h => `"${h}"`).join(',') + '\n'

  rows.forEach((r, idx) => {
    const genderVal = r.gioiTinh ? (r.gioiTinh.includes('Nữ') || r.gioiTinh.includes('F') ? 'F' : 'M') : ''
    const dobVal = formatIsoDate(r.rawDob || r.ngaySinh)
    const entryDateVal = formatIsoDateTime(r.rawEntryDate || r.ngayNhapCanh || r.ngayDen)
    const quocTichVal = (r.quocTich && !r.isVn && r.quocTich !== 'Vietnam ( Việt Nam )') ? r.quocTich : ''

    const rowValues = [
      idx + 1,
      r.ma || '',
      r.phong || '',
      r.ten || '',
      genderVal,
      dobVal,
      r.ngayDen || '',
      r.ngayDi || '',
      quocTichVal,
      r.diaChi !== '_' ? r.diaChi : '',
      r.soGiayTo || '',
      r.hoChieu || '',
      r.ngayHetHan || '',
      entryDateVal,
      r.cuaKhau || '',
      r.nLon ? '1' : '0',
      r.transferNote || ''
    ]
    csvContent += rowValues.map(val => `"${String(val).replace(/"/g, '""')}"`).join(',') + '\n'
  })

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Khai_Bao_Luu_Tru_${new Date().toISOString().slice(0,10)}.csv`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function exportToXml(rows) {
  const buildTag = (tag, val) => {
    if (val === null || val === undefined || String(val).trim() === '') {
      return `<${tag}/>`
    }
    return `<${tag}>${val}</${tag}>`
  }

  let xml = '<KHAI_BAO_TAM_TRU>\n'
  rows.forEach((r, idx) => {
    let g = 'M'
    if (r.gioiTinh) {
      const gStr = String(r.gioiTinh).toLowerCase()
      if (gStr.includes('f') || gStr.includes('nữ')) {
        g = 'F'
      }
    }

    const fullLabel = getForeignNationalityCodeLabel(r.quocTich)
    const isoCode = fullLabel.split(' - ')[0] || 'VNM'

    xml += '<THONG_TIN_KHACH>\n'
    xml += `<so_thu_tu>${idx + 1}</so_thu_tu>\n`
    xml += `${buildTag('ho_ten', r.ten)}\n`
    xml += `${buildTag('ngay_sinh', r.ngaySinh)}\n`
    xml += `<ngay_sinh_dung_den>D</ngay_sinh_dung_den>\n`
    xml += `<gioi_tinh>${g}</gioi_tinh>\n`
    xml += `<ma_quoc_tich>${isoCode}</ma_quoc_tich>\n`
    xml += `${buildTag('so_ho_chieu', r.soGiayTo)}\n`
    xml += `${buildTag('so_phong', r.phong)}\n`
    xml += `${buildTag('ngay_den', r.ngayDen)}\n`
    xml += `${buildTag('ngay_di_du_kien', r.ngayDi)}\n`
    xml += `${buildTag('ngay_tra_phong', r.ngayTraPhong || r.ngayDi)}\n`
    xml += '</THONG_TIN_KHACH>\n'
  })
  xml += '</KHAI_BAO_TAM_TRU>'

  const blob = new Blob([xml], { type: 'application/xml;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Khai_Bao_Tam_Tru_${new Date().toISOString().slice(0,10)}.xml`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function getForeignNationalityCodeLabel(input) {
  if (!input) return 'VNM - Viet Nam'
  const str = String(input).trim()
  if (foreignNationalityMap.value[str.toUpperCase()]) {
    return foreignNationalityMap.value[str.toUpperCase()]
  }
  if (str.toLowerCase().includes('vietnam') || str.toLowerCase().includes('việt nam')) {
    return 'VNM - Viet Nam'
  }
  if (str.toLowerCase().includes('united states') || str.toLowerCase().includes('mỹ')) {
    return 'USA - United States'
  }
  if (/^[A-Z]{3}\s*-\s*/.test(str)) {
    return str
  }
  return str
}

function exportToExcelForeign(rows) {
  const headers = [
    'STT', 'HỌ TÊN', 'NGÀY SINH', 'NGÀY SINH ĐÚNG ĐẾN', 'GIỚI TÍNH',
    'MÃ QUỐC TỊCH', 'SỐ HỘ CHIẾU', 'SỐ PHÒNG', 'NGÀY ĐẾN', 'NGÀY ĐI DỰ KIẾN', 'NGÀY TRẢ PHÒNG'
  ]

  let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">`
  html += `<head><!--[if gte mso 9]><xml><x:Workbook><x:Worksheets><x:Worksheet><x:Name>DS_KBTT_NNN</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:Worksheet></x:Worksheets></x:Workbook></xml><![endif]--><meta charset="utf-8"/>`
  html += `<style>
    body, table, tr, th, td { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
    table { border-collapse: collapse; }
    th { font-weight: bold; background-color: #9bc2e6; border: 0.5pt solid #000000; text-align: left; padding: 4px 6px; }
    td { border: 0.5pt solid #000000; padding: 4px 6px; }
  </style></head>`
  html += `<body><table style="border-collapse: collapse;">`
  html += `<thead>`
  html += `<tr><td colspan="11" style="text-align: center; font-size: 13pt; font-weight: bold; background-color: #ffffff; border: none; height: 30px; vertical-align: middle;">DANH SÁCH HỒ SƠ KBTT</td></tr>`
  html += `<tr>`
  headers.forEach(h => {
    html += `<th style="background-color: #9bc2e6; font-weight: bold; border: 0.5pt solid #000000;">${h}</th>`
  })
  html += `</tr></thead><tbody>`

  rows.forEach((r, idx) => {
    let genderText = r.gioiTinh || 'M - Nam'
    if (!genderText.includes('-')) {
      genderText = (genderText === 'Nữ' || genderText === 'F') ? 'F - Nữ' : 'M - Nam'
    }

    const natLabel = getForeignNationalityCodeLabel(r.quocTich)
    const passportNo = r.hoChieu || r.soGiayTo || ''

    html += `<tr>`
    html += `<td style="text-align: center; border: 0.5pt solid #000000;">${idx + 1}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ten || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ngaySinh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">D - Ngày</td>`
    html += `<td style="border: 0.5pt solid #000000;">${genderText}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${natLabel}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${passportNo}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.phong || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ngayDen || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ngayDi || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ngayDi || ''}</td>`
    html += `</tr>`
  })

  html += `</tbody></table></body></html>`

  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Danh_Sach_Hoso_KBTT_NNN_${new Date().toISOString().slice(0,10)}.xls`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function exportToExcelVn(rows) {
  const headers = [
    'STT',
    'HỌ TÊN (*)',
    'NGÀY SINH (*)',
    'GIỚI TÍNH (*)',
    'QUỐC TỊCH (*)',
    'LOẠI GIẤY TỜ (*)',
    'TÊN GIẤY TỜ',
    'SỐ GIẤY TỜ (*)',
    'SỐ ĐIỆN THOẠI',
    'NƠI CƯ TRÚ HIỆN NAY',
    'TỈNH/ THÀNH PHỐ',
    'PHƯỜNG/ XÃ/ ĐẶC KHU',
    'ĐỊA CHỈ CHI TIẾT',
    'NGÀY ĐẾN (*)',
    'NGÀY ĐI DỰ KIẾN (*)',
    'SỐ PHÒNG/ KHOA (*)',
    'LÝ DO CƯ TRÚ (*)',
    'NHẬP LÝ DO',
    'GHI CHÚ'
  ]

  let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">`
  html += `<head><!--[if gte mso 9]><xml><x:Workbook><x:Worksheets><x:Worksheet><x:Name>DS_LuuTru_VN</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:Worksheet></x:Worksheets></x:Workbook></xml><![endif]--><meta charset="utf-8"/>`
  html += `<style>
    body, table, tr, th, td { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
    table { border-collapse: collapse; }
    th { font-weight: bold; background-color: #8ea9db; border: 0.5pt solid #000000; text-align: left; padding: 4px 6px; }
    td { border: 0.5pt solid #000000; padding: 4px 6px; }
  </style></head>`
  html += `<body><table style="border-collapse: collapse;">`
  html += `<thead>`
  
  // Row 1: Single Merged Header Row for Title & Subtitle (Centered in 1 single Excel row)
  html += `<tr><td colspan="19" align="center" style="text-align: center; border: none; padding: 6px 0; vertical-align: middle;"><span style="font-size: 13pt; font-weight: bold; font-family: 'Times New Roman', serif;">DANH SÁCH THÔNG BÁO LƯU TRÚ CHO CÔNG DÂN VIỆT NAM</span><br/><span style="font-size: 10pt; font-style: italic; color: #ff0000; font-family: 'Times New Roman', serif;">(*Vui lòng không xóa dữ liệu mẫu; hãy thêm thông tin từ dòng tiếp theo)</span></td></tr>`
  
  // Row 2: Main Headers (Blue background)
  html += `<tr>`
  headers.forEach(h => {
    html += `<th style="background-color: #9bc2e6; font-weight: bold; border: 0.5pt solid #000000; text-align: left;">${h}</th>`
  })
  html += `</tr>`

  // Row 4: Instruction Row
  const instructions = [
    'Mẫu dữ liệu',
    '&lt;Họ và tên gồm chữ cái và ký tự&gt;',
    'Giá trị &lt;dd/mm/yyyy&gt;',
    '&lt;Giá trị: F - Nữ, M - Nam&gt;',
    '&lt;Chọn Quốc tịch theo Sheet [DANH_MUC]&gt;',
    '&lt;Chọn Loại Giấy Tờ theo Sheet [DANH_MUC]&gt;',
    '&lt;Bắt buộc nhập với Giấy tờ khác&gt;',
    '&lt;Bắt buộc nhập: Số giấy tờ&gt;',
    '&lt;Không bắt buộc&gt;',
    '&lt;Chọn Nơi cư trú Sheet [DANH_MUC]&gt;',
    '&lt;Chọn Tỉnh/Thành phố Sheet [TINH_THANH]&gt;',
    '&lt;Chọn Phường/Xã/Đặc khu Sheet [PHUONG_XA]&gt;',
    '&lt;Nhập thông tin địa chỉ chi tiết&gt;',
    '&lt;dd/mm/yyyy&gt;',
    '&lt;dd/mm/yyyy&gt;',
    '&lt;Nhập tên Phòng/Khoa&gt;',
    '&lt;Chọn Lý do cư trú theo Sheet [DANH_MUC]&gt;',
    '&lt;Bắt buộc nhập với Lý do cư trú là Mục đích khác&gt;',
    ''
  ]
  html += `<tr>`
  instructions.forEach(inst => {
    html += `<td style="font-size: 9pt; font-style: italic; color: #333333; background-color: #ffffff; border: 0.5pt solid #000000;">${inst}</td>`
  })
  html += `</tr>`

  // Row 5: Example Row
  const exampleRow = [
    '[EXAMPLE]',
    '[TEST] NGUYỄN QUANG MINH',
    '10/02/1999',
    'M - Nam',
    'VNM - Viet Nam',
    '1 - Thẻ CCCD',
    '',
    '',
    '03669541874',
    '',
    '',
    '',
    'Ngõ 3/6A',
    '20/03/2026',
    '30/04/2026',
    '205A0',
    '3 - Học tập',
    '',
    ''
  ]
  html += `<tr>`
  exampleRow.forEach(ex => {
    html += `<td style="background-color: #ffff00; font-weight: normal; border: 0.5pt solid #000000; mso-number-format:'\\@';">${ex}</td>`
  })
  html += `</tr></thead><tbody>`

  // Row 6+: Data Rows
  rows.forEach((r, idx) => {
    let genderText = r.gioiTinh || 'M - Nam'
    if (!genderText.includes('-')) {
      genderText = (genderText === 'Nữ' || genderText === 'F') ? 'F - Nữ' : 'M - Nam'
    }

    const docTypeVal = r.loaiGiayTo === 'Hộ chiếu' ? '2 - Hộ chiếu' : '1 - Thẻ CCCD'
    const addressDetail = r.diaChi !== '_' ? r.diaChi : ''

    html += `<tr>`
    html += `<td style="text-align: center; border: 0.5pt solid #000000;">${idx + 1}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ten || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngaySinh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${genderText}</td>`
    html += `<td style="border: 0.5pt solid #000000;">VNM - Viet Nam</td>`
    html += `<td style="border: 0.5pt solid #000000;">${docTypeVal}</td>`
    html += `<td style="border: 0.5pt solid #000000;"></td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.soGiayTo || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.soDienThoai || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;"></td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.tpTinh || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.phuongXa || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">${addressDetail}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngayDen || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.ngayDi || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000; mso-number-format:'\\@';">${r.phong || ''}</td>`
    html += `<td style="border: 0.5pt solid #000000;">1 - Du lịch</td>`
    html += `<td style="border: 0.5pt solid #000000;"></td>`
    html += `<td style="border: 0.5pt solid #000000;">${r.ghiChu || ''}</td>`
    html += `</tr>`
  })

  html += `</tbody></table></body></html>`

  const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Danh_Sach_Thong_Bao_Luu_Tru_VN_${new Date().toISOString().slice(0,10)}.xls`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function handleExport(type) {
  if (selectedRows.value.length === 0) return
  
  // Find selected rows within rowsData
  const selectedItems = rowsData.value.filter(r => selectedRows.value.includes(r.id))
  if (selectedItems.length === 0) return

  const tStr = type.toLowerCase()

  if (tStr.includes('nước ngoài') || tStr.includes('nnn')) {
    exportToExcelForeign(selectedItems)
    uiStore.showToast(`Đã xuất thành công ${selectedItems.length} khách hàng sang file Excel (Mẫu khách nước ngoài).`, 'success')
  } else if (tStr.includes('việt nam') || tStr.includes(' - vn')) {
    exportToExcelVn(selectedItems)
    uiStore.showToast(`Đã xuất thành công ${selectedItems.length} khách hàng sang file Excel (Mẫu khách Việt Nam).`, 'success')
  } else if (tStr.includes('excel') || type === 'Excel') {
    exportToExcel(selectedItems)
    uiStore.showToast(`Đã xuất thành công ${selectedItems.length} khách hàng sang file Excel.`, 'success')
  } else if (type === 'XML') {
    exportToXml(selectedItems)
    uiStore.showToast(`Đã xuất thành công ${selectedItems.length} khách hàng sang XML.`, 'success')
  } else if (type === 'CSV') {
    exportToCsv(selectedItems)
    uiStore.showToast(`Đã xuất thành công ${selectedItems.length} khách hàng sang CSV.`, 'success')
  }
  isExportOpen.value = false
}

function resetFilters() {
  filters.value = {
    vat: false,
    noVat: false,
    children: false,
    passport: false,
    roomMove: false,
    inHouse: false
  }
}

function applyFilters() {
  isFilterOpen.value = false
}

async function initSystemDate() {
  try {
    const res = await fetchSystemDate()
    const rawDate = res?.data?.data?.system_date || res?.data?.system_date || res?.data
    if (rawDate && typeof rawDate === 'string') {
      const cleanDate = rawDate.replace('T', ' ').split(' ')[0]
      if (cleanDate.includes('-')) {
        const [y, m, d] = cleanDate.split('-')
        isoDate.value = `${y}-${m}-${d}`
        selectedDate.value = `${d}/${m}/${y}`
        return
      }
    }
  } catch (err) {
    console.warn('Could not fetch system date, fallback to current date:', err)
  }
  const now = new Date()
  const d = String(now.getDate()).padStart(2, '0')
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const y = now.getFullYear()
  selectedDate.value = `${d}/${m}/${y}`
  isoDate.value = `${y}-${m}-${d}`
}

async function loadBookingData() {
  try {
    const res = await fetchBookings()
    const rawList = res?.data?.data || res?.data || []
    if (Array.isArray(rawList)) {
      // Include non-cancelled bookings
      const activeBookings = rawList.filter(b => {
        if (b.deleted_at || b.is_cancelled || b.is_canceled) return false
        
        if (b.registrationStatus && Number(b.registrationStatus.bk_definite) === 4) return false
        if (b.registration_status && Number(b.registration_status.bk_definite) === 4) return false

        const statusVal = b.status !== undefined && b.status !== null ? String(b.status).toLowerCase() : ''
        if (statusVal === 'cancelled' || statusVal === 'canceled' || statusVal === '3') return false

        return true
      })

      const mapped = []
      let seq = 1
      activeBookings.forEach(b => {
        const ma = b.booking_code || b.code || b.id || ''
        const hasVat = !!(b.has_vat || b.vat || b.is_vat)
        const bookingRooms = b.booking_rooms || b.bookingRooms || []
        
        if (bookingRooms.length > 0) {
          bookingRooms.forEach(br => {
            const brStatus = br.status !== undefined && br.status !== null ? Number(br.status) : 0
            // Skip cancelled room assignments (status 3)
            if (brStatus === 3) return

            const phong = br.room_number || br.room?.room_number || b.original_room_name || b.room_name || ''
            if (!phong) return

            const ngayDen = br.arrival_date || b.arrival_date || ''
            const ngayDi = br.departure_date || b.departure_date || ''

            // Tìm phòng cũ nếu đây là phòng mới được chuyển tới
            const oldBr = bookingRooms.find(r => String(r.move_room) === String(br.id)) || br.moved_from_room || br.movedFromRoom
            const isMovedRoom = !!oldBr
            const oldArrStr = oldBr ? (oldBr.arrival_date || '') : ''
            const newArrStr = br.arrival_date || ''
            const oldArr = parseDate(oldArrStr)
            const newArr = parseDate(newArrStr)
            const isStayedThenMoved = !!(isMovedRoom && oldArr && newArr && oldArr.getTime() < newArr.getTime())
            const isSameDayMoved = !!(isMovedRoom && oldArr && newArr && oldArr.getTime() === newArr.getTime())
            const oldRoomNum = oldBr ? (oldBr.room_number || oldBr.room?.room_number || '') : ''

            let transferNote = ''
            if (isMovedRoom && oldRoomNum) {
              transferNote = `From Room ${oldRoomNum}, old arrival date: ${formatDateDisplay(oldArrStr)}`
            }

            // 1. Tải danh sách Người lớn trong phòng
            const brGuests = br.guests || []
            if (brGuests.length > 0) {
              brGuests.forEach(brg => {
                if (brg.status === 3 || brg.status === '3') return // skip cancelled guest in room
                const c = brg.guest
                if (c) {
                  const isVn = (c.nationality_code || 'VNM').toUpperCase() === 'VNM' || 
                               (c.nationality_code || 'VN').toUpperCase() === 'VN' || 
                               (c.nationality_code || '').toLowerCase().includes('vietnam') || 
                               (c.nationality_code || '').toLowerCase().includes('việt nam')

                  let genderText = 'M - Nam'
                  if (c.gender === 2 || String(c.gender) === '2' || String(c.gender).toLowerCase() === 'female' || (c.title && ['Girl.', 'Girl', 'Ms.', 'Ms', 'Mrs.', 'Mrs'].includes(c.title))) {
                    genderText = 'F - Nữ'
                  }

                  mapped.push({
                    id: seq++,
                    bookingId: b.id,
                    ma: String(ma),
                    phong: String(phong),
                    ten: c.full_name || c.name || 'Guest',
                    gioiTinh: genderText,
                    ngaySinh: c.dob ? formatDateDisplay(c.dob) : '',
                    ngayDen: formatDateDisplay(ngayDen),
                    ngayDi: formatDateDisplay(ngayDi),
                    quocTich: getNationalityName(c.nationality_code || 'VNM'),
                    diaChi: c.address || '_',
                    soGiayTo: c.id_number || c.passport_number || '',
                    loaiGiayTo: c.passport_number ? 'Hộ chiếu' : 'Căn cước công dân',
                    hoChieu: c.passport_number || '',
                    ngayHetHan: c.passport_expiry ? formatDateDisplay(c.passport_expiry) : '',
                    ngayNhapCanh: c.entry_date ? formatDateDisplay(c.entry_date) : formatDateDisplay(ngayDen),
                    cuaKhau: c.border_gate || '',
                    nLon: true,
                    isVn: isVn,
                    phuongXa: c.ward || '',
                    quanHuyen: c.district || '',
                    tpTinh: c.province || '',
                    donGia: br.rate || 0,
                    mucDichNhapCanh: c.entry_purpose || 'Du lịch',
                    tamTruDen: c.temp_residence_to ? formatDateDisplay(c.temp_residence_to) : formatDateDisplay(ngayDi),
                    ngheNghiep: c.occupation || '',
                    ghiChu: c.note || '',
                    transferNote: transferNote,
                    soDienThoai: c.phone || '',
                    noiLamViec: c.workplace || '',
                    lyDoLuuTru: c.entry_purpose || 'Du lịch',
                    thuongTru: c.address || '',
                    danToc: '',
                    rawDob: c.dob || '',
                    rawEntryDate: c.entry_date || ngayDen || '',
                    // Metadata phục vụ lọc ngày & chuyển phòng
                    roomStatus: brStatus,
                    arrivalDate: br.arrival_date,
                    departureDate: br.departure_date,
                    isStayedThenMoved: isStayedThenMoved,
                    isSameDayMoved: isSameDayMoved,
                    hasVat: hasVat
                  })
                }
              })
            } else {
              // fallback to booking guest info nếu phòng chưa gán khách cụ thể
              mapped.push({
                id: seq++,
                bookingId: b.id,
                ma: String(ma),
                phong: String(phong),
                ten: b.contact_name || b.guest_name || 'Guest',
                gioiTinh: 'M - Nam',
                ngaySinh: '',
                ngayDen: formatDateDisplay(ngayDen),
                ngayDi: formatDateDisplay(ngayDi),
                quocTich: 'Vietnam ( Việt Nam )',
                diaChi: '_',
                soGiayTo: '',
                loaiGiayTo: 'Căn cước công dân',
                hoChieu: '',
                ngayHetHan: '',
                ngayNhapCanh: formatDateDisplay(ngayDen),
                cuaKhau: '',
                nLon: true,
                isVn: true,
                phuongXa: '',
                quanHuyen: '',
                tpTinh: '',
                donGia: br.rate || 0,
                mucDichNhapCanh: 'Du lịch',
                tamTruDen: formatDateDisplay(ngayDi),
                ngheNghiep: '',
                ghiChu: '',
                transferNote: transferNote,
                soDienThoai: b.contact_phone || '',
                noiLamViec: '',
                lyDoLuuTru: 'Du lịch',
                thuongTru: '',
                danToc: '',
                rawDob: '',
                rawEntryDate: ngayDen || '',
                roomStatus: brStatus,
                arrivalDate: br.arrival_date,
                departureDate: br.departure_date,
                isStayedThenMoved: isStayedThenMoved,
                isSameDayMoved: isSameDayMoved,
                hasVat: hasVat
              })
            }

            // 2. Tải danh sách Trẻ em trong phòng (br.children và assignedChildren)
            const brChildren = [
              ...(br.children || []),
              ...(br.assigned_children || br.assignedChildren || []),
              ...((b.children || []).filter(c => String(c.booking_room_id) === String(br.id)))
            ]
            const seenChildIds = new Set()
            brChildren.forEach(ch => {
              if (!ch || !ch.id || seenChildIds.has(ch.id)) return
              seenChildIds.add(ch.id)

              const isVnChild = (ch.nationality_code || 'VNM').toUpperCase() === 'VNM' || 
                               (ch.nationality_code || 'VN').toUpperCase() === 'VN' || 
                               (ch.nationality_code || '').toLowerCase().includes('vietnam') || 
                               (ch.nationality_code || '').toLowerCase().includes('việt nam')

              let childGenderText = 'M - Nam'
              if (ch.gender === 2 || String(ch.gender) === '2' || String(ch.gender).toLowerCase() === 'female' || (ch.title && ['Girl.', 'Girl', 'Ms.', 'Ms'].includes(ch.title))) {
                childGenderText = 'F - Nữ'
              }

              mapped.push({
                id: seq++,
                bookingId: b.id,
                ma: String(ma),
                phong: String(phong),
                ten: ch.full_name || 'Trẻ em',
                gioiTinh: childGenderText,
                ngaySinh: ch.dob ? formatDateDisplay(ch.dob) : '',
                ngayDen: formatDateDisplay(ngayDen),
                ngayDi: formatDateDisplay(ngayDi),
                quocTich: getNationalityName(ch.nationality_code || 'VNM'),
                diaChi: ch.address || '_',
                soGiayTo: ch.id_number || ch.passport_number || '',
                loaiGiayTo: ch.passport_number ? 'Hộ chiếu' : (ch.id_type || 'Căn cước công dân'),
                hoChieu: ch.passport_number || '',
                ngayHetHan: ch.passport_expiry ? formatDateDisplay(ch.passport_expiry) : '',
                ngayNhapCanh: ch.entry_date ? formatDateDisplay(ch.entry_date) : formatDateDisplay(ngayDen),
                cuaKhau: ch.border_gate || '',
                nLon: false,
                isVn: isVnChild,
                phuongXa: ch.ward || '',
                quanHuyen: ch.district || '',
                tpTinh: ch.province || '',
                donGia: br.rate || 0,
                mucDichNhapCanh: ch.entry_purpose || 'Du lịch',
                tamTruDen: ch.temp_residence_to ? formatDateDisplay(ch.temp_residence_to) : formatDateDisplay(ngayDi),
                ngheNghiep: '',
                ghiChu: ch.note || '',
                transferNote: transferNote,
                soDienThoai: ch.phone || '',
                noiLamViec: '',
                lyDoLuuTru: ch.entry_purpose || 'Du lịch',
                thuongTru: ch.address || '',
                danToc: '',
                rawDob: ch.dob || '',
                rawEntryDate: ch.entry_date || ngayDen || '',
                roomStatus: brStatus,
                arrivalDate: br.arrival_date,
                departureDate: br.departure_date,
                isStayedThenMoved: isStayedThenMoved,
                isSameDayMoved: isSameDayMoved,
                hasVat: hasVat
              })
            })
          })
        }
      })

      rowsData.value = mapped
    }
  } catch (err) {
    console.warn('Booking API fetch fallback to empty list:', err)
    rowsData.value = []
  }
}

watch(isoDate, () => {
  loadBookingData()
})

onMounted(() => {
  document.addEventListener('click', closeDropdowns)
  loadNationalities()
  initSystemDate()
  loadBookingData()
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdowns)
})
</script>

<template>
  <div class="kb-page">
    <!-- ═══ TOOLBAR ═══ -->
    <div class="toolbar">
      <!-- Date Selector with Calendar & Copy Button -->
      <div class="date-field">
        <div class="date-display-box" @click="openDatePicker" title="Click để chọn ngày">
          <span class="date-text">{{ selectedDate }}</span>
          <svg class="date-icon" width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </div>
        <button class="icon-btn copy-btn" @click.stop="copyDateToClipboard" title="Sao chép ngày">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="8" y="8" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="1.7"/></svg>
        </button>
        <input 
          type="date" 
          ref="datePickerInputRef" 
          :value="isoDate" 
          @change="handleDateInputChange" 
          class="hidden-native-date-input" 
        />
      </div>

      <div class="tb-sep"></div>

      <!-- Export Split Button (Clicking opens format selector panel) -->
      <div class="dropdown-anchor export-anchor">
        <div class="split-btn" :class="{ disabled: selectedRows.length === 0 }">
          <button 
            class="split-main btn-primary" 
            :disabled="selectedRows.length === 0"
            @click.stop="selectedRows.length > 0 && toggleDropdown('export')"
            :title="selectedRows.length === 0 ? 'Vui lòng chọn ít nhất 1 khách hàng để xuất file' : 'Chọn loại file xuất'"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Xuất ra file
          </button>
          <button 
            class="split-caret btn-primary" 
            :disabled="selectedRows.length === 0"
            @click.stop="selectedRows.length > 0 && toggleDropdown('export')"
            title="Tuỳ chọn xuất"
          >
            <svg width="9" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>

        <div class="dropdown-panel export-panel" :class="{ open: isExportOpen && selectedRows.length > 0 }">
          <div class="dp-head">
            <span class="dp-head-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.7"/></svg>
              Định dạng xuất
            </span>
            <button class="dp-head-x" @click="isExportOpen = false">
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="export-list">
            <button class="export-item" @click="handleExport('Xuất ra file Excel')">
              <span class="export-icon excel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7"/><path d="M8 13l2.5 4M13.5 13 11 17M8 17l2.5-4M13.5 17 11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </span>
              <div class="export-item-text">
                <span class="export-item-name">Xuất ra file Excel</span>
              </div>
            </button>

            <button class="export-item" @click="handleExport('Xuất ra file excel - mẫu khách nước ngoài')">
              <span class="export-icon excel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7"/><path d="M8 13l2.5 4M13.5 13 11 17M8 17l2.5-4M13.5 17 11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </span>
              <div class="export-item-text">
                <span class="export-item-name">Xuất ra file excel - mẫu khách nước ngoài</span>
              </div>
            </button>

            <button class="export-item" @click="handleExport('Xuất ra file excel - mẫu khách Việt Nam')">
              <span class="export-icon excel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7"/><path d="M8 13l2.5 4M13.5 13 11 17M8 17l2.5-4M13.5 17 11 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </span>
              <div class="export-item-text">
                <span class="export-item-name">Xuất ra file excel - mẫu khách Việt Nam</span>
              </div>
            </button>

            <button class="export-item" @click="handleExport('XML')">
              <span class="export-icon xml">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7"/><path d="M8 13l1-1.5 1 1.5M14 13l1-1.5 1 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </span>
              <div class="export-item-text">
                <span class="export-item-name">XML</span>
              </div>
            </button>

            <button class="export-item" @click="handleExport('CSV')">
              <span class="export-icon csv">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" stroke="currentColor" stroke-width="1.7"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7"/><path d="M8 14a2 2 0 0 1 0-2M12 12h2M16 12h.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </span>
              <div class="export-item-text">
                <span class="export-item-name">CSV</span>
              </div>
            </button>
          </div>
        </div>
      </div>

      <div class="tb-sep"></div>

      <!-- Search Input -->
      <div class="search-wrap" :class="{ 'has-val': searchTerm.length > 0 }">
        <svg class="s-icon" width="13" height="13" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <input type="text" v-model="searchTerm" placeholder="Tìm mã, phòng, tên, quốc tịch…" />
        <button class="search-clear" @click="searchTerm = ''">
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </button>
      </div>

      <div class="spacer"></div>

      <!-- Filter Dropdown Anchor (Matching Image 1) -->
      <div class="dropdown-anchor">
        <button class="filter-btn filter-select-box" :class="{ 'has-active': activeFilterCount > 0 }" @click.stop="toggleDropdown('filter')">
          <span class="filter-select-label">Chọn: {{ activeFilterCount }}</span>
          <svg width="8" height="5" viewBox="0 0 10 6" fill="none" class="filter-arrow-down"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <div class="dropdown-panel filter-popup-panel" :class="{ open: isFilterOpen }">
          <div class="filter-popup-top-header">
            <span class="filter-top-count">Chọn: {{ activeFilterCount }}</span>
            <svg width="8" height="5" viewBox="0 0 10 6" fill="none" class="filter-arrow-up"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>

          <div class="dp-search-box">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="dp-search-icon"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="text" v-model="filterSearchTerm" placeholder="" class="dp-search-input" />
          </div>

          <div class="dp-list filter-items-list">
            <label v-show="!filterSearchTerm || 'vat'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.vat" /> VAT
            </label>
            <label v-show="!filterSearchTerm || 'no vat'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.noVat" /> No VAT
            </label>
            <label v-show="!filterSearchTerm || 'trẻ em'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.children" /> Trẻ em
            </label>
            <label v-show="!filterSearchTerm || 'passport'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.passport" /> Passport
            </label>
            <label v-show="!filterSearchTerm || 'phòng chuyển'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.roomMove" /> Phòng Chuyển
            </label>
            <label v-show="!filterSearchTerm || 'khách đang ở'.includes(filterSearchTerm.toLowerCase())" class="dp-item">
              <input type="checkbox" v-model="filters.inHouse" /> Khách đang ở
            </label>
          </div>

          <div class="filter-popup-foot">
            <button class="btn-filter-save" @click="applyFilters">Lưu</button>
          </div>
        </div>
      </div>

      <!-- Column Visibility Anchor (All 16 columns toggleable) -->
      <div class="dropdown-anchor">
        <button class="filter-btn icon-only" @click.stop="toggleDropdown('col')" title="Tuỳ chỉnh cột">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="16" cy="6" r="2" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="12" r="2" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="18" r="2" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>

        <div class="dropdown-panel wide" :class="{ open: isColOpen }">
          <div class="dp-head">
            <span class="dp-head-label">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="18" rx="1.5" stroke="currentColor" stroke-width="1.7"/><rect x="14" y="3" width="7" height="18" rx="1.5" stroke="currentColor" stroke-width="1.7"/></svg>
              Hiển thị cột
            </span>
            <button class="dp-head-x" @click="isColOpen = false">
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="dp-list">
            <label class="dp-item all-row">
              <input type="checkbox" v-model="allColsChecked" /> Hiển thị tất cả
            </label>
            <hr class="dp-hr" />
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ma" /> Mã ĐK</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.phong" /> Phòng</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ten" /> Tên khách</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.gioitinh" /> Giới tính</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ngaysinh" /> Ngày sinh</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ngayden" /> Ngày đến</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ngaydi" /> Ngày đi</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.quoctich" /> Quốc tịch</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.diachi" /> Địa chỉ</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.sogiayto" /> Số giấy tờ</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.hochieu" /> Hộ chiếu</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ngayhethan" /> Ngày hết hạn</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ngaynhapcanh" /> Ngày nhập cảnh</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.cuakhau" /> Cửa khẩu</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.nlon" /> N.Lớn</label>
            <label class="dp-item"><input type="checkbox" v-model="colVisibility.ghichuchuyen" /> Ghi chú chuyển phòng</label>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ TABLE ═══ -->
    <div class="table-shell">
      <div class="table-scroll">
        <table class="grid" :class="tableClasses">
          <thead>
            <tr>
              <th class="col-checkbox" style="padding-left:14px;">
                <input type="checkbox" v-model="checkAll" @change="toggleCheckAll" />
              </th>
              <th class="col-ma sortable" :class="{ 'sort-asc': sortKey === 'ma' && sortDir === 1, 'sort-desc': sortKey === 'ma' && sortDir === -1 }" @click="handleSort('ma')">
                <div class="th-inner">
                  Mã Đăng Ký
                  <span class="sort-icons">
                    <svg class="up" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg class="down" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </span>
                </div>
              </th>
              <th class="col-phong sortable" :class="{ 'sort-asc': sortKey === 'phong' && sortDir === 1, 'sort-desc': sortKey === 'phong' && sortDir === -1 }" @click="handleSort('phong')">
                <div class="th-inner">
                  Phòng
                  <span class="sort-icons">
                    <svg class="up" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg class="down" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </span>
                </div>
              </th>
              <th class="col-ten">Tên Khách</th>
              <th class="col-gioitinh">Giới Tính</th>
              <th class="col-ngaysinh">Ngày Sinh</th>
              <th class="col-ngayden sortable" :class="{ 'sort-asc': sortKey === 'ngayDen' && sortDir === 1, 'sort-desc': sortKey === 'ngayDen' && sortDir === -1 }" @click="handleSort('ngayDen')">
                <div class="th-inner">
                  Ngày Đến
                  <span class="sort-icons">
                    <svg class="up" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg class="down" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </span>
                </div>
              </th>
              <th class="col-ngaydi sortable" :class="{ 'sort-asc': sortKey === 'ngayDi' && sortDir === 1, 'sort-desc': sortKey === 'ngayDi' && sortDir === -1 }" @click="handleSort('ngayDi')">
                <div class="th-inner">
                  Ngày Đi
                  <span class="sort-icons">
                    <svg class="up" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg class="down" width="7" height="4" viewBox="0 0 10 6" fill="none"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </span>
                </div>
              </th>
              <th class="col-quoctich">Quốc Tịch</th>
              <th class="col-diachi">Địa chỉ</th>
              <th class="col-sogiayto">Số Giấy Tờ</th>
              <th class="col-hochieu">Hộ chiếu</th>
              <th class="col-ngayhethan">Ngày hết hạn</th>
              <th class="col-ngaynhapcanh">Ngày Nhập Cảnh</th>
              <th class="col-cuakhau">Cửa Khẩu</th>
              <th class="col-nlon">N.Lớn</th>
              <th class="col-ghichuchuyen">Ghi chú chuyển phòng</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="filteredRows.length === 0">
              <td colspan="17">
                <div class="empty-state">Không tìm thấy kết quả phù hợp.</div>
              </td>
            </tr>
            <tr v-for="r in filteredRows" :key="r.id">
              <td class="col-checkbox" style="padding-left:14px;">
                <input type="checkbox" :value="r.id" v-model="selectedRows" />
              </td>
              <td class="col-ma cell-ma">{{ r.ma }}</td>
              <td class="col-phong cell-phong">{{ r.phong }}</td>
              <td class="col-ten font-bold text-slate-800">{{ r.ten }}</td>
              <td class="col-gioitinh">{{ r.gioiTinh }}</td>
              <td class="col-ngaysinh">{{ r.ngaySinh || '' }}</td>
              <td class="col-ngayden">{{ r.ngayDen }}</td>
              <td class="col-ngaydi">{{ r.ngayDi }}</td>
              <td class="col-quoctich">
                <span v-if="r.quocTich" class="cell-country">{{ r.quocTich }}</span>
              </td>
              <td class="col-diachi">{{ r.diaChi !== '_' ? r.diaChi : '' }}</td>
              <td class="col-sogiayto">{{ r.soGiayTo }}</td>
              <td class="col-hochieu">{{ r.hoChieu }}</td>
              <td class="col-ngayhethan">{{ r.ngayHetHan }}</td>
              <td class="col-ngaynhapcanh">{{ r.ngayNhapCanh }}</td>
              <td class="col-cuakhau">{{ r.cuaKhau }}</td>
              <td class="col-nlon">
                <label class="sw">
                  <input type="checkbox" :checked="r.nLon" disabled />
                  <span class="sw-track"></span>
                </label>
              </td>
              <td class="col-ghichuchuyen cell-ghichuchuyen">{{ r.transferNote || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Table Footer Statistics -->
      <div class="table-footer-bar">
        <div class="footer-stat">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.7"/></svg>
          Tổng phòng: <strong>{{ totalUniqueRooms }}</strong>
        </div>
        <div class="footer-sep"></div>
        <div class="footer-stat">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.7"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          Tổng khách: <strong>{{ totalGuests }}</strong>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kb-page {
  --navy: #1E2D4A;
  --navy-hover: #162238;
  --blue: #2F6FED;
  --blue-hover: #255CD1;
  --blue-soft: #EAF1FE;
  --bg: #EEF1F6;
  --surface: #FFFFFF;
  --surface-alt: #F5F7FA;
  --surface-hover: #F0F4FA;
  --border: #DDE2EA;
  --border-strong: #C4CDD8;
  --text: #1A1D26;
  --text-muted: #5C6470;
  --text-faint: #9AA1AC;
  --danger: #DC3545;
  --success: #12A594;
  --radius-sm: 6px;
  --radius: 8px;
  --radius-lg: 10px;

  padding: 16px 20px 48px;
  width: 100%;
  max-width: 100%;
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, Roboto, "Helvetica Neue", Arial, sans-serif;
  color: var(--text);
  font-size: 13px;
  line-height: 1.5;
  background: var(--bg);
  min-height: 100%;
}

/* Toolbar */
.toolbar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 9px 12px;
  margin-bottom: 12px;
  box-shadow: 0 1px 3px rgba(20, 30, 55, 0.05);
}

/* Date Field Box */
.date-field {
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;
}
.date-display-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5.5px 10px;
  background: var(--surface-alt);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
  user-select: none;
  transition: .15s;
}
.date-display-box:hover {
  border-color: var(--blue);
  background: #fff;
}
.date-text {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text);
  font-family: inherit;
}
.date-icon {
  color: var(--text-muted);
}
.copy-btn {
  width: 28px;
  height: 28px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  transition: .15s;
}
.copy-btn:hover {
  border-color: var(--blue);
  color: var(--blue);
  background: var(--blue-soft);
}
.hidden-native-date-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
  pointer-events: none;
  visibility: hidden;
}

.tb-sep {
  width: 1px;
  height: 22px;
  background: var(--border);
  flex-shrink: 0;
  margin: 0 3px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6.5px 13px;
  border-radius: var(--radius-sm);
  font-size: 12.5px;
  font-weight: 650;
  cursor: pointer;
  transition: .15s;
  border: 1px solid transparent;
  white-space: nowrap;
  line-height: 1;
}
.btn-primary {
  background: var(--blue);
  color: #fff;
}
.btn-primary:hover {
  background: var(--blue-hover);
}

/* Split Button */
.split-btn {
  display: flex;
  align-items: stretch;
  border-radius: var(--radius-sm);
  overflow: hidden;
  transition: .15s;
}
.split-btn.disabled,
.split-main:disabled,
.split-caret:disabled {
  opacity: 0.5;
  cursor: not-allowed !important;
  background: var(--border-strong) !important;
  border-color: var(--border-strong) !important;
  pointer-events: none !important;
}

.split-main {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6.5px 12px;
  background: var(--blue);
  color: #fff;
  font-size: 12.5px;
  font-weight: 650;
  cursor: pointer;
  border: none;
  border-right: 1px solid rgba(255, 255, 255, 0.25);
  transition: .15s;
  white-space: nowrap;
}
.split-main:hover {
  background: var(--blue-hover);
}
.split-caret {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 9px;
  background: var(--blue);
  color: #fff;
  border: none;
  cursor: pointer;
  transition: .15s;
}
.split-caret:hover {
  background: var(--blue-hover);
}

/* Export Panel */
.export-anchor {
  flex-shrink: 0;
}
.export-panel {
  width: 290px;
  left: 0;
  right: auto;
}
.export-list {
  padding: 6px;
}
.export-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  border: none;
  background: none;
  cursor: pointer;
  text-align: left;
  transition: .1s;
}
.export-item:hover {
  background: var(--blue-soft);
}
.export-item:hover .export-item-name {
  color: var(--blue);
}
.export-icon {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.export-icon.excel {
  background: #E8F5E9;
  color: #2E7D32;
}
.export-icon.xml {
  background: #FFF3E0;
  color: #E65100;
}
.export-icon.csv {
  background: #E8EAF6;
  color: #3949AB;
}
.export-item-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}
.export-item-name {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text);
}

/* Search */
.search-wrap {
  position: relative;
  width: 230px;
  flex-shrink: 0;
}
.search-wrap .s-icon {
  position: absolute;
  left: 9px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-faint);
  pointer-events: none;
}
.search-wrap input {
  width: 100%;
  padding: 6.5px 28px 6.5px 30px;
  font-size: 12.5px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--surface-alt);
  color: var(--text);
  outline: none;
  transition: .15s;
}
.search-wrap input::placeholder {
  color: var(--text-faint);
}
.search-wrap input:focus {
  border-color: var(--blue);
  background: #fff;
  box-shadow: 0 0 0 3px var(--blue-soft);
}
.search-clear {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  width: 17px;
  height: 17px;
  border: none;
  background: var(--border-strong);
  border-radius: 50%;
  color: var(--text-muted);
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
  transition: .12s;
}
.search-clear:hover {
  background: var(--blue);
  color: #fff;
}
.search-wrap.has-val .search-clear {
  display: flex;
}

.spacer {
  flex: 1;
}

/* Filter / Column button */
.dropdown-anchor {
  position: relative;
  flex-shrink: 0;
}
.filter-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6.5px 11px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-sm);
  background: var(--surface);
  cursor: pointer;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text-muted);
  transition: .15s;
}
.filter-btn:hover {
  border-color: var(--blue);
  color: var(--blue);
}
.filter-btn.has-active {
  border-color: var(--blue);
  background: var(--blue-soft);
  color: var(--blue);
}
.filter-btn .fbadge {
  background: var(--blue);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 99px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.filter-btn.icon-only {
  padding: 6.5px 8px;
}

/* Dropdown Panel */
.dropdown-panel {
  position: absolute;
  top: calc(100% + 5px);
  right: 0;
  width: 252px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: 0 16px 40px rgba(20, 30, 55, 0.18), 0 4px 12px rgba(20, 30, 55, 0.08);
  z-index: 100;
  display: none;
  overflow: hidden;
}
.dropdown-panel.open {
  display: block;
  animation: dpIn .14s ease;
}
@keyframes dpIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.dp-head {
  background: var(--navy);
  padding: 10px 13px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.dp-head-label {
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: .02em;
  display: flex;
  align-items: center;
  gap: 7px;
}
.dp-head-x {
  width: 22px;
  height: 22px;
  background: rgba(255, 255, 255, 0.12);
  border: none;
  border-radius: 4px;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: .12s;
}
.dp-head-x:hover {
  background: rgba(255, 255, 255, 0.24);
}

.dp-list {
  max-height: 230px;
  overflow-y: auto;
  padding: 5px;
}
.dp-list::-webkit-scrollbar {
  width: 4px;
}
.dp-list::-webkit-scrollbar-thumb {
  background: var(--border-strong);
  border-radius: 2px;
}

.dp-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 10px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--text);
  transition: .1s;
}
.dp-item:hover {
  background: var(--blue-soft);
  color: var(--blue);
}
.dp-item input[type=checkbox] {
  width: 14px;
  height: 14px;
  accent-color: var(--blue);
  cursor: pointer;
  flex-shrink: 0;
}
.dp-hr {
  border: none;
  border-top: 1px solid var(--border);
  margin: 4px 6px;
}
.dp-item.all-row {
  font-weight: 700;
  color: var(--text-muted);
}

.dp-foot {
  padding: 9px 10px;
  border-top: 1px solid var(--border);
  display: flex;
  gap: 7px;
  background: var(--surface-alt);
}
.dp-foot .btn {
  padding: 6px 12px;
  font-size: 12px;
}
.btn-reset-sm {
  background: none;
  border: 1px solid var(--border-strong);
  color: var(--text-muted);
}
.btn-reset-sm:hover {
  border-color: var(--danger);
  color: var(--danger);
}
.dp-foot .btn-primary {
  flex: 1;
  justify-content: center;
}
.dropdown-panel.wide {
  width: 266px;
}

/* Table Shell */
.table-shell {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(20, 30, 55, 0.06);
}

.table-scroll {
  overflow-x: auto;
}
.table-scroll::-webkit-scrollbar {
  height: 5px;
}
.table-scroll::-webkit-scrollbar-thumb {
  background: var(--border-strong);
  border-radius: 3px;
}

table.grid {
  width: 100%;
  border-collapse: collapse;
  min-width: 100%;
  table-layout: fixed;
}
table.grid thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--surface-alt);
  text-align: left;
  font-size: 11.5px;
  font-weight: 700;
  color: #000;
  letter-spacing: .01em;
  padding: 10px 13px;
  border-bottom: 2px solid var(--border);
  white-space: nowrap;
}
table.grid th .th-inner {
  display: flex;
  align-items: center;
  gap: 5px;
}
table.grid th.sortable {
  cursor: pointer;
  user-select: none;
}
table.grid th.sortable:hover .th-inner {
  color: var(--blue);
}
.sort-icons {
  display: flex;
  flex-direction: column;
  line-height: 0;
  gap: 1px;
  margin-left: 1px;
}
.sort-icons svg {
  display: block;
}
.sort-icons .up,
.sort-icons .down {
  color: var(--border-strong);
  transition: .12s;
}
th.sort-asc .sort-icons .up {
  color: var(--blue);
}
th.sort-desc .sort-icons .down {
  color: var(--blue);
}

table.grid td {
  padding: 9.5px 13px;
  border-bottom: 1px solid var(--border);
  color: var(--text);
  white-space: nowrap;
  font-size: 12.5px;
}
table.grid tbody tr:last-child td {
  border-bottom: none;
}
table.grid tbody tr:hover td {
  background: var(--surface-hover);
}

.cell-ma {
  color: var(--navy);
  font-weight: 600;
}
.cell-phong {
  color: var(--navy);
  font-weight: 600;
}
.cell-country {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--text);
  font-size: 12px;
}

/* Toggle Switch */
.sw {
  position: relative;
  width: 32px;
  height: 18px;
  display: inline-block;
  flex-shrink: 0;
}
.sw input {
  opacity: 0;
  width: 0;
  height: 0;
}
.sw-track {
  position: absolute;
  inset: 0;
  background: var(--border-strong);
  border-radius: 99px;
  cursor: pointer;
  transition: .2s;
}
.sw-track::before {
  content: "";
  position: absolute;
  width: 12px;
  height: 12px;
  left: 3px;
  top: 3px;
  background: #fff;
  border-radius: 50%;
  transition: .2s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
}
.sw input:checked + .sw-track {
  background: var(--blue);
}
.sw input:checked + .sw-track::before {
  transform: translateX(14px);
}

input[type=checkbox] {
  width: 14px;
  height: 14px;
  accent-color: var(--blue);
  cursor: pointer;
}

/* Column Widths */
.col-checkbox { width: 3.5%; }
.col-ma { width: 6%; }
.col-phong { width: 5%; }
.col-ten { width: 8.5%; }
.col-gioitinh { width: 5%; }
.col-ngaysinh { width: 6%; }
.col-ngayden { width: 6.5%; }
.col-ngaydi { width: 6.5%; }
.col-quoctich { width: 9%; }
.col-diachi { width: 5%; }
.col-sogiayto { width: 6.5%; }
.col-hochieu { width: 5.5%; }
.col-ngayhethan { width: 6%; }
.col-ngaynhapcanh { width: 6.5%; }
.col-cuakhau { width: 5.5%; }
.col-nlon { width: 4%; }
.col-ghichuchuyen { min-width: 220px; width: 14%; }

/* Column Hiding (All 16 columns) */
table.grid.hide-ma .col-ma,
table.grid.hide-phong .col-phong,
table.grid.hide-ten .col-ten,
table.grid.hide-gioitinh .col-gioitinh,
table.grid.hide-ngaysinh .col-ngaysinh,
table.grid.hide-ngayden .col-ngayden,
table.grid.hide-ngaydi .col-ngaydi,
table.grid.hide-quoctich .col-quoctich,
table.grid.hide-diachi .col-diachi,
table.grid.hide-sogiayto .col-sogiayto,
table.grid.hide-hochieu .col-hochieu,
table.grid.hide-ngayhethan .col-ngayhethan,
table.grid.hide-ngaynhapcanh .col-ngaynhapcanh,
table.grid.hide-cuakhau .col-cuakhau,
table.grid.hide-nlon .col-nlon,
table.grid.hide-ghichuchuyen .col-ghichuchuyen {
  display: none !important;
}

.cell-ghichuchuyen {
  color: #c0392b;
  font-size: 11.5px;
  font-style: italic;
  white-space: normal;
}

/* Filter Dropdown Popup (Image 1) */
.filter-select-box {
  background: #fff;
  border: 1px solid var(--border-strong);
  border-radius: 4px;
  padding: 5px 10px;
  font-size: 12px;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 80px;
  justify-content: space-between;
}
.filter-select-box:hover {
  border-color: #999;
}
.filter-select-box.has-active {
  border-color: var(--blue);
  background: var(--blue-soft);
  color: var(--blue);
}
.filter-popup-panel {
  width: 190px;
  padding: 8px;
  background: #ffffff;
  border: 1px solid #cdd5df;
  border-radius: 4px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
.filter-popup-top-header {
  display: none;
}
.dp-search-box {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 6px;
}
.dp-search-box .dp-search-input {
  width: 100%;
  height: 26px;
  border: 1px solid #cdd5df;
  border-radius: 3px;
  padding: 2px 24px 2px 6px;
  font-size: 12px;
  outline: none;
}
.dp-search-box .dp-search-input:focus {
  border-color: var(--blue);
}
.dp-search-box .dp-search-icon {
  position: absolute;
  right: 6px;
  color: #888;
  pointer-events: none;
}
.filter-items-list {
  max-height: 220px;
  overflow-y: auto;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.filter-items-list .dp-item {
  padding: 4px 6px;
  font-size: 12px;
  font-weight: 400;
  color: #333;
  border-radius: 3px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.filter-items-list .dp-item:hover {
  background: #f0f5fa;
}
.filter-popup-foot {
  margin-top: 8px;
  padding-top: 4px;
}
.btn-filter-save {
  background: #7fc1eb;
  color: #fff;
  border: none;
  border-radius: 3px;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
}
.btn-filter-save:hover {
  background: #5baee4;
}

.empty-state {
  padding: 56px 20px;
  text-align: center;
  color: var(--text-faint);
  font-size: 13px;
}

/* Footer Statistics Bar */
.table-footer-bar {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 9px 16px;
  border-top: 1px solid var(--border);
  background: var(--surface-alt);
  font-size: 12px;
  color: var(--text-muted);
}
.footer-stat {
  display: flex;
  align-items: center;
  gap: 5px;
}
.footer-stat svg {
  opacity: .55;
  flex-shrink: 0;
}
.footer-stat strong {
  color: var(--navy);
  font-weight: 700;
  font-size: 13px;
  margin-left: 2px;
}
.footer-sep {
  width: 1px;
  height: 14px;
  background: var(--border-strong);
}

@media(max-width:768px) {
  .toolbar {
    flex-direction: column;
    align-items: stretch;
  }
  .search-wrap {
    width: 100%;
  }
}
</style>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import { 
  X, Save, Play, RefreshCw, FileText, Layers, History, Settings,
  Plus, Trash2, ArrowUp, ArrowDown, ChevronRight, Check, RotateCcw,
  AlignLeft, AlignCenter, AlignRight, AlignJustify, Bold
} from '@lucide/vue'

const props = defineProps({
  templateId: {
    type: Number,
    required: true
  },
  isOpen: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'saved'])

const uiStore = useUiStore()
const loading = ref(false)
const saving = ref(false)
const template = ref(null)
const activeTab = ref('design') // 'design' | 'preview' | 'css' | 'versions'

// Version control state
const versions = ref([])
const note = ref('')
const showSaveModal = ref(false)

// Preview state
const previewHtml = ref('')
const loadingPreview = ref(false)

// Editor state
const selectedBand = ref('header') // 'header' | 'detail' | 'footer'
const selectedBlockId = ref(null)
const openCategories = ref({
  hotel: true,
  customer: true,
  booking: true,
  room: true,
  payment: true,
  registration: true,
  lists: true
})

// Visual Blocks structure
const blocks = ref({
  header: [],
  detail: [],
  footer: []
})

// Variables dictionary for Field List
const fieldList = {
  hotel: [
    { label: 'Tên khách sạn', value: 'hotel.name' },
    { label: 'Địa chỉ', value: 'hotel.address' },
    { label: 'Số điện thoại', value: 'hotel.phone' },
    { label: 'Email', value: 'hotel.email' },
    { label: 'Logo', value: 'hotel.logo' }
  ],
  customer: [
    { label: 'Tên khách hàng', value: 'customer.name' },
    { label: 'Số điện thoại', value: 'customer.phone' },
    { label: 'Email', value: 'customer.email' },
    { label: 'Số giấy tờ (ID Card)', value: 'customer.id_card' }
  ],
  booking: [
    { label: 'Mã đặt phòng', value: 'booking.code' },
    { label: 'Ngày đến (Check-in)', value: 'booking.checkin_date' },
    { label: 'Ngày đi (Check-out)', value: 'booking.checkout_date' },
    { label: 'Số người lớn', value: 'booking.adults' },
    { label: 'Số trẻ em', value: 'booking.children' },
    { label: 'Số đêm lưu trú', value: 'booking.nights' }
  ],
  room: [
    { label: 'Số phòng', value: 'room.number' },
    { label: 'Hạng phòng', value: 'room.class' },
    { label: 'Giá phòng', value: 'room.price' }
  ],
  payment: [
    { label: 'Tiền cọc', value: 'payment.deposit' },
    { label: 'Tổng số tiền', value: 'payment.total' },
    { label: 'Phương thức', value: 'payment.method' }
  ],
  lists: [
    { label: 'Bảng dịch vụ (Services)', value: 'booking.services', isList: true },
    { label: 'Bảng phòng (Rooms)', value: 'booking.rooms', isList: true },
    { label: 'Bảng thanh toán (Payments)', value: 'booking.payments', isList: true }
  ],
  registration: [
    { label: 'Số đặt phòng (Confirmation No)', value: 'registration.confirmation_no' },
    { label: 'Tên công ty (Company)', value: 'registration.company' },
    { label: 'Họ tên khách (Full name)', value: 'registration.guest_name' },
    { label: 'Số CCCD/Hộ chiếu (ID/Passport No)', value: 'registration.id_passport' },
    { label: 'Quốc tịch (Nationality)', value: 'registration.nationality' },
    { label: 'Địa chỉ email (Email address)', value: 'registration.email' },
    { label: 'Số điện thoại (Phone number)', value: 'registration.phone' },
    { label: 'Ngày đến (Arrival date)', value: 'registration.arrival_date' },
    { label: 'Ngày đi (Departure date)', value: 'registration.departure_date' },
    { label: 'Hạng phòng (Room type)', value: 'registration.room_type' },
    { label: 'Số phòng (Room number)', value: 'registration.room_no' },
    { label: 'Số lượng phòng (No. of Room(s))', value: 'registration.no_rooms' },
    { label: 'Số lượng khách (No. of Guest(s))', value: 'registration.no_guests' },
    { label: 'Giá phòng (Room rate)', value: 'registration.room_rate' },
    { label: 'Số đêm (No. of Night(s))', value: 'registration.no_nights' },
    { label: 'Tiền đặt cọc (Deposit)', value: 'registration.deposit_method' },
    { label: 'Phương thức thanh toán (Payment method)', value: 'registration.payment_method' }
  ]
}

const getListFields = (listValue) => {
  if (listValue === 'booking.services') {
    return [
      { label: 'Ngày dịch vụ', value: 'service.date' },
      { label: 'Tên dịch vụ', value: 'service.name' },
      { label: 'Phòng', value: 'service.room' },
      { label: 'Đơn giá', value: 'service.price' },
      { label: 'Số lượng', value: 'service.quantity' },
      { label: 'Thành tiền', value: 'service.amount' }
    ]
  }
  if (listValue === 'booking.rooms') {
    return [
      { label: 'Số phòng', value: 'room.room_number' },
      { label: 'Hạng phòng', value: 'room.room_class' },
      { label: 'Giá phòng', value: 'room.price' }
    ]
  }
  if (listValue === 'booking.payments') {
    return [
      { label: 'Ngày', value: 'payment.date' },
      { label: 'Giờ', value: 'payment.time' },
      { label: 'Phương thức', value: 'payment.method' },
      { label: 'Mã Ref', value: 'payment.ref' },
      { label: 'Số tiền', value: 'payment.amount' }
    ]
  }
  return []
}

// Strip HTML helper to edit plain text
const stripHtml = (html) => {
  if (!html) return ''
  let text = html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n')
    .replace(/<\/div>/gi, '\n')
    .replace(/<\/h[1-6]>/gi, '\n')
    .replace(/<[^>]*>/g, '') // Remove HTML tags
  
  // Decode HTML entities
  const txt = document.createElement('textarea')
  txt.innerHTML = text
  text = txt.value
  
  return text.trim()
}

// Fetch template data
const loadTemplate = async () => {
  loading.value = true
  try {
    const res = await http.get(`/templates/${props.templateId}`)
    if (res.data && res.data.data) {
      template.value = res.data.data
      
      // Load blocks structure from JSON
      if (template.value.content_json) {
        const json = template.value.content_json
        const mapBlock = (b) => {
          if (b.type === 'text') {
            return {
              ...b,
              content: b.content,
              style: {
                ...b.style
              }
            }
          }
          return b
        }
        blocks.value = {
          header: (json.header || []).map(mapBlock),
          detail: (json.detail || []).map(mapBlock),
          footer: (json.footer || []).map(mapBlock)
        }
      } else {
        // Fallback or empty structure
        blocks.value = { header: [], detail: [], footer: [] }
      }
      
      // Select first block if exists
      if (blocks.value.header.length > 0) {
        selectedBlockId.value = blocks.value.header[0].id
        selectedBand.value = 'header'
      } else if (blocks.value.detail.length > 0) {
        selectedBlockId.value = blocks.value.detail[0].id
        selectedBand.value = 'detail'
      } else if (blocks.value.footer.length > 0) {
        selectedBlockId.value = blocks.value.footer[0].id
        selectedBand.value = 'footer'
      } else {
        selectedBlockId.value = null
      }
    }
  } catch (err) {
    console.error('Lỗi tải mẫu in:', err)
    uiStore.showToast('Không thể tải dữ liệu mẫu in', 'error')
  } finally {
    loading.value = false
  }
}

// Load versions
const loadVersions = async () => {
  try {
    const res = await http.get(`/templates/${props.templateId}/versions`)
    if (res.data && res.data.data) {
      versions.value = res.data.data
    }
  } catch (err) {
    console.error('Lỗi tải danh sách phiên bản:', err)
  }
}

// Render Preview
const loadPreview = async () => {
  loadingPreview.value = true
  try {
    // Generate compiled HTML first locally and sync
    compileHtml()
    
    // Call preview API with currently compiled template content
    await saveTemplateDraft()
    
    const res = await http.get(`/templates/${props.templateId}/preview`)
    if (res.data && res.data.html) {
      previewHtml.value = res.data.html
    }
  } catch (err) {
    console.error('Lỗi tải preview mẫu:', err)
  } finally {
    loadingPreview.value = false
  }
}

// Selected block getter & setter helper
// Selected block getter & setter helper (with recursive search for sub-blocks inside columns layout)
const selectedBlock = computed(() => {
  if (!selectedBlockId.value) return null
  const bandBlocks = blocks.value[selectedBand.value]
  
  // 1. Check top-level blocks
  const topBlock = bandBlocks.find(b => b.id === selectedBlockId.value)
  if (topBlock) return topBlock
  
  // 2. Check nested blocks inside column grids
  for (const b of bandBlocks) {
    if (b.type === 'columns' && b.columns) {
      for (const col of b.columns) {
        if (col.blocks) {
          const foundSub = col.blocks.find(sb => sb.id === selectedBlockId.value)
          if (foundSub) return foundSub
        }
      }
    }
  }
  return null
})

// Columns Layout Sub-block helpers
const addSubBlock = (parentBlock, colIdx, type) => {
  const band = selectedBand.value
  const id = `${band}_sub_${type}_${Date.now()}`
  let subBlock = {
    id,
    type,
    style: {
      textAlign: 'left',
      fontSize: '12px',
      paddingTop: '2px',
      paddingBottom: '2px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '0px',
      marginBottom: '2px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'pre-wrap'
    }
  }
  
  if (type === 'text') {
    subBlock.content = 'Nhấp để sửa văn bản...'
  } else if (type === 'image') {
    subBlock.content = 'hotel.logo'
    subBlock.imageUrl = ''
  } else if (type === 'spacer') {
    subBlock.height = 15
  } else if (type === 'divider') {
    subBlock.content = '<hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 5px 0;">'
  }
  
  if (!parentBlock.columns[colIdx].blocks) {
    parentBlock.columns[colIdx].blocks = []
  }
  parentBlock.columns[colIdx].blocks.push(subBlock)
  selectedBlockId.value = id
  compileHtml()
}

const deleteSubBlock = (parentBlock, colIdx, subBlockId) => {
  parentBlock.columns[colIdx].blocks = parentBlock.columns[colIdx].blocks.filter(b => b.id !== subBlockId)
  if (selectedBlockId.value === subBlockId) {
    selectedBlockId.value = null
  }
  compileHtml()
}

const moveSubBlock = (parentBlock, colIdx, index, direction) => {
  const subBlocks = parentBlock.columns[colIdx].blocks
  if (direction === 'up' && index > 0) {
    const temp = subBlocks[index]
    subBlocks[index] = subBlocks[index - 1]
    subBlocks[index - 1] = temp
  } else if (direction === 'down' && index < subBlocks.length - 1) {
    const temp = subBlocks[index]
    subBlocks[index] = subBlocks[index + 1]
    subBlocks[index + 1] = temp
  }
  compileHtml()
}

const setColumnWidths = (block, layout) => {
  if (layout === '50-50') {
    block.columns = [
      { width: '50%', blocks: block.columns[0]?.blocks || [] },
      { width: '50%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '30-70') {
    block.columns = [
      { width: '30%', blocks: block.columns[0]?.blocks || [] },
      { width: '70%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '70-30') {
    block.columns = [
      { width: '70%', blocks: block.columns[0]?.blocks || [] },
      { width: '30%', blocks: block.columns[1]?.blocks || [] }
    ]
  } else if (layout === '33-33-33') {
    block.columns = [
      { width: '33.3%', blocks: block.columns[0]?.blocks || [] },
      { width: '33.3%', blocks: block.columns[1]?.blocks || [] },
      { width: '33.4%', blocks: block.columns[2]?.blocks || [] }
    ]
  }
  compileHtml()
}

const uploadingImage = ref(false)

const handleImageUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return
  
  const block = selectedBlock.value
  if (!block || block.type !== 'image') return

  const formData = new FormData()
  formData.append('image', file)

  uploadingImage.value = true
  try {
    const res = await http.post('/templates/upload-image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (res.data && res.data.success) {
      block.imageUrl = res.data.url
      compileHtml()
      uiStore.showToast('Tải ảnh lên thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi upload ảnh:', err)
    let errorMsg = 'Không thể tải ảnh lên. Vui lòng thử lại.'
    if (err.response && err.response.data && err.response.data.message) {
      errorMsg = err.response.data.message
    }
    uiStore.showToast(errorMsg, 'error')
  } finally {
    uploadingImage.value = false
    event.target.value = ''
  }
}

// Manage block addition
const addBlock = (type) => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  
  let newBlock = {
    id,
    type,
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '0px',
      marginBottom: '5px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'pre-wrap'
    }
  }
  
  if (type === 'text') {
    newBlock.content = 'Nhấp vào đây để sửa nội dung...'
  } else if (type === 'image') {
    newBlock.content = 'hotel.logo' // bindings variable or direct image tag
    newBlock.imageUrl = ''
    newBlock.style.textAlign = 'center'
  } else if (type === 'divider') {
    newBlock.content = '<hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 10px 0;">'
  } else if (type === 'spacer') {
    newBlock.height = 20 // mm or px
  } else if (type === 'table') {
    newBlock.dataSource = 'booking.services'
    newBlock.columns = [
      { header: 'Tên dịch vụ', value: 'service.name', width: '50%' },
      { header: 'Đơn giá', value: 'service.price', width: '20%' },
      { header: 'Số lượng', value: 'service.quantity', width: '10%' },
      { header: 'Thành tiền', value: 'service.amount', width: '20%' }
    ]
    newBlock.style.marginTop = '10px'
    newBlock.style.marginBottom = '10px'
  } else if (type === 'columns') {
    newBlock.columns = [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_col1_txt_${Date.now()}`,
            type: 'text',
            content: 'Cột trái...',
            style: {
              textAlign: 'left',
              fontSize: '12px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'pre-wrap'
            }
          }
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_col2_txt_${Date.now() + 1}`,
            type: 'text',
            content: 'Cột phải...',
            style: {
              textAlign: 'left',
              fontSize: '12px',
              color: '#1e293b',
              fontWeight: 'normal',
              whiteSpace: 'pre-wrap'
            }
          }
        ]
      }
    ]
    newBlock.style.marginTop = '10px'
    newBlock.style.marginBottom = '10px'
  }
  
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
}

// Reorder blocks
const moveBlock = (index, direction) => {
  const band = selectedBand.value
  const bandBlocks = blocks.value[band]
  if (direction === 'up' && index > 0) {
    const temp = bandBlocks[index]
    bandBlocks[index] = bandBlocks[index - 1]
    bandBlocks[index - 1] = temp
  } else if (direction === 'down' && index < bandBlocks.length - 1) {
    const temp = bandBlocks[index]
    bandBlocks[index] = bandBlocks[index + 1]
    bandBlocks[index + 1] = temp
  }
}

// Remove block
const deleteBlock = (band, id) => {
  blocks.value[band] = blocks.value[band].filter(b => b.id !== id)
  if (selectedBlockId.value === id) {
    selectedBlockId.value = null
  }
}

// Insert dynamic variable at cursor position or append to content
const insertVariable = (value) => {
  const block = selectedBlock.value
  if (!block) {
    uiStore.showToast('Vui lòng chọn một khối văn bản hoặc bảng trước khi chèn biến', 'warning')
    return
  }
  
  const placeholder = `{{${value}}}`
  
  if (block.type === 'text') {
    // Basic rich text insertion - append for simplicity
    if (!block.content) block.content = ''
    block.content += ' ' + placeholder
  } else if (block.type === 'image') {
    block.content = value
  }
}

// Compile blocks JSON structures to plain HTML
const compileHtml = () => {
  let html = ''
  
  // 1. Process Header Band
  html += '<div class="report-header-band">\n'
  blocks.value.header.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  // 2. Process Detail Band
  html += '<div class="report-detail-band">\n'
  blocks.value.detail.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  // 3. Process Footer Band
  html += '<div class="report-footer-band">\n'
  blocks.value.footer.forEach(b => {
    html += compileBlockToHtml(b)
  })
  html += '</div>\n'
  
  if (template.value) {
    template.value.content_html = html
  }
}

const compileBlockToHtml = (b) => {
  const styles = Object.entries(b.style || {})
    .map(([k, v]) => `${k.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${v}`)
    .join('; ')
  
  let blockHtml = `<div id="${b.id}" style="${styles}">\n`
  
  if (b.type === 'text' || b.type === 'divider') {
    blockHtml += `  ${b.content || ''}\n`
  } else if (b.type === 'spacer') {
    blockHtml += `  <div style="height: ${b.height || 20}px;"></div>\n`
  } else if (b.type === 'image') {
    if (b.imageUrl) {
      blockHtml += `  <img src="${b.imageUrl}" style="max-height: 80px; max-width: 100%;" alt="Image">\n`
    } else {
      blockHtml += `  {{${b.content || 'hotel.logo'}}}\n`
    }
  } else if (b.type === 'table') {
    blockHtml += '  <table style="width: 100%; border-collapse: collapse;">\n'
    blockHtml += '    <thead>\n      <tr>\n'
    b.columns.forEach(col => {
      blockHtml += `        <th style="border-bottom: 2px solid #cbd5e1; padding: 6px 8px; font-weight: bold; width: ${col.width || 'auto'};">${col.header}</th>\n`
    })
    blockHtml += '      </tr>\n    </thead>\n'
    blockHtml += '    <tbody>\n'
    
    // Add detail loop marker
    blockHtml += `      <tr class="pms-detail-row" data-source="${b.dataSource}">\n`
    b.columns.forEach(col => {
      blockHtml += `        <td style="border-bottom: 1px solid #e2e8f0; padding: 6px 8px;">{{${col.value}}}</td>\n`
    })
    blockHtml += '      </tr>\n'
    
    blockHtml += '    </tbody>\n'
    blockHtml += '  </table>\n'
  } else if (b.type === 'columns') {
    blockHtml += '  <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">\n'
    blockHtml += '    <tr style="border: none;">\n'
    b.columns.forEach(col => {
      blockHtml += `      <td style="width: ${col.width || '50%'}; border: none; padding: 0; vertical-align: top;">\n`
      if (col.blocks) {
        col.blocks.forEach(subBlock => {
          blockHtml += compileBlockToHtml(subBlock)
        })
      }
      blockHtml += '      </td>\n'
    })
    blockHtml += '    </tr>\n'
    blockHtml += '  </table>\n'
  }
  
  blockHtml += '</div>\n'
  return blockHtml
}

// Quick Save Draft (no version comment required, keeps user in sync)
const saveTemplateDraft = async () => {
  if (!template.value) return
  compileHtml()
  try {
    await http.put(`/templates/${props.templateId}`, {
      group: template.value.group,
      name: template.value.name,
      report: template.value.report,
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10,
      content_json: blocks.value,
      content_html: template.value.content_html,
      css: template.value.css || '',
      note: 'Bản nháp tự động'
    })
  } catch (err) {
    console.error('Lỗi lưu nháp:', err)
  }
}

// Final Save (with version notes)
const saveTemplateWithVersion = async () => {
  if (!template.value) return
  saving.value = true
  compileHtml()
  try {
    const res = await http.put(`/templates/${props.templateId}`, {
      group: template.value.group,
      name: template.value.name,
      report: template.value.report,
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10,
      content_json: blocks.value,
      content_html: template.value.content_html,
      css: template.value.css || '',
      note: note.value || 'Cập nhật mẫu biểu thiết kế trực quan'
    })
    
    if (res.data && res.data.success) {
      uiStore.showToast('Lưu biểu mẫu và tạo phiên bản thành công!', 'success')
      showSaveModal.value = false
      note.value = ''
      await loadTemplate()
      await loadVersions()
      emit('saved')
    }
  } catch (err) {
    console.error('Lỗi khi lưu biểu mẫu:', err)
    let errorMsg = 'Không thể lưu biểu mẫu. Vui lòng kiểm tra lại.'
    if (err.response && err.response.data) {
      if (err.response.data.errors) {
        errorMsg = Object.values(err.response.data.errors).flat().join(', ')
      } else if (err.response.data.message) {
        errorMsg = err.response.data.message
      }
    }
    uiStore.showToast(errorMsg, 'error')
  } finally {
    saving.value = false
  }
}

// Rollback version
const rollbackToVersion = async (versionId) => {
  if (!confirm('Bạn có chắc chắn muốn khôi phục về phiên bản này không? Mọi thay đổi chưa lưu hiện tại sẽ bị ghi đè.')) {
    return
  }
  
  loading.value = true
  try {
    const res = await http.post(`/templates/${props.templateId}/rollback`, {
      version_id: versionId
    })
    if (res.data && res.data.success) {
      uiStore.showToast('Khôi phục phiên bản thành công!', 'success')
      await loadTemplate()
      await loadVersions()
      if (activeTab.value === 'design') {
        // Force refresh design UI
      }
    }
  } catch (err) {
    console.error('Lỗi khi rollback phiên bản:', err)
    uiStore.showToast('Không thể khôi phục phiên bản', 'error')
  } finally {
    loading.value = false
  }
}

// Watchers
watch(() => props.isOpen, (newVal) => {
  if (newVal && props.templateId) {
    activeTab.value = 'design'
    loadTemplate()
    loadVersions()
  }
})

watch(activeTab, (newVal) => {
  if (newVal === 'preview') {
    loadPreview()
  }
})

// Trigger close
const handleClose = () => {
  emit('close')
}

// Select band helper
const selectBand = (band) => {
  selectedBand.value = band
  // Select first block in this band if exists
  if (blocks.value[band].length > 0) {
    selectedBlockId.value = blocks.value[band][0].id
  } else {
    selectedBlockId.value = null
  }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/60 z-50 flex items-center justify-center p-4 backdrop-blur-xs">
    <div class="bg-white rounded-2xl w-full h-[95vh] flex flex-col shadow-2xl border border-slate-200 overflow-hidden max-w-[96vw]">
      <!-- 1. Header Bar of Editor -->
      <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center shrink-0">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl bg-sky-600 flex items-center justify-center text-white font-extrabold shadow-sm">
            DEV
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-800 flex items-center gap-2" v-if="template">
              {{ template.name }} 
              <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 text-[10px] font-black uppercase tracking-wider">
                v{{ template.version }}
              </span>
            </h2>
            <p class="text-xs text-slate-400" v-if="template">Nhóm: {{ template.group }}</p>
          </div>
        </div>

        <!-- Middle Page Layout configurations -->
        <div class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-xl shadow-xs" v-if="template">
          <!-- Page size -->
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Khổ giấy:</span>
            <select v-model="template.page_size" class="text-xs border border-slate-200 rounded-lg px-2 py-1 font-semibold focus:outline-sky-500">
              <option value="A4">A4</option>
              <option value="A5">A5</option>
              <option value="Letter">Letter</option>
            </select>
          </div>
          <!-- Orientation -->
          <div class="flex items-center gap-1.5 border-l border-slate-200 pl-3">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Chiều:</span>
            <select v-model="template.page_orientation" class="text-xs border border-slate-200 rounded-lg px-2 py-1 font-semibold focus:outline-sky-500">
              <option value="portrait">Dọc (Portrait)</option>
              <option value="landscape">Ngang (Landscape)</option>
            </select>
          </div>
          <!-- Margins (mm) -->
          <div class="flex items-center gap-1 pl-3 border-l border-slate-200">
            <span class="text-[10px] font-bold text-slate-400 uppercase">Lề (mm):</span>
            <div class="flex gap-1 items-center">
              <input type="number" v-model.number="template.margin_top" placeholder="Top" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Trên" />
              <input type="number" v-model.number="template.margin_bottom" placeholder="Bot" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Dưới" />
              <input type="number" v-model.number="template.margin_left" placeholder="Left" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Trái" />
              <input type="number" v-model.number="template.margin_right" placeholder="Right" class="w-10 text-center text-xs border border-slate-200 rounded-lg py-1 font-semibold" title="Lề Phải" />
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex items-center gap-2">
          <button @click="showSaveModal = true" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-xl shadow-sm hover:shadow-md transition-all cursor-pointer flex items-center gap-1.5 uppercase">
            <Save class="w-4 h-4" /> Lưu phiên bản
          </button>
          <button @click="handleClose" class="w-9 h-9 rounded-xl hover:bg-slate-200 text-slate-500 flex items-center justify-center cursor-pointer transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <!-- 2. Tabs Bar (Design, Preview, CSS, Versions) -->
      <div class="border-b border-slate-200 px-6 bg-white shrink-0 flex items-center justify-between">
        <div class="flex gap-1.5 py-2">
          <button @click="activeTab = 'design'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'design' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <Layers class="w-4 h-4" /> Thiết kế Banded
          </button>
          <button @click="activeTab = 'preview'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'preview' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <FileText class="w-4 h-4" /> Xem trước in (PDF/Web)
          </button>
          <button @click="activeTab = 'css'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'css' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <Settings class="w-4 h-4" /> Custom CSS Styles
          </button>
          <button @click="activeTab = 'versions'" 
            class="px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1.5 transition-colors border-none cursor-pointer"
            :class="activeTab === 'versions' ? 'bg-sky-50 text-sky-700' : 'text-slate-500 hover:bg-slate-50'">
            <History class="w-4 h-4" /> Lịch sử phiên bản
          </button>
        </div>
        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">DevExpress Report Mode</span>
      </div>

      <!-- 3. Loading Spinner -->
      <div v-if="loading" class="flex-1 flex flex-col items-center justify-center gap-3">
        <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-sky-600"></div>
        <p class="text-xs text-slate-400 font-semibold">Đang chuẩn bị trình thiết kế...</p>
      </div>

      <!-- 4. Content Area -->
      <div v-else class="flex-1 overflow-hidden flex items-stretch">
        
        <!-- ================== TAB 1: DESIGNER ================== -->
        <template v-if="activeTab === 'design'">
          <!-- Column 1: Field List (Left Panel) -->
          <div class="w-1/4 bg-slate-50 border-r border-slate-200 p-4 overflow-y-auto flex flex-col gap-4 select-none shrink-0">
            
            <!-- TOOLBOX -->
            <div class="flex flex-col gap-2 bg-white rounded-xl p-3 border border-slate-200 shadow-3xs">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-100 block">Hộp Công Cụ Blocks</span>
              <div class="grid grid-cols-2 gap-1.5 pt-2">
                <button @click="addBlock('text')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">T</span> Văn Bản
                </button>
                <button @click="addBlock('image')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">📷</span> Hình Ảnh
                </button>
                <button @click="addBlock('table')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all col-span-2">
                  <span class="text-base mb-0.5">田</span> Bảng Chi Tiết (Detail Table)
                </button>
                <button @click="addBlock('divider')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">─</span> Đường Kẻ
                </button>
                <button @click="addBlock('spacer')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all">
                  <span class="text-base mb-0.5">↕</span> Khoảng Trống
                </button>
                <button @click="addBlock('columns')" class="flex flex-col items-center justify-center p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all col-span-2">
                  <span class="text-base mb-0.5">◫</span> Bố Cục Cột (Columns Layout)
                </button>
              </div>
            </div>

            <!-- FIELD LIST (DATA VARIABLES) -->
            <div class="flex flex-col gap-2 flex-1">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-200 block">Danh Sách Trường Dữ Liệu (Field List)</span>
              <p class="text-[10px] text-slate-400 italic">Chọn 1 khối rồi click biến để chèn nhanh</p>
              
              <!-- Accordion groups -->
              <div v-for="(fields, key) in fieldList" :key="key" class="border border-slate-200 rounded-lg bg-white overflow-hidden shadow-3xs">
                <button @click="openCategories[key] = !openCategories[key]" class="w-full flex justify-between items-center px-3 py-2 bg-slate-50/75 border-none font-bold text-xs text-slate-700 hover:bg-slate-50 cursor-pointer">
                  <span class="flex items-center gap-1.5 capitalize">
                    📁 {{ key === 'hotel' ? 'Khách Sạn' : key === 'customer' ? 'Khách Hàng' : key === 'booking' ? 'Đặt Phòng' : key === 'room' ? 'Hạng & Số Phòng' : key === 'payment' ? 'Thanh Toán' : key === 'registration' ? 'Phiếu Đăng Ký' : 'Bảng Dữ Liệu Lặp' }}
                  </span>
                  <ChevronRight class="w-3.5 h-3.5 transition-transform" :class="openCategories[key] ? 'rotate-90' : ''" />
                </button>
                
                <div v-if="openCategories[key]" class="p-1 border-t border-slate-100 flex flex-col gap-0.5 bg-white">
                  <button v-for="field in fields" :key="field.value" @click="insertVariable(field.value)"
                    class="w-full text-left px-2.5 py-1.5 rounded-md hover:bg-sky-50 text-slate-600 hover:text-sky-700 text-xs font-semibold border-none bg-transparent flex justify-between items-center cursor-pointer transition-colors group">
                    <span>{{ field.label }}</span>
                    <span class="text-[9px] font-mono text-slate-400 group-hover:text-sky-500 font-bold">[{{ field.value }}]</span>
                  </button>
                </div>
              </div>
            </div>

          </div>

          <!-- Column 2: Banded Design Canvas (Middle Panel) -->
          <div class="flex-1 bg-slate-100 p-6 overflow-y-auto flex flex-col items-center">
            
            <!-- Band selector controls -->
            <div class="flex bg-white p-1 border border-slate-200 rounded-xl shadow-xs mb-4 gap-1 select-none">
              <button @click="selectBand('header')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'header' ? 'bg-amber-100 text-amber-800' : 'text-slate-500 hover:bg-slate-50'">
                Report Header Band
              </button>
              <button @click="selectBand('detail')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'detail' ? 'bg-sky-100 text-sky-800' : 'text-slate-500 hover:bg-slate-50'">
                Detail Band (Bảng chi tiết)
              </button>
              <button @click="selectBand('footer')" 
                class="px-4 py-2 rounded-lg font-bold text-xs border-none cursor-pointer transition-all flex items-center gap-1.5"
                :class="selectedBand === 'footer' ? 'bg-emerald-100 text-emerald-800' : 'text-slate-500 hover:bg-slate-50'">
                Report Footer Band
              </button>
            </div>

            <!-- Page Canvas Layout Representation -->
            <div class="bg-white shadow-lg border border-slate-300 w-[210mm] min-h-[297mm] p-6 relative flex flex-col"
              :style="{
                width: template?.page_size === 'A5' ? '148mm' : '210mm',
                minHeight: template?.page_size === 'A5' ? '210mm' : '297mm',
                paddingTop: `${template?.margin_top || 10}mm`,
                paddingBottom: `${template?.margin_bottom || 10}mm`,
                paddingLeft: `${template?.margin_left || 10}mm`,
                paddingRight: `${template?.margin_right || 10}mm`
              }">
              
              <!-- SECTION 1: HEADER BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mb-4 transition-all relative group/band"
                :class="[
                  selectedBand === 'header' ? 'border-amber-400 bg-amber-50/20' : 'border-slate-200',
                  blocks.header.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'header'">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-amber-100 text-amber-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Report Header Band
                </span>

                <div v-if="blocks.header.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Header. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Header -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.header" :key="b.id"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'header'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white'">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type }}
                    </span>

                    <!-- Block drag/edit overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.header.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('header', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Block Visual Content -->
                    <div v-if="b.type === 'text'">
                      <textarea v-if="selectedBlockId === b.id" 
                        v-model="b.content" 
                        rows="3" 
                        class="w-full text-xs border border-sky-300 rounded-lg p-2 focus:outline-none focus:ring-1 focus:ring-sky-500 text-slate-700 font-semibold font-mono"
                        placeholder="Nhập nội dung..."></textarea>
                      <div v-else class="min-h-[20px] text-slate-700 leading-relaxed font-semibold" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="text-center py-2 bg-slate-50 border border-dashed border-slate-200 rounded flex justify-center items-center overflow-hidden min-h-[40px]">
                      <img v-if="b.imageUrl" :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                      <span v-else class="font-bold text-slate-500 font-mono text-xs">[Ảnh liên kết: {{ b.content }}]</span>
                    </div>
                    
                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'header'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'">
                            <textarea v-if="selectedBlockId === subBlock.id" v-model="subBlock.content" rows="2" class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 font-semibold font-mono" placeholder="Nội dung..."></textarea>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px]" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="text-center py-1 bg-slate-50 border border-dashed border-slate-200 rounded flex justify-center items-center min-h-[30px]">
                            <img v-if="subBlock.imageUrl" :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                            <span v-else class="font-bold text-slate-500 font-mono text-[9px]">[Ảnh: {{ subBlock.content }}]</span>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- SECTION 2: DETAIL BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mb-4 transition-all relative group/band"
                :class="[
                  selectedBand === 'detail' ? 'border-sky-400 bg-sky-50/20' : 'border-slate-200',
                  blocks.detail.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'detail'">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-sky-100 text-sky-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Detail Band (Bảng lặp động)
                </span>

                <div v-if="blocks.detail.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Detail. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Detail -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.detail" :key="b.id"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'detail'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white'">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type === 'table' ? 'Detail Table' : b.type }}
                    </span>

                    <!-- Overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.detail.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('detail', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Table Block Rendering -->
                    <div v-if="b.type === 'table'" class="w-full overflow-x-auto">
                      <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                        🔗 Bảng lặp nguồn: {{ b.dataSource }}
                      </p>
                      <table class="w-full text-xs text-left border-collapse border border-slate-200">
                        <thead>
                          <tr class="bg-slate-50 font-bold border-b border-slate-200">
                            <th v-for="col in b.columns" :key="col.header" class="p-2 border-r border-slate-200" :style="{ width: col.width }">
                              {{ col.header }}
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr class="bg-white border-b border-slate-100">
                            <td v-for="col in b.columns" :key="col.value" class="p-2 border-r border-slate-200 font-mono text-[10px] text-slate-400">
                              {{ col.value }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    
                    <!-- Other Block Types -->
                    <div v-if="b.type === 'text'">
                      <textarea v-if="selectedBlockId === b.id" 
                        v-model="b.content" 
                        rows="3" 
                        class="w-full text-xs border border-sky-300 rounded-lg p-2 focus:outline-none focus:ring-1 focus:ring-sky-500 text-slate-700 font-semibold font-mono"
                        placeholder="Nhập nội dung..."></textarea>
                      <div v-else class="min-h-[20px] text-slate-700 leading-relaxed font-semibold" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>

                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'detail'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'">
                            <textarea v-if="selectedBlockId === subBlock.id" v-model="subBlock.content" rows="2" class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 font-semibold font-mono" placeholder="Nội dung..."></textarea>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px]" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="text-center py-1 bg-slate-50 border border-dashed border-slate-200 rounded flex justify-center items-center min-h-[30px]">
                            <img v-if="subBlock.imageUrl" :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                            <span v-else class="font-bold text-slate-500 font-mono text-[9px]">[Ảnh: {{ subBlock.content }}]</span>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- SECTION 3: FOOTER BAND -->
              <div class="border-2 border-dashed rounded-lg p-3 mt-auto transition-all relative group/band"
                :class="[
                  selectedBand === 'footer' ? 'border-emerald-400 bg-emerald-50/20' : 'border-slate-200',
                  blocks.footer.length === 0 ? 'min-h-[100px] flex items-center justify-center' : ''
                ]"
                @click="selectedBand = 'footer'">
                
                <!-- Band Title -->
                <span class="absolute top-0 right-2 -translate-y-1/2 bg-emerald-100 text-emerald-800 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full select-none">
                  Report Footer Band
                </span>

                <div v-if="blocks.footer.length === 0" class="text-center text-slate-400 text-xs italic">
                  Chưa có khối nào ở vùng Footer. Chọn vùng này rồi bấm hộp công cụ để thêm.
                </div>
                
                <!-- Blocks inside Footer -->
                <div v-else class="flex flex-col gap-2">
                  <div v-for="(b, idx) in blocks.footer" :key="b.id"
                    @click.stop="selectedBlockId = b.id; selectedBand = 'footer'"
                    class="border rounded-lg p-2.5 cursor-pointer relative hover:shadow-2xs group/block"
                    :class="selectedBlockId === b.id ? 'border-sky-500 bg-sky-50/40 ring-1 ring-sky-300' : 'border-slate-200 bg-white'">
                    
                    <!-- Block Type Tag -->
                    <span class="absolute -top-1.5 left-2 bg-slate-100 text-slate-500 text-[8px] font-black uppercase px-1.5 rounded-md border border-slate-200">
                      {{ b.type }}
                    </span>

                    <!-- Overlay handles -->
                    <div class="absolute right-2 top-2 hidden group-hover/block:flex gap-1 bg-white/80 p-0.5 border border-slate-200 rounded-md shadow-xs z-10">
                      <button @click.stop="moveBlock(idx, 'up')" :disabled="idx === 0" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="moveBlock(idx, 'down')" :disabled="idx === blocks.footer.length - 1" class="w-6 h-6 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button @click.stop="deleteBlock('footer', b.id)" class="w-6 h-6 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Block Visual Content -->
                    <div v-if="b.type === 'text'">
                      <textarea v-if="selectedBlockId === b.id" 
                        v-model="b.content" 
                        rows="3" 
                        class="w-full text-xs border border-sky-300 rounded-lg p-2 focus:outline-none focus:ring-1 focus:ring-sky-500 text-slate-700 font-semibold font-mono"
                        placeholder="Nhập nội dung..."></textarea>
                      <div v-else class="min-h-[20px] text-slate-700 leading-relaxed font-semibold" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="text-center py-2 bg-slate-50 border border-dashed border-slate-200 rounded flex justify-center items-center overflow-hidden min-h-[40px]">
                      <img v-if="b.imageUrl" :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                      <span v-else class="font-bold text-slate-500 font-mono text-xs">[Ảnh liên kết: {{ b.content }}]</span>
                    </div>

                    <!-- Columns layout block -->
                    <div v-else-if="b.type === 'columns'" class="w-full flex gap-3 select-none">
                      <div v-for="(col, colIdx) in b.columns" :key="colIdx"
                        :style="{ width: col.width || '50%' }"
                        class="border border-dashed border-slate-200 bg-slate-50/20 rounded-lg p-2 min-h-[60px] flex flex-col gap-2 relative">
                        
                        <!-- Column label -->
                        <span class="text-[8px] font-bold text-slate-400 self-end">Cột {{ colIdx + 1 }} ({{ col.width }})</span>
                        
                        <!-- Subblocks list -->
                        <div v-for="(subBlock, subIdx) in col.blocks" :key="subBlock.id"
                          @click.stop="selectedBlockId = subBlock.id; selectedBand = 'footer'"
                          class="border rounded p-1.5 cursor-pointer relative group/subblock text-left"
                          :class="selectedBlockId === subBlock.id ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-300' : 'border-slate-100 bg-white'">
                          
                          <span class="text-[7px] font-black uppercase text-slate-400 absolute -top-1.5 left-1 bg-slate-50 px-1 border border-slate-200 rounded">
                            {{ subBlock.type }}
                          </span>
                          
                          <!-- Handles for subblock -->
                          <div class="absolute right-1 top-1 hidden group-hover/subblock:flex gap-0.5 bg-white/90 p-0.5 border border-slate-200 rounded shadow-3xs z-10">
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'up')" :disabled="subIdx === 0" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowUp class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="moveSubBlock(b, colIdx, subIdx, 'down')" :disabled="subIdx === col.blocks.length - 1" class="w-4 h-4 hover:bg-slate-100 rounded flex items-center justify-center text-slate-500 border-none bg-transparent cursor-pointer disabled:opacity-30">
                              <ArrowDown class="w-2.5 h-2.5" />
                            </button>
                            <button @click.stop="deleteSubBlock(b, colIdx, subBlock.id)" class="w-4 h-4 hover:bg-red-50 hover:text-red-600 rounded flex items-center justify-center text-slate-400 border-none bg-transparent cursor-pointer">
                              <Trash2 class="w-2.5 h-2.5" />
                            </button>
                          </div>
                          
                          <!-- Content for subblock -->
                          <div v-if="subBlock.type === 'text'">
                            <textarea v-if="selectedBlockId === subBlock.id" v-model="subBlock.content" rows="2" class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 font-semibold font-mono" placeholder="Nội dung..."></textarea>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px]" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="text-center py-1 bg-slate-50 border border-dashed border-slate-200 rounded flex justify-center items-center min-h-[30px]">
                            <img v-if="subBlock.imageUrl" :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                            <span v-else class="font-bold text-slate-500 font-mono text-[9px]">[Ảnh: {{ subBlock.content }}]</span>
                          </div>
                          <div v-else-if="subBlock.type === 'spacer'" class="border border-dashed border-slate-100 bg-slate-50/50 rounded flex items-center justify-center text-[9px] text-slate-400 italic" :style="{ height: `${subBlock.height || 15}px` }">
                            Khoảng trống {{ subBlock.height || 15 }}px
                          </div>
                        </div>
                        
                        <!-- Quick Add buttons inside Column -->
                        <div class="mt-auto pt-1.5 border-t border-slate-100 flex justify-center gap-1 shrink-0">
                          <button @click.stop="addSubBlock(b, colIdx, 'text')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + T
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'image')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Ảnh
                          </button>
                          <button @click.stop="addSubBlock(b, colIdx, 'spacer')" class="px-1.5 py-0.5 bg-slate-100 hover:bg-sky-50 text-[9px] font-bold text-slate-600 hover:text-sky-700 rounded border-none cursor-pointer">
                            + Trống
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

            </div>
          </div>

          <!-- Column 3: Properties Inspector Sidebar (Right Panel) -->
          <div class="w-1/4 bg-slate-50 border-l border-slate-200 p-4 overflow-y-auto flex flex-col gap-4 select-none shrink-0">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-200 block">Bảng Thuộc Tính (Properties)</span>
            
            <div v-if="!selectedBlock" class="text-center py-8 text-slate-400 text-xs italic">
              Chọn một Block trên khu vực thiết kế để tùy chỉnh thuộc tính chi tiết.
            </div>

            <!-- Block Settings Forms -->
            <div v-else class="flex flex-col gap-4">
              <!-- Type Info -->
              <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase">Khối Đang Chọn:</span>
                <p class="text-xs font-black text-slate-700 mt-0.5 capitalize">{{ selectedBlock.type }} ({{ selectedBlock.id }})</p>
              </div>

              <!-- Alignment & Font properties -->
              <div class="flex flex-col gap-2.5 bg-white p-3 border border-slate-200 rounded-xl shadow-3xs">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Định dạng kiểu chữ (Styles)</span>
                
                <!-- Align text -->
                <div class="flex items-center justify-between mt-1.5">
                  <span class="text-xs text-slate-500">Căn lề:</span>
                  <div class="flex bg-slate-100 p-0.5 rounded-lg">
                    <button @click="selectedBlock.style.textAlign = 'left'" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'left' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignLeft class="w-3.5 h-3.5" />
                    </button>
                    <button @click="selectedBlock.style.textAlign = 'center'" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'center' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignCenter class="w-3.5 h-3.5" />
                    </button>
                    <button @click="selectedBlock.style.textAlign = 'right'" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'right' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignRight class="w-3.5 h-3.5" />
                    </button>
                    <button @click="selectedBlock.style.textAlign = 'justify'" class="p-1.5 rounded-md hover:bg-white border-none cursor-pointer text-slate-600" :class="selectedBlock.style.textAlign === 'justify' ? 'bg-white shadow-3xs text-sky-600' : ''">
                      <AlignJustify class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Font size slider -->
                <div class="flex flex-col gap-1 mt-2">
                  <div class="flex justify-between items-center text-xs text-slate-500">
                    <span>Cỡ chữ:</span>
                    <span class="font-bold text-slate-700">{{ selectedBlock.style.fontSize }}</span>
                  </div>
                  <input type="range" min="10" max="36" step="1" 
                    :value="parseInt(selectedBlock.style.fontSize)" 
                    @input="selectedBlock.style.fontSize = `${$event.target.value}px`"
                    class="w-full accent-sky-600" />
                </div>

                <!-- Color & Font-Weight -->
                <div class="grid grid-cols-2 gap-2 mt-2">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Màu chữ:</span>
                    <input type="color" v-model="selectedBlock.style.color" class="w-full h-8 border border-slate-200 rounded-lg cursor-pointer" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Đậm:</span>
                    <button @click="selectedBlock.style.fontWeight = selectedBlock.style.fontWeight === 'bold' ? 'normal' : 'bold'"
                      class="h-8 rounded-lg border font-bold text-xs flex items-center justify-center cursor-pointer transition-colors"
                      :class="selectedBlock.style.fontWeight === 'bold' ? 'bg-sky-50 border-sky-300 text-sky-700' : 'bg-slate-50 border-slate-200 text-slate-600'">
                      <Bold class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Paddings & Margins -->
                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Top:</span>
                    <input type="text" v-model="selectedBlock.style.paddingTop" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.paddingBottom" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Top:</span>
                    <input type="text" v-model="selectedBlock.style.marginTop" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.marginBottom" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                </div>

              </div>

              <!-- CONTENT EDITORS BY BLOCK TYPE -->
              
              <!-- 1. Text content editor -->
              <div v-if="selectedBlock.type === 'text'" class="flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Nội dung HTML Văn Bản:</span>
                <textarea v-model="selectedBlock.content" rows="10" 
                  class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-mono leading-relaxed" 
                  placeholder="Viết nội dung văn bản (hỗ trợ các thẻ <b>, <i>, <p>...)"></textarea>
              </div>

              <!-- 2. Spacer height editor -->
              <div v-else-if="selectedBlock.type === 'spacer'" class="flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Chiều cao khoảng trống (px):</span>
                <input type="number" v-model.number="selectedBlock.height" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-bold" />
              </div>

              <!-- 3. Image block settings -->
              <div v-else-if="selectedBlock.type === 'image'" class="flex flex-col gap-3">
                <!-- 3a. Upload new logo/image file -->
                <div class="flex flex-col gap-1.5">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Tải logo/ảnh lên (Upload):</span>
                  <div class="flex items-center gap-2">
                    <label class="px-3.5 py-2 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-black rounded-lg cursor-pointer transition-colors border border-sky-200 flex items-center gap-1.5 w-fit">
                      📷 Chọn tệp ảnh
                      <input type="file" @change="handleImageUpload" accept="image/*" class="hidden" />
                    </label>
                    <button v-if="selectedBlock.imageUrl" @click="selectedBlock.imageUrl = ''; compileHtml()" 
                      class="px-2.5 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-lg cursor-pointer transition-colors">
                      Xóa ảnh
                    </button>
                    <span v-if="uploadingImage" class="text-xs text-slate-400 font-semibold animate-pulse">Đang tải...</span>
                  </div>
                </div>

                <!-- 3b. Direct path input -->
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Hoặc đường dẫn ảnh trực tiếp:</span>
                  <input type="text" v-model="selectedBlock.imageUrl" @input="compileHtml" placeholder="/uploads/... or http://..." class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-mono" />
                </div>

                <!-- 3c. Dynamic binding variable selector -->
                <div class="flex flex-col gap-1" v-if="!selectedBlock.imageUrl">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Hoặc liên kết biến dữ liệu ảnh:</span>
                  <input type="text" v-model="selectedBlock.content" placeholder="hotel.logo" class="w-full text-xs border border-slate-200 rounded-lg p-2 bg-slate-50 font-mono font-bold" readonly />
                </div>
              </div>

              <!-- 4. Table Block column and fields mapping configuration -->
              <div v-else-if="selectedBlock.type === 'table'" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Nguồn dữ liệu bảng lặp:</span>
                  <select v-model="selectedBlock.dataSource" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="booking.services">Lặp dịch vụ (booking.services)</option>
                    <option value="booking.rooms">Lặp danh sách phòng (booking.rooms)</option>
                    <option value="booking.payments">Lặp thanh toán (booking.payments)</option>
                  </select>
                </div>

                <!-- Columns manager -->
                <div class="flex flex-col gap-2">
                  <div class="flex justify-between items-center pb-1 border-b border-slate-200">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Cột của bảng</span>
                    <button @click="selectedBlock.columns.push({ header: 'Mới', value: '', width: 'auto' })" 
                      class="px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-600 text-[10px] font-black border-none rounded-lg cursor-pointer">
                      Thêm Cột
                    </button>
                  </div>
                  
                  <!-- Columns list items -->
                  <div class="flex flex-col gap-2.5 max-h-[250px] overflow-y-auto pr-1">
                    <div v-for="(col, cIdx) in selectedBlock.columns" :key="cIdx" class="border border-slate-200 rounded-lg p-2.5 bg-white shadow-3xs flex flex-col gap-1.5 relative group/col">
                      <!-- Delete Column -->
                      <button @click="selectedBlock.columns.splice(cIdx, 1)" class="absolute top-2 right-2 text-slate-400 hover:text-red-600 border-none bg-transparent cursor-pointer">
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                      
                      <!-- Col Header -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Tiêu đề cột:</span>
                        <input type="text" v-model="col.header" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 font-bold" />
                      </div>
                      
                      <!-- Col Value binding selector -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Biến dữ liệu ánh xạ:</span>
                        <select v-model="col.value" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 font-mono">
                          <option v-for="f in getListFields(selectedBlock.dataSource)" :key="f.value" :value="f.value">
                            {{ f.label }} [{{ f.value }}]
                          </option>
                        </select>
                      </div>

                      <!-- Col Width -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Độ rộng (%):</span>
                        <input type="text" v-model="col.width" class="text-[11px] border border-slate-200 rounded px-1.5 py-0.5 font-mono" placeholder="20% hoặc auto" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 5. Columns Layout Block configuration -->
              <div v-else-if="selectedBlock.type === 'columns'" class="flex flex-col gap-3">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Bố cục chia cột (Layout):</span>
                <div class="grid grid-cols-2 gap-2">
                  <button @click="setColumnWidths(selectedBlock, '50-50')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '50%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    50% - 50%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '30-70')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '30%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    30% - 70%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '70-30')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 2 && selectedBlock.columns[0].width === '70%' ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    70% - 30%
                  </button>
                  <button @click="setColumnWidths(selectedBlock, '33-33-33')" 
                    class="py-2 border rounded-lg text-xs font-bold transition-all cursor-pointer animate-none bg-white border-slate-200 text-slate-600 hover:bg-slate-50"
                    :class="selectedBlock.columns.length === 3 ? '!bg-sky-50 !border-sky-300 !text-sky-700' : ''">
                    33% - 33% - 33%
                  </button>
                </div>
              </div>

            </div>
          </div>
        </template>

        <!-- ================== TAB 2: LIVE PREVIEW ================== -->
        <template v-else-if="activeTab === 'preview'">
          <div class="flex-1 bg-slate-200 p-6 overflow-y-auto flex flex-col items-center">
            <!-- Iframe container with print simulation borders -->
            <div class="flex justify-between items-center w-[210mm] max-w-full mb-3 shrink-0">
              <span class="text-xs text-slate-500 font-bold">Xem trước thực tế (A4/A5 preview)</span>
              <button @click="loadPreview" class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold flex items-center gap-1 cursor-pointer transition-colors shadow-3xs">
                <RefreshCw class="w-3.5 h-3.5" :class="loadingPreview ? 'animate-spin' : ''" /> Làm mới
              </button>
            </div>
            
            <div v-if="loadingPreview" class="bg-white shadow-lg border border-slate-300 w-[210mm] h-[297mm] flex flex-col items-center justify-center gap-3">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
              <p class="text-xs text-slate-400 italic">Đang biên dịch và render dữ liệu giả lập từ hệ thống...</p>
            </div>
            
            <iframe v-else-if="previewHtml" :srcdoc="previewHtml" 
              class="bg-white shadow-lg border border-slate-300 w-[210mm] min-h-[297mm] rounded-sm transition-all"
              :style="{
                width: template?.page_size === 'A5' ? '148mm' : '210mm',
                minHeight: template?.page_size === 'A5' ? '210mm' : '297mm'
              }"></iframe>
          </div>
        </template>

        <!-- ================== TAB 3: CUSTOM CSS ================== -->
        <template v-else-if="activeTab === 'css'">
          <div class="flex-1 bg-slate-50 p-6 flex flex-col gap-4">
            <div class="flex justify-between items-center shrink-0">
              <div>
                <h3 class="text-sm font-bold text-slate-700 uppercase">Mã CSS Tùy Biến (Custom CSS Styles)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Các class định nghĩa ở đây sẽ được nạp và áp dụng trực tiếp lên bản in. Ví dụ: <code>h1 { font-family: monospace; }</code></p>
              </div>
            </div>
            <textarea v-if="template" v-model="template.css" rows="20"
              class="flex-1 text-xs border border-slate-200 rounded-2xl p-4 focus:outline-sky-500 font-mono leading-relaxed bg-white shadow-2xs resize-none" 
              placeholder="/* Nhập CSS của bạn ở đây... */&#10;h1 {&#10;    color: #0284c7;&#10;    font-size: 24px;&#10;}&#10;table {&#10;    margin-top: 15px;&#10;}"></textarea>
          </div>
        </template>

        <!-- ================== TAB 4: VERSIONS ================== -->
        <template v-else-if="activeTab === 'versions'">
          <div class="flex-1 bg-slate-50 p-6 overflow-y-auto flex flex-col gap-4">
            <h3 class="text-sm font-bold text-slate-700 uppercase pb-2 border-b border-slate-200">Lịch sử thay đổi mẫu biểu</h3>
            
            <div class="overflow-hidden border border-slate-200 rounded-xl bg-white shadow-2xs">
              <table class="w-full text-sm text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                    <th class="p-3">Phiên bản</th>
                    <th class="p-3">Ghi chú thay đổi</th>
                    <th class="p-3">Người cập nhật</th>
                    <th class="p-3">Thời gian tạo</th>
                    <th class="p-3 text-right">Khôi phục (Rollback)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="v in versions" :key="v.id" class="border-b border-slate-100 hover:bg-slate-50/50">
                    <td class="p-3 font-bold text-sky-600 font-mono">v{{ v.version }}</td>
                    <td class="p-3 text-slate-700 font-semibold">{{ v.note }}</td>
                    <td class="p-3 text-slate-500">{{ v.updater ? v.updater.name : 'Hệ thống' }}</td>
                    <td class="p-3 text-slate-400 font-semibold text-xs">{{ new Date(v.created_at).toLocaleString() }}</td>
                    <td class="p-3 text-right">
                      <button @click="rollbackToVersion(v.id)" 
                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-sky-50 border border-slate-200 hover:border-sky-300 text-slate-600 hover:text-sky-700 font-extrabold rounded-lg text-[10px] cursor-pointer transition-colors flex items-center gap-1.5 ml-auto uppercase shadow-3xs">
                        <RotateCcw class="w-3.5 h-3.5" /> Khôi phục
                      </button>
                    </td>
                  </tr>
                  <tr v-if="versions.length === 0">
                    <td colspan="5" class="p-6 text-center text-slate-400 italic">Chưa ghi nhận lịch sử phiên bản nào.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>

      </div>
    </div>
  </div>

  <!-- Save Overlay Dialog (Notes input for version control) -->
  <div v-if="showSaveModal" class="fixed inset-0 bg-slate-900/60 z-60 flex items-center justify-center p-4 backdrop-blur-xs">
    <div class="bg-white rounded-2xl w-full max-w-md p-6 border border-slate-200 shadow-2xl flex flex-col gap-4 animate-scale-up">
      <div class="flex justify-between items-center pb-2 border-b border-slate-100">
        <h3 class="text-sm font-bold text-slate-800 uppercase flex items-center gap-1.5">
          <Save class="w-4 h-4 text-sky-600" /> Nhập ghi chú phiên bản
        </h3>
        <button @click="showSaveModal = false" class="text-slate-400 hover:text-slate-600 border-none bg-transparent cursor-pointer">
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="flex flex-col gap-1.5">
        <span class="text-[10px] font-bold text-slate-400 uppercase">Mô tả lý do thay đổi (Note):</span>
        <input type="text" v-model="note" placeholder="Ví dụ: Thay đổi cỡ chữ, định dạng lại bảng..." 
          class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-semibold"
          @keyup.enter="saveTemplateWithVersion" />
        <p class="text-[10px] text-slate-400 leading-normal">
          Nhập mô tả sẽ giúp đội ngũ kỹ thuật dễ dàng phân biệt và tìm lại lịch sử biểu mẫu khi cần rollback.
        </p>
      </div>

      <div class="flex gap-2 justify-end pt-2 border-t border-slate-100">
        <button @click="showSaveModal = false" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-black rounded-xl cursor-pointer">
          HỦY BỎ
        </button>
        <button @click="saveTemplateWithVersion" :disabled="saving" 
          class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-xl cursor-pointer flex items-center gap-1.5 disabled:opacity-50">
          <Check class="w-4 h-4" /> {{ saving ? 'ĐANG LƯU...' : 'XÁC NHẬN LƯU' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Scoped adjustments for smooth ranges */
input[type="range"] {
  height: 6px;
  border-radius: 4px;
}
.report-header-band, .report-detail-band, .report-footer-band {
  width: 100%;
}
</style>

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
const activeTextarea = ref(null)
const editingContent = ref('')
const editorMode = ref('visual') // 'visual' | 'code'

// Active cell editing states for static tables to prevent cursor jumps
const activeCell = ref(null)
const editingCellContent = ref('')

const onCellFocus = (cell) => {
  activeCell.value = cell
  editingCellContent.value = cell.content || ''
}

const onCellBlur = () => {
  activeCell.value = null
  editingCellContent.value = ''
  compileHtml()
}

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

// Render Preview — read current unsaved canvas blocks and styling live
const loadPreview = async () => {
  if (!template.value) return
  loadingPreview.value = true
  compileHtml() // Ensure latest blocks layout is compiled to content_html
  try {
    const res = await http.post(`/templates/${props.templateId}/preview`, {
      content_html: template.value.content_html,
      css: template.value.css || '',
      page_size: template.value.page_size || 'A4',
      page_orientation: template.value.page_orientation || 'portrait',
      margin_top: template.value.margin_top ?? 10,
      margin_bottom: template.value.margin_bottom ?? 10,
      margin_left: template.value.margin_left ?? 10,
      margin_right: template.value.margin_right ?? 10
    })
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
    subBlock.content = ''
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
  
  if (type === 'image') {
    setTimeout(() => {
      triggerCanvasImageUpload(subBlock)
    }, 150)
  }
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

const triggerCanvasImageUpload = (block) => {
  const input = document.getElementById('file_input_' + block.id)
  if (input) {
    input.click()
  }
}

const handleCanvasImageUpload = async (event, block) => {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('image', file)
  
  uiStore.showToast('Đang tải ảnh lên...', 'info')
  try {
    const res = await http.post('/templates/upload-image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (res.data && res.data.success) {
      block.imageUrl = res.data.url
      block.content = '' // Clear variables link when custom image is uploaded
      compileHtml()
      uiStore.showToast('Tải ảnh lên thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi upload ảnh:', err)
    uiStore.showToast('Không thể tải ảnh lên. Vui lòng thử lại.', 'error')
  } finally {
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
    newBlock.content = '' // bindings variable or direct image tag
    newBlock.imageUrl = ''
    newBlock.style.textAlign = 'center'
  } else if (type === 'divider') {
    newBlock.content = '<hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 10px 0;">'
  } else if (type === 'spacer') {
    newBlock.height = 20 // mm or px
  } else if (type === 'table') {
    newBlock.isNew = true
    newBlock.dataSource = 'booking.services'
    newBlock.tableType = 'dynamic'
    newBlock.rowsCount = 3
    newBlock.colsCount = 2
    newBlock.selectedFields = ['service.name', 'service.price', 'service.quantity', 'service.amount']
    newBlock.columns = []
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
  compileHtml()
  
  if (type === 'image') {
    setTimeout(() => {
      triggerCanvasImageUpload(newBlock)
    }, 150)
  }
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

const onTextareaFocus = (e) => {
  activeTextarea.value = e.target
}

const insertHTMLAtCursor = (html) => {
  let sel, range;
  if (window.getSelection) {
    sel = window.getSelection();
    if (sel.getRangeAt && sel.rangeCount) {
      range = sel.getRangeAt(0);
      range.deleteContents();
      
      const el = document.createElement("div");
      el.innerHTML = html;
      const frag = document.createDocumentFragment();
      let node, lastNode;
      while ((node = el.firstChild)) {
        lastNode = frag.appendChild(node);
      }
      range.insertNode(frag);
      
      if (lastNode) {
        range = range.cloneRange();
        range.setStartAfter(lastNode);
        range.collapse(true);
        sel.removeAllRanges();
        sel.addRange(range);
      }
    }
  }
}

const formatText = (command, value = null) => {
  document.execCommand(command, false, value)
  // Sync HTML to active contenteditable element by dispatching input event
  const activeEl = document.activeElement
  if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
    activeEl.dispatchEvent(new Event('input', { bubbles: true }))
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
  // Inline styled span tag showing as a beautiful blue pill (User-friendly Tag)
  const varHtml = `<span class="pms-variable" style="background-color: #f0f9ff; color: #0369a1; padding: 2px 6px; border-radius: 4px; border: 1px solid #bae6fd; font-family: monospace; font-size: 11px; margin: 0 2px; display: inline-block; user-select: all;" contenteditable="false" data-val="${value}">${placeholder}</span>`
  
  if (block.type === 'text') {
    const activeEl = document.activeElement
    if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
      insertHTMLAtCursor(varHtml)
      block.content = activeEl.innerHTML
    } else if (activeTextarea.value) {
      const textarea = activeTextarea.value
      const start = textarea.selectionStart
      const end = textarea.selectionEnd
      const text = block.content || ''
      block.content = text.substring(0, start) + placeholder + text.substring(end)
      setTimeout(() => {
        textarea.focus()
        textarea.selectionStart = textarea.selectionEnd = start + placeholder.length
      }, 50)
    } else {
      if (!block.content) block.content = ''
      block.content += ' ' + placeholder
    }
  } else if (block.type === 'image') {
    block.content = value
  }
  compileHtml()
}

// Quick HTML Formatting Tag insertion at cursor
const insertHtmlTag = (openTag, closeTag) => {
  const block = selectedBlock.value
  if (!block || !['text', 'static-table'].includes(block.type)) {
    uiStore.showToast('Vui lòng chọn một khối văn bản hoặc ô bảng trước khi định dạng', 'warning')
    return
  }

  const activeEl = document.activeElement
  if (activeEl && activeEl.getAttribute('contenteditable') === 'true') {
    // If editable div has focus, use selection wrap
    let sel = window.getSelection()
    if (sel.rangeAt && sel.rangeCount) {
      let range = sel.getRangeAt(0)
      let selectedText = range.toString()
      let wrapperHtml = openTag + selectedText + closeTag
      insertHTMLAtCursor(wrapperHtml)
      // Dispatch input event to trigger Vue listener to sync model and compile HTML!
      activeEl.dispatchEvent(new Event('input', { bubbles: true }))
    }
  } else if (activeTextarea.value) {
    const textarea = activeTextarea.value
    const start = textarea.selectionStart
    const end = textarea.selectionEnd
    const text = block.content || ''
    const selectedText = text.substring(start, end)
    const replacement = openTag + selectedText + closeTag
    block.content = text.substring(0, start) + replacement + text.substring(end)
    
    setTimeout(() => {
      textarea.focus()
      textarea.selectionStart = start + openTag.length
      textarea.selectionEnd = start + openTag.length + selectedText.length
    }, 50)
  } else {
    block.content = (block.content || '') + openTag + closeTag
  }
  compileHtml()
}

// Add predefined block: Hotel Info
const addHotelInfoBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '25%',
        blocks: [
          {
            id: `${band}_hotel_logo_${Date.now()}`,
            type: 'image',
            content: 'hotel.logo',
            imageUrl: '',
            style: {
              textAlign: 'center',
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
        ]
      },
      {
        width: '75%',
        blocks: [
          {
            id: `${band}_hotel_info_${Date.now()}`,
            type: 'text',
            content: '<h2 style="margin:0;font-size:16px;font-weight:bold;">{{hotel.name}}</h2>\n<p style="margin:2px 0;font-size:11px;">Đ/C: {{hotel.address}}</p>\n<p style="margin:2px 0;font-size:11px;">SĐT: {{hotel.phone}} | Email: {{hotel.email}}</p>',
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
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '10px',
      marginBottom: '15px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'pre-wrap'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Add predefined block: Customer & Booking Info
const addCustomerInfoBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_cust_${Date.now()}`,
            type: 'text',
            content: '<b>Khách hàng:</b> {{customer.name}}<br><b>Số điện thoại:</b> {{customer.phone}}<br><b>Địa chỉ email:</b> {{customer.email}}',
            style: {
              textAlign: 'left',
              fontSize: '11px',
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
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_book_${Date.now()}`,
            type: 'text',
            content: '<b>Mã đặt phòng:</b> {{booking.code}}<br><b>Ngày đến:</b> {{booking.checkin_date}}<br><b>Ngày đi:</b> {{booking.checkout_date}} ({{booking.nights}} đêm)',
            style: {
              textAlign: 'left',
              fontSize: '11px',
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
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '8px',
      paddingBottom: '8px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '10px',
      marginBottom: '10px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'pre-wrap',
      borderBottom: '1px solid #e2e8f0'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Add predefined block: Signature
const addSignatureBlock = () => {
  const band = selectedBand.value
  const id = `${band}_b_${Date.now()}`
  const newBlock = {
    id,
    type: 'columns',
    columns: [
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_sig1_${Date.now()}`,
            type: 'text',
            content: '<p style="text-align:center;margin:0;"><b>Khách ký nhận</b><br><span style="font-size:10px;color:#94a3b8;font-style:italic;">(Ký và ghi rõ họ tên)</span></p>',
            style: {
              textAlign: 'center',
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
        ]
      },
      {
        width: '50%',
        blocks: [
          {
            id: `${band}_sig2_${Date.now()}`,
            type: 'text',
            content: '<p style="text-align:center;margin:0;"><b>Nhân viên thực hiện</b><br><span style="font-size:10px;color:#94a3b8;font-style:italic;">(Ký và ghi rõ họ tên)</span></p>',
            style: {
              textAlign: 'center',
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
        ]
      }
    ],
    style: {
      textAlign: 'left',
      fontSize: '13px',
      paddingTop: '5px',
      paddingBottom: '5px',
      paddingLeft: '0px',
      paddingRight: '0px',
      marginTop: '30px',
      marginBottom: '40px',
      color: '#1e293b',
      fontWeight: 'normal',
      whiteSpace: 'pre-wrap'
    }
  }
  blocks.value[band].push(newBlock)
  selectedBlockId.value = id
  compileHtml()
}

// Table columns helpers
const addTableColumn = (block) => {
  const fields = getListFields(block.dataSource)
  block.columns.push({
    header: 'Cột mới',
    value: fields[0]?.value || '',
    width: 'auto'
  })
  compileHtml()
}

const deleteTableColumn = (block, colIdx) => {
  if (block.columns.length <= 1) {
    uiStore.showToast('Không thể xóa cột cuối cùng của bảng', 'warning')
    return
  }
  block.columns.splice(colIdx, 1)
  compileHtml()
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
  const originalStyles = b.style || {}
  const compiledStyles = { ...originalStyles }
  
  if (compiledStyles.borderSide && compiledStyles.borderSide !== 'all') {
    const side = compiledStyles.borderSide
    const sideCap = side.charAt(0).toUpperCase() + side.slice(1)
    
    if (compiledStyles.borderStyle) {
      compiledStyles[`border${sideCap}Style`] = compiledStyles.borderStyle
      delete compiledStyles.borderStyle
    }
    if (compiledStyles.borderWidth) {
      compiledStyles[`border${sideCap}Width`] = compiledStyles.borderWidth
      delete compiledStyles.borderWidth
    }
    if (compiledStyles.borderColor) {
      compiledStyles[`border${sideCap}Color`] = compiledStyles.borderColor
      delete compiledStyles.borderColor
    }
    
    const otherSides = ['top', 'bottom', 'left', 'right'].filter(s => s !== side)
    otherSides.forEach(s => {
      const sCap = s.charAt(0).toUpperCase() + s.slice(1)
      compiledStyles[`border${sCap}Style`] = 'none'
    })
    
    delete compiledStyles.borderSide
  } else {
    delete compiledStyles.borderSide
  }
  
  const styles = Object.entries(compiledStyles)
    .filter(([k, v]) => v !== undefined && v !== null && v !== '')
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
    } else if (b.content) {
      blockHtml += `  {{${b.content}}}\n`
    } else {
      blockHtml += '  <!-- empty image block -->\n'
    }
  } else if (b.type === 'table') {
    const tableStyle = b.tableStyle || 'grid'
    let thStyle = 'padding: 6px 8px; font-weight: bold;'
    let tdStyle = 'padding: 6px 8px;'
    
    if (tableStyle === 'grid') {
      thStyle += ' border-bottom: 2px solid #cbd5e1; border-right: 1px solid #cbd5e1;'
      tdStyle += ' border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;'
    } else if (tableStyle === 'horizontal') {
      thStyle += ' border-bottom: 2px solid #cbd5e1;'
      tdStyle += ' border-bottom: 1px solid #e2e8f0;'
    } else {
      thStyle += ' border: none;'
      tdStyle += ' border: none;'
    }

    blockHtml += '  <table style="width: 100%; border-collapse: collapse; border: none;">\n'
    blockHtml += '    <thead>\n      <tr>\n'
    b.columns.forEach(col => {
      blockHtml += `        <th style="${thStyle} width: ${col.width || 'auto'}; text-align: ${col.align || 'left'};">${col.header}</th>\n`
    })
    blockHtml += '      </tr>\n    </thead>\n'
    blockHtml += '    <tbody>\n'
    
    blockHtml += `      <tr class="pms-detail-row" data-source="${b.dataSource}">\n`
    b.columns.forEach(col => {
      blockHtml += `        <td style="${tdStyle} text-align: ${col.align || 'left'};">{{${col.value}}}</td>\n`
    })
    blockHtml += '      </tr>\n'
    blockHtml += '    </tbody>\n'
    blockHtml += '  </table>\n'
  } else if (b.type === 'static-table') {
    const tableStyle = b.tableStyle || 'grid'
    let tdStyle = 'padding: 6px 8px;'
    
    if (tableStyle === 'grid') {
      tdStyle += ' border: 1px solid #cbd5e1;'
    } else if (tableStyle === 'horizontal') {
      tdStyle += ' border-bottom: 1px solid #cbd5e1; border-top: 1px solid #cbd5e1;'
    } else {
      tdStyle += ' border: none;'
    }

    blockHtml += '  <table style="width: 100%; border-collapse: collapse; border: none;">\n'
    blockHtml += '    <tbody>\n'
    if (b.rows) {
      b.rows.forEach(row => {
        blockHtml += '      <tr>\n'
        if (row.cells) {
          row.cells.forEach((cell, colIdx) => {
            const col = b.columns && b.columns[colIdx] ? b.columns[colIdx] : {}
            blockHtml += `        <td style="${tdStyle} width: ${col.width || 'auto'};">${cell.content || ''}</td>\n`
          })
        }
        blockHtml += '      </tr>\n'
      })
    }
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

const getBlockStyle = (b) => {
  if (!b.style) return {}
  const originalStyles = b.style
  const compiledStyles = { ...originalStyles }
  
  if (compiledStyles.borderSide && compiledStyles.borderSide !== 'all') {
    const side = compiledStyles.borderSide
    const sideCap = side.charAt(0).toUpperCase() + side.slice(1)
    
    if (compiledStyles.borderStyle) {
      compiledStyles[`border${sideCap}Style`] = compiledStyles.borderStyle
      delete compiledStyles.borderStyle
    }
    if (compiledStyles.borderWidth) {
      compiledStyles[`border${sideCap}Width`] = compiledStyles.borderWidth
      delete compiledStyles.borderWidth
    }
    if (compiledStyles.borderColor) {
      compiledStyles[`border${sideCap}Color`] = compiledStyles.borderColor
      delete compiledStyles.borderColor
    }
    
    const otherSides = ['top', 'bottom', 'left', 'right'].filter(s => s !== side)
    otherSides.forEach(s => {
      const sCap = s.charAt(0).toUpperCase() + s.slice(1)
      compiledStyles[`border${sCap}Style`] = 'none'
    })
    
    delete compiledStyles.borderSide
  } else {
    delete compiledStyles.borderSide
  }
  
  return compiledStyles
}

const getTableCellStyle = (block, col) => {
  const align = col.align || 'left'
  const borderStyle = block.tableStyle || 'grid'
  
  if (borderStyle === 'horizontal') {
    return {
      textAlign: align,
      borderBottom: '1px solid #e2e8f0',
      borderRight: 'none',
      padding: '8px'
    }
  } else if (borderStyle === 'none') {
    return {
      textAlign: align,
      border: 'none',
      padding: '8px'
    }
  } else {
    return {
      textAlign: align,
      borderBottom: '1px solid #cbd5e1',
      borderRight: '1px solid #cbd5e1',
      padding: '8px'
    }
  }
}

const getTableHeaderStyle = (block, col) => {
  const align = col.align || 'left'
  const borderStyle = block.tableStyle || 'grid'
  
  if (borderStyle === 'horizontal') {
    return {
      textAlign: align,
      borderBottom: '2px solid #cbd5e1',
      borderRight: 'none',
      padding: '8px',
      fontWeight: 'bold'
    }
  } else if (borderStyle === 'none') {
    return {
      textAlign: align,
      border: 'none',
      padding: '8px',
      fontWeight: 'bold'
    }
  } else {
    return {
      textAlign: align,
      borderBottom: '2px solid #cbd5e1',
      borderRight: '1px solid #cbd5e1',
      padding: '8px',
      fontWeight: 'bold'
    }
  }
}

const onBorderSideChange = (block) => {
  if (!block.style.borderStyle) {
    block.style.borderStyle = 'solid'
  }
  if (!block.style.borderWidth) {
    block.style.borderWidth = '1px'
  }
  if (!block.style.borderColor) {
    block.style.borderColor = '#cbd5e1'
  }
  compileHtml()
}

const confirmTableSetup = (block) => {
  if (block.tableType === 'dynamic') {
    const allFields = getListFields(block.dataSource)
    block.columns = block.selectedFields.map(val => {
      const found = allFields.find(f => f.value === val)
      return {
        header: found ? found.label : 'Cột',
        value: val,
        width: 'auto',
        align: 'left'
      }
    })
    if (block.columns.length === 0) {
      block.columns = [{ header: 'Cột mới', value: '', width: 'auto', align: 'left' }]
    }
  } else {
    block.type = 'static-table'
    block.columns = Array.from({ length: block.colsCount }, () => ({
      width: `${Math.round(100 / block.colsCount)}%`
    }))
    block.rows = Array.from({ length: block.rowsCount }, () => ({
      cells: Array.from({ length: block.colsCount }, () => ({
        content: 'Nội dung ô...'
      }))
    }))
  }
  block.isNew = false
  compileHtml()
}

const addStaticRow = (block) => {
  const colsCount = block.columns.length
  block.rows.push({
    cells: Array.from({ length: colsCount }, () => ({ content: 'Nội dung ô...' }))
  })
  compileHtml()
}

const deleteStaticRow = (block, rowIndex) => {
  if (block.rows.length <= 1) {
    uiStore.showToast('Không thể xóa hàng duy nhất của bảng', 'warning')
    return
  }
  block.rows.splice(rowIndex, 1)
  compileHtml()
}

const addStaticColumn = (block) => {
  block.columns.push({ width: 'auto' })
  const newColsCount = block.columns.length
  block.columns.forEach(col => {
    col.width = `${Math.round(100 / newColsCount)}%`
  })
  block.rows.forEach(row => {
    row.cells.push({ content: 'Nội dung ô...' })
  })
  compileHtml()
}

const deleteStaticColumn = (block, colIndex) => {
  if (block.columns.length <= 1) {
    uiStore.showToast('Không thể xóa cột duy nhất của bảng', 'warning')
    return
  }
  block.columns.splice(colIndex, 1)
  const newColsCount = block.columns.length
  block.columns.forEach(col => {
    col.width = `${Math.round(100 / newColsCount)}%`
  })
  block.rows.forEach(row => {
    row.cells.splice(colIndex, 1)
  })
  compileHtml()
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

watch(selectedBlockId, (newId) => {
  if (newId) {
    const block = selectedBlock.value
    if (block) {
      editingContent.value = block.content || ''
    }
  } else {
    editingContent.value = ''
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

            <!-- PREDEFINED BLOCKS -->
            <div class="flex flex-col gap-2 bg-white rounded-xl p-3 border border-slate-200 shadow-3xs">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest pb-1 border-b border-slate-100 block">Khối Dựng Sẵn (Templates)</span>
              <div class="flex flex-col gap-1.5 pt-2">
                <button @click="addHotelInfoBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">🏢</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Thông tin Khách sạn</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Logo + Tên + Địa chỉ (2 cột)</p>
                  </div>
                </button>
                <button @click="addCustomerInfoBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">👤</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Thông tin Khách & Đặt phòng</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Thông tin khách hàng & checkin/out</p>
                  </div>
                </button>
                <button @click="addSignatureBlock" class="flex items-center gap-2.5 p-2 border border-slate-200 rounded-lg hover:border-sky-300 hover:bg-sky-50 text-slate-600 hover:text-sky-700 font-bold text-[10px] cursor-pointer transition-all text-left w-full">
                  <span class="text-base shrink-0">✍️</span>
                  <div class="leading-tight">
                    <p class="font-bold text-slate-700 text-[10px]">Khối Chữ ký phê duyệt</p>
                    <p class="text-[9px] text-slate-400 font-medium font-sans">Khách ký nhận + Lễ tân thực hiện</p>
                  </div>
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
                    <div v-if="b.type === 'text'" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                        @focus="onTextareaFocus"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Table Setup and Rendering -->
                    <div v-else-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" class="w-full bg-transparent border-none font-bold text-slate-800 text-xs focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" :class="col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" :style="getTableCellStyle(b, col)" class="font-mono text-[10px] text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx"
                              :style="getTableCellStyle(b, b.columns[cIdx] || {})"
                              class="relative group/td p-0">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-slate-700"
                                v-html="activeCell === cell ? editingCellContent : cell.content"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
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
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                              @focus="onTextareaFocus"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
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
                    <div v-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" class="w-full bg-transparent border-none font-bold text-slate-800 text-xs focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" :class="col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" :style="getTableCellStyle(b, col)" class="font-mono text-[10px] text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    
                    <!-- Other Block Types -->
                    <div v-if="b.type === 'text'" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                        @focus="onTextareaFocus"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx"
                              :style="getTableCellStyle(b, b.columns[cIdx] || {})"
                              class="relative group/td p-0">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-slate-700"
                                v-html="activeCell === cell ? editingCellContent : cell.content"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
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
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                              @focus="onTextareaFocus"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
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
                    <div v-if="b.type === 'text'" :style="getBlockStyle(b)">
                      <div v-if="selectedBlockId === b.id" 
                        contenteditable="true"
                        @input="b.content = $event.target.innerHTML; compileHtml()"
                        @focus="onTextareaFocus"
                        class="w-full focus:outline-none focus:ring-1 focus:ring-sky-500 min-h-[20px] outline-none"
                        v-html="editingContent"></div>
                      <div v-else class="min-h-[20px]" v-html="b.content"></div>
                    </div>
                    <div v-else-if="b.type === 'divider'" v-html="b.content"></div>
                    <!-- Table Setup and Rendering -->
                    <div v-else-if="b.type === 'table'" class="w-full overflow-x-auto text-left">
                      <!-- New Table Configuration Card -->
                      <div v-if="b.isNew" class="p-4 border border-sky-200 bg-sky-50/50 rounded-xl flex flex-col gap-3">
                        <p class="text-xs font-bold text-sky-800 flex items-center gap-1 select-none">
                          📊 Cấu hình bảng dữ liệu mới
                        </p>
                        <div class="grid grid-cols-2 gap-3 select-none">
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Loại bảng:</span>
                            <div class="flex gap-4 mt-1">
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="dynamic" />
                                Lặp dữ liệu
                              </label>
                              <label class="flex items-center gap-1 text-xs font-bold text-slate-600 cursor-pointer">
                                <input type="radio" v-model="b.tableType" value="static" />
                                Bảng tĩnh tự nhập
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Dynamic Table -->
                        <div v-if="b.tableType === 'dynamic'" class="flex flex-col gap-2.5">
                          <div class="flex items-center gap-2 select-none">
                            <span class="text-xs text-slate-500 font-semibold">Nguồn dữ liệu:</span>
                            <select v-model="b.dataSource" class="text-xs border border-slate-200 rounded p-1 font-bold text-slate-700">
                              <option v-for="f in fieldList.lists" :key="f.value" :value="f.value">
                                {{ f.label }}
                              </option>
                            </select>
                          </div>
                          
                          <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase select-none">Chọn các cột hiển thị:</span>
                            <div class="flex flex-wrap gap-2 pt-1 select-none">
                              <label v-for="f in getListFields(b.dataSource)" :key="f.value" class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-slate-200 rounded-md font-semibold text-slate-600 cursor-pointer">
                                <input type="checkbox" v-model="b.selectedFields" :value="f.value" />
                                {{ f.label }}
                              </label>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Configuration fields for Static Table -->
                        <div v-else class="grid grid-cols-2 gap-3">
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số hàng:</span>
                            <input type="number" v-model.number="b.rowsCount" min="1" max="20" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                          <div class="flex flex-col gap-1">
                            <span class="text-xs text-slate-500 font-semibold select-none">Số cột:</span>
                            <input type="number" v-model.number="b.colsCount" min="1" max="10" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono focus:outline-sky-500" />
                          </div>
                        </div>
                        
                        <div class="flex justify-end pt-1">
                          <button @click.stop="confirmTableSetup(b)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-black rounded-lg cursor-pointer flex items-center gap-1 shadow-3xs uppercase border-none">
                            <Check class="w-3.5 h-3.5" /> Tạo bảng biểu
                          </button>
                        </div>
                      </div>
                      
                      <!-- Configured Dynamic Table -->
                      <div v-else>
                        <p class="text-[10px] text-sky-600 font-bold mb-1 uppercase tracking-wide">
                          🔗 Bảng lặp nguồn: {{ b.dataSource }}
                        </p>
                        <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                          <thead>
                            <tr class="font-bold">
                              <th v-for="(col, colIdx) in b.columns" :key="colIdx" class="relative group/th" :style="getTableHeaderStyle(b, col)">
                                <input type="text" v-model="col.header" class="w-full bg-transparent border-none font-bold text-slate-800 text-xs focus:ring-1 focus:ring-sky-500 rounded px-1 py-0.5" :class="col.align === 'center' ? 'text-center' : col.align === 'right' ? 'text-right' : 'text-left'" />
                                <button @click.stop="deleteTableColumn(b, colIdx)" class="absolute top-1.5 right-1 hidden group-hover/th:flex w-4 h-4 bg-red-100 hover:bg-red-200 text-red-600 rounded text-[9px] border-none cursor-pointer items-center justify-center font-bold">×</button>
                              </th>
                              <th class="p-1 text-center w-8 bg-slate-100 select-none" style="border-bottom: 2px solid #cbd5e1;">
                                <button @click.stop="addTableColumn(b)" class="w-5 h-5 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded border-none cursor-pointer text-xs font-bold flex items-center justify-center">+</button>
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="bg-white">
                              <td v-for="col in b.columns" :key="col.value" :style="getTableCellStyle(b, col)" class="font-mono text-[10px] text-slate-400">
                                {{ col.value }}
                              </td>
                              <td class="bg-slate-50/50" :style="{ borderBottom: b.tableStyle === 'none' ? 'none' : '1px solid #cbd5e1' }"></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- Configured Static Table rendering -->
                    <div v-else-if="b.type === 'static-table'" class="w-full overflow-x-auto text-left">
                      <table class="w-full text-xs border-collapse border-none" :style="getBlockStyle(b)">
                        <tbody>
                          <tr v-for="(row, rIdx) in b.rows" :key="rIdx">
                            <td v-for="(cell, cIdx) in row.cells" :key="cIdx"
                              :style="getTableCellStyle(b, b.columns[cIdx] || {})"
                              class="relative group/td p-0">
                              <!-- ContentEditable Cell directly on Canvas -->
                              <div contenteditable="true"
                                @focus="onCellFocus(cell)"
                                @blur="onCellBlur"
                                @input="cell.content = $event.target.innerHTML; compileHtml()"
                                class="w-full min-h-[24px] focus:outline-none focus:ring-1 focus:ring-sky-500 rounded px-1.5 py-1 outline-none text-slate-700"
                                v-html="activeCell === cell ? editingCellContent : cell.content"></div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else-if="b.type === 'spacer'" class="border border-dashed border-slate-200 bg-slate-50/50 rounded flex items-center justify-center text-[10px] text-slate-400 italic" :style="{ height: `${b.height || 20}px` }">
                      Khoảng trống {{ b.height || 20 }}px
                    </div>
                    <div v-else-if="b.type === 'image'" class="relative group/img-wrapper select-none">
                      <div v-if="b.imageUrl" class="text-center py-2 bg-slate-50/50 rounded flex justify-center items-center min-h-[40px] relative">
                        <img :src="b.imageUrl" class="max-h-20 max-w-full" alt="Image" />
                        <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/img-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded">
                          <label class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-800 text-[10px] font-bold rounded cursor-pointer shadow-sm">
                            Thay đổi
                            <input type="file" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                          </label>
                          <button @click.stop="b.imageUrl = ''; b.content = ''; compileHtml()" class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold rounded cursor-pointer border-none shadow-sm">
                            Xóa
                          </button>
                        </div>
                      </div>
                      <div v-else class="text-center py-3 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[60px] cursor-pointer" @click.stop="triggerCanvasImageUpload(b)">
                        <span class="text-base">🖼️</span>
                        <span v-if="b.content" class="font-bold text-sky-800 font-mono text-[10px] mt-1">[Biến liên kết: {{ b.content }}]</span>
                        <span v-else class="font-bold text-slate-500 text-[10px] mt-1">[Chưa có ảnh - Click để tải lên]</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Click để chọn tệp hình ảnh</span>
                        <input type="file" :id="'file_input_' + b.id" @change="handleCanvasImageUpload($event, b)" accept="image/*" class="hidden" />
                      </div>
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
                            <div v-if="selectedBlockId === subBlock.id"
                              contenteditable="true"
                              @input="subBlock.content = $event.target.innerHTML; compileHtml()"
                              @focus="onTextareaFocus"
                              class="w-full text-[11px] border border-sky-300 rounded p-1 text-slate-700 min-h-[30px] bg-white outline-none font-sans font-medium"
                              v-html="editingContent"></div>
                            <div v-else class="text-slate-700 leading-relaxed font-semibold text-[11px] min-h-[15px] font-sans" v-html="subBlock.content"></div>
                          </div>
                          <div v-else-if="subBlock.type === 'image'" class="relative group/subimg-wrapper select-none">
                            <div v-if="subBlock.imageUrl" class="text-center py-1 bg-slate-50/50 rounded flex justify-center items-center min-h-[30px] relative">
                              <img :src="subBlock.imageUrl" class="max-h-12 max-w-full" alt="Image" />
                              <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover/subimg-wrapper:opacity-100 transition-opacity flex items-center justify-center gap-1 rounded">
                                <label class="px-1.5 py-0.5 bg-white hover:bg-slate-100 text-slate-800 text-[9px] font-bold rounded cursor-pointer shadow-sm">
                                  Thay
                                  <input type="file" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                                </label>
                                <button @click.stop="subBlock.imageUrl = ''; subBlock.content = ''; compileHtml()" class="px-1.5 py-0.5 bg-red-600 hover:bg-red-700 text-white text-[9px] font-bold rounded cursor-pointer border-none shadow-sm">
                                  Xóa
                                </button>
                              </div>
                            </div>
                            <div v-else class="text-center py-2 bg-sky-50/50 border border-dashed border-sky-300 rounded flex flex-col justify-center items-center min-h-[50px] cursor-pointer" @click.stop="triggerCanvasImageUpload(subBlock)">
                              <span class="text-sm">🖼️</span>
                              <span v-if="subBlock.content" class="font-bold text-sky-800 font-mono text-[9px] mt-0.5">[{{ subBlock.content }}]</span>
                              <span v-else class="font-bold text-slate-500 text-[9px] mt-0.5">[Chọn ảnh]</span>
                              <span class="text-[8px] text-slate-400">Tải ảnh</span>
                              <input type="file" :id="'file_input_' + subBlock.id" @change="handleCanvasImageUpload($event, subBlock)" accept="image/*" class="hidden" />
                            </div>
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
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Đậm (Tất cả):</span>
                    <button @click="selectedBlock.style.fontWeight = selectedBlock.style.fontWeight === 'bold' ? 'normal' : 'bold'"
                      class="h-8 rounded-lg border font-bold text-xs flex items-center justify-center cursor-pointer transition-colors"
                      :class="selectedBlock.style.fontWeight === 'bold' ? 'bg-sky-50 border-sky-300 text-sky-700' : 'bg-slate-50 border-slate-200 text-slate-600'">
                      <Bold class="w-4 h-4" />
                    </button>
                  </div>
                </div>

                <!-- Formatting Toolbar for Highlighted Selection -->
                <div class="flex flex-col gap-1.5 mt-2 pt-2 border-t border-slate-100">
                  <span class="text-[10px] text-slate-400 font-bold uppercase">Định dạng chữ bôi đen:</span>
                  <div class="flex flex-wrap gap-1 bg-slate-100 p-1.5 rounded-lg border border-slate-200 select-none">
                    <button @mousedown.prevent="formatText('bold')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs font-bold cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In đậm (Ctrl+B)">B</button>
                    <button @mousedown.prevent="formatText('italic')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs italic cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In nghiêng (Ctrl+I)">I</button>
                    <button @mousedown.prevent="formatText('underline')" class="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs underline cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Gạch chân (Ctrl+U)">U</button>
                    <button @mousedown.prevent="insertHtmlTag('<br>', '')" class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-xs cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Xuống dòng">br</button>
                    <button @mousedown.prevent="insertHtmlTag('<b>', '</b>')" class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[11px] font-bold cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Thẻ Bold">&lt;b&gt;</button>
                  </div>
                </div>

                <!-- Paddings & Margins -->
                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Top:</span>
                    <input type="text" v-model="selectedBlock.style.paddingTop" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Padding Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.paddingBottom" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Top:</span>
                    <input type="text" v-model="selectedBlock.style.marginTop" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] text-slate-400 font-bold uppercase">Margin Bottom:</span>
                    <input type="text" v-model="selectedBlock.style.marginBottom" @input="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg px-2 py-1" />
                  </div>
                </div>

                <!-- Borders & Backgrounds -->
                <div class="flex flex-col gap-2.5 mt-2 pt-2 border-t border-slate-100">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Khung viền & Nền</span>
                  
                  <!-- Background Color picker -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Màu nền:</span>
                    <div class="flex items-center gap-1">
                      <input type="color" v-model="selectedBlock.style.backgroundColor" @change="compileHtml" class="w-8 h-8 border border-slate-200 rounded cursor-pointer" />
                      <button @click="selectedBlock.style.backgroundColor = ''; compileHtml()" class="px-1.5 py-0.5 bg-slate-100 hover:bg-slate-200 text-[10px] text-slate-500 rounded border-none cursor-pointer">Xóa</button>
                    </div>
                  </div>

                  <!-- Border apply side dropdown -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Cạnh áp dụng viền:</span>
                    <select v-model="selectedBlock.style.borderSide" @change="onBorderSideChange(selectedBlock)" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 focus:outline-sky-500">
                      <option value="all">Tất cả các cạnh</option>
                      <option value="top">Cạnh trên</option>
                      <option value="bottom">Cạnh dưới</option>
                      <option value="left">Cạnh trái</option>
                      <option value="right">Cạnh phải</option>
                    </select>
                  </div>

                  <!-- Border style dropdown -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Kiểu đường viền:</span>
                    <select v-model="selectedBlock.style.borderStyle" @change="compileHtml" class="text-xs border border-slate-200 rounded px-1.5 py-0.5 focus:outline-sky-500">
                      <option value="none">Không viền (none)</option>
                      <option value="solid">Đường liền (solid)</option>
                      <option value="dashed">Đường đứt nét (dashed)</option>
                      <option value="dotted">Đường chấm (dotted)</option>
                      <option value="double">Đường đôi (double)</option>
                    </select>
                  </div>

                  <!-- Border width input -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Độ dày viền:</span>
                    <input type="text" v-model="selectedBlock.style.borderWidth" @input="compileHtml" class="w-20 text-center text-xs border border-slate-200 rounded py-0.5" placeholder="1px" />
                  </div>

                  <!-- Border color picker -->
                  <div class="flex items-center justify-between" v-if="selectedBlock.style.borderStyle && selectedBlock.style.borderStyle !== 'none'">
                    <span class="text-xs text-slate-500">Màu đường viền:</span>
                    <input type="color" v-model="selectedBlock.style.borderColor" @change="compileHtml" class="w-8 h-8 border border-slate-200 rounded cursor-pointer" />
                  </div>

                  <!-- Border radius input -->
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-500">Bo góc bo viền:</span>
                    <input type="text" v-model="selectedBlock.style.borderRadius" @input="compileHtml" class="w-20 text-center text-xs border border-slate-200 rounded py-0.5" placeholder="0px" />
                  </div>
                </div>

              </div>

              <!-- CONTENT EDITORS BY BLOCK TYPE -->
              
              <!-- 1. Text content editor -->
              <div v-if="selectedBlock.type === 'text'" class="flex flex-col gap-2">
                <div class="flex justify-between items-center select-none">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Soạn thảo văn bản:</span>
                  <div class="flex bg-slate-100 p-0.5 rounded-md text-[9px] font-bold">
                    <button @click="editorMode = 'visual'" class="px-2 py-0.5 rounded transition-all cursor-pointer border-none" :class="editorMode === 'visual' ? 'bg-white shadow-3xs text-sky-600 font-bold' : 'text-slate-400'">Trực quan</button>
                    <button @click="editorMode = 'code'" class="px-2 py-0.5 rounded transition-all cursor-pointer border-none" :class="editorMode === 'code' ? 'bg-white shadow-3xs text-sky-600 font-bold' : 'text-slate-400'">HTML Code</button>
                  </div>
                </div>
                
                <!-- Quick HTML format buttons -->
                <div v-if="editorMode === 'visual'" class="flex flex-wrap gap-1 bg-slate-100 p-1.5 rounded-lg border border-slate-200">
                  <button @mousedown.prevent="formatText('bold')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] font-bold cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In đậm">B</button>
                  <button @mousedown.prevent="formatText('italic')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] italic cursor-pointer text-slate-700 active:scale-95 transition-transform" title="In nghiêng">I</button>
                  <button @mousedown.prevent="formatText('underline')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] underline cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Gạch chân">U</button>
                  <button @mousedown.prevent="insertHtmlTag('<br>', '')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Xuống dòng">br</button>
                  <button @mousedown.prevent="insertHtmlTag('<p>', '</p>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Đoạn văn">p</button>
                  <button @mousedown.prevent="insertHtmlTag('<span style=\'font-size:16px;font-weight:bold;\'>', '</span>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-700 active:scale-95 transition-transform" title="Chữ lớn">Lớn</button>
                  <button @mousedown.prevent="insertHtmlTag('<span style=\'color:#ef4444;font-weight:bold;\'>', '</span>')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-red-600 font-bold active:scale-95 transition-transform" title="Chữ đỏ">Đỏ</button>
                  <button @mousedown.prevent="formatText('justifyCenter')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Căn giữa">Giữa</button>
                  <button @mousedown.prevent="formatText('justifyLeft')" class="px-2 py-0.5 bg-white hover:bg-slate-50 border border-slate-200 rounded text-[10px] cursor-pointer text-slate-600 active:scale-95 transition-transform" title="Căn trái">Trái</button>
                </div>

                <!-- Visual Editor -->
                <div v-if="editorMode === 'visual'"
                  contenteditable="true"
                  @input="selectedBlock.content = $event.target.innerHTML; compileHtml()"
                  @focus="onTextareaFocus"
                  class="w-full text-xs border border-slate-200 rounded-xl p-3 focus:outline-sky-500 font-sans leading-relaxed min-h-[200px] bg-white outline-none"
                  v-html="editingContent"
                ></div>

                <!-- Source HTML Code Editor -->
                <textarea v-else v-model="selectedBlock.content" rows="10" 
                  @focus="onTextareaFocus"
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
                    <button v-if="selectedBlock.imageUrl" @click="selectedBlock.imageUrl = ''; selectedBlock.content = ''; compileHtml()" 
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
                  <div class="flex gap-2">
                    <input type="text" :value="selectedBlock.content" placeholder="Chưa có liên kết biến (Ví dụ: hotel.logo)" class="flex-1 text-xs border border-slate-200 rounded-lg p-2 bg-slate-50 font-mono font-bold" readonly />
                    <button v-if="selectedBlock.content" @click="selectedBlock.content = ''; compileHtml()" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-lg cursor-pointer">Xóa</button>
                  </div>
                </div>
              </div>

              <!-- 4. Table Block column and fields mapping configuration -->
              <div v-else-if="selectedBlock.type === 'table' && !selectedBlock.isNew" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Nguồn dữ liệu bảng lặp:</span>
                  <select v-model="selectedBlock.dataSource" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="booking.services">Lặp dịch vụ (booking.services)</option>
                    <option value="booking.rooms">Lặp danh sách phòng (booking.rooms)</option>
                    <option value="booking.payments">Lặp thanh toán (booking.payments)</option>
                  </select>
                </div>

                <!-- Table Style option -->
                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Kiểu hiển thị đường kẻ:</span>
                  <select v-model="selectedBlock.tableStyle" @change="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="grid">Lưới đầy đủ (grid)</option>
                    <option value="horizontal">Chỉ kẻ dòng ngang (horizontal)</option>
                    <option value="none">Không kẻ đường viền (none)</option>
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
                      
                      <!-- Alignment -->
                      <div class="flex flex-col gap-0.5">
                        <span class="text-[9px] font-bold text-slate-400">Căn lề cột:</span>
                        <select v-model="col.align" @change="compileHtml" class="text-[11px] border border-slate-200 rounded px-1 py-0.5 font-semibold">
                          <option value="left">Căn trái (Left)</option>
                          <option value="center">Căn giữa (Center)</option>
                          <option value="right">Căn phải (Right)</option>
                        </select>
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

              <!-- 4b. Static Table configuration -->
              <div v-else-if="selectedBlock.type === 'static-table'" class="flex flex-col gap-3">
                <span class="text-[10px] font-bold text-slate-400 uppercase">Quản lý dòng & cột:</span>
                <div class="grid grid-cols-2 gap-2">
                  <button @click="addStaticRow(selectedBlock)" class="px-2.5 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-lg border border-sky-200 cursor-pointer">
                    + Thêm hàng
                  </button>
                  <button @click="addStaticColumn(selectedBlock)" class="px-2.5 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-lg border border-sky-200 cursor-pointer">
                    + Thêm cột
                  </button>
                </div>

                <div class="flex flex-col gap-1">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Kiểu hiển thị đường kẻ:</span>
                  <select v-model="selectedBlock.tableStyle" @change="compileHtml" class="w-full text-xs border border-slate-200 rounded-lg p-2 focus:outline-sky-500 font-semibold">
                    <option value="grid">Lưới đầy đủ (grid)</option>
                    <option value="horizontal">Chỉ kẻ dòng ngang (horizontal)</option>
                    <option value="none">Không kẻ đường viền (none)</option>
                  </select>
                </div>

                <!-- Custom width inputs for columns -->
                <div class="flex flex-col gap-2 mt-2">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Độ rộng các cột:</span>
                  <div class="flex flex-col gap-2 max-h-[150px] overflow-y-auto">
                    <div v-for="(col, cIdx) in selectedBlock.columns" :key="cIdx" class="flex justify-between items-center p-2 bg-white border border-slate-200 rounded-lg text-xs">
                      <span class="font-bold text-slate-600">Cột {{ cIdx + 1 }}:</span>
                      <div class="flex items-center gap-1.5">
                        <input type="text" v-model="col.width" @input="compileHtml" class="w-16 text-center text-xs border border-slate-200 rounded p-1 font-mono" placeholder="50%" />
                        <button @click="deleteStaticColumn(selectedBlock, cIdx)" class="px-1.5 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[10px] border border-red-200 cursor-pointer font-bold">×</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Rows delete helper -->
                <div class="flex flex-col gap-2 mt-2">
                  <span class="text-[10px] font-bold text-slate-400 uppercase">Danh sách các hàng:</span>
                  <div class="flex flex-col gap-2 max-h-[150px] overflow-y-auto">
                    <div v-for="(row, rIdx) in selectedBlock.rows" :key="rIdx" class="flex justify-between items-center p-2 bg-white border border-slate-200 rounded-lg text-xs">
                      <span class="font-bold text-slate-600">Hàng {{ rIdx + 1 }}:</span>
                      <button @click="deleteStaticRow(selectedBlock, rIdx)" class="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded text-[10px] border border-red-200 cursor-pointer">Xóa hàng</button>
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

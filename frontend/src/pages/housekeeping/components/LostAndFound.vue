<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  Camera,
  ChevronLeft,
  ChevronRight,
  FileText,
  Pencil,
  Plus,
  Save,
  Trash2,
  X,
} from '@lucide/vue'
import http from '@/services/http'
import { fetchSystemDate } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'

const API_URL = '/lost-and-found'
const PAGE_SIZE = 10

const route = useRoute()
const uiStore = useUiStore()
const items = ref([])
const isLoading = ref(false)
const systemDate = ref('')
const statusFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const searchQuery = ref('')
const currentPage = ref(1)
const showModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isDeleting = ref(false)
const images = ref([])
const lightboxImage = ref('')
const fileInput = ref(null)
const initialFormSnapshot = ref('')

const emptyForm = () => ({
  id: null,
  status: 'lost',
  guest_info: '',
  item_found: '',
  date_reported: systemDate.value,
  date_found: '',
  who_found: '',
  where_found: '',
  storage_location: '',
  date_handling: '',
  method_handling: '',
  delieved_handling: '',
  received_handling: '',
  received: '',
  remarks: '',
})

const form = reactive(emptyForm())

const formatDate = (value) => {
  if (!value) return ''
  const [year, month, day] = String(value).slice(0, 10).split('-')
  return year && month && day ? `${day}/${month}/${year}` : value
}

const normalizeImages = (value) => {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (!value) return []
  return [value]
}

const fetchItems = async () => {
  isLoading.value = true
  try {
    const { data } = await http.get(API_URL)
    items.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error(error)
    uiStore.showToast('Không thể tải dữ liệu đồ thất lạc', 'error')
  } finally {
    isLoading.value = false
  }
}

const loadSystemDate = async () => {
  const localDate = new Date()
  systemDate.value = `${localDate.getFullYear()}-${String(localDate.getMonth() + 1).padStart(2, '0')}-${String(localDate.getDate()).padStart(2, '0')}`

  try {
    const response = await fetchSystemDate()
    systemDate.value = response?.data?.data?.system_date || response?.data?.system_date || systemDate.value
  } catch (error) {
    console.error('Không thể lấy ngày hệ thống', error)
  }
}

const filteredItems = computed(() => {
  const query = searchQuery.value.trim().toLocaleLowerCase('vi')

  return items.value.filter((item) => {
    const reportDate = String(item.date_reported || '').slice(0, 10)
    const matchesStatus = !statusFilter.value || item.status === statusFilter.value
    const matchesFrom = !dateFrom.value || reportDate >= dateFrom.value
    const matchesTo = !dateTo.value || reportDate <= dateTo.value
    const matchesSearch = !query || [item.guest_info, item.item_found, item.where_found]
      .some((value) => String(value || '').toLocaleLowerCase('vi').includes(query))

    return matchesStatus && matchesFrom && matchesTo && matchesSearch
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredItems.value.length / PAGE_SIZE)))
const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * PAGE_SIZE
  return filteredItems.value.slice(start, start + PAGE_SIZE)
})

watch([statusFilter, dateFrom, dateTo, searchQuery], () => {
  currentPage.value = 1
})

watch(totalPages, (pages) => {
  if (currentPage.value > pages) currentPage.value = pages
})

watch(() => route.query.openAdd, (value) => {
  if (value === 'true' && !showModal.value) openCreateModal()
})

watch(() => route.query.selectedMethod, (value) => {
  if (value === 'Chưa xử lý') statusFilter.value = 'lost'
})

const resetFilters = () => {
  statusFilter.value = ''
  dateFrom.value = systemDate.value
  dateTo.value = systemDate.value
  searchQuery.value = ''
  currentPage.value = 1
}

const onDateFromChange = () => {
  if (dateFrom.value && (!dateTo.value || dateTo.value < dateFrom.value)) {
    dateTo.value = dateFrom.value
  }
}

const snapshotForm = () => JSON.stringify({ ...form, image: images.value })

const openCreateModal = () => {
  Object.assign(form, emptyForm())
  images.value = []
  isEditing.value = false
  initialFormSnapshot.value = snapshotForm()
  showModal.value = true
}

const openEditModal = (item) => {
  Object.assign(form, emptyForm(), item, {
    date_reported: String(item.date_reported || '').slice(0, 10),
    date_found: String(item.date_found || '').slice(0, 10),
    date_handling: String(item.date_handling || '').slice(0, 10),
  })
  images.value = normalizeImages(item.image)
  isEditing.value = true
  initialFormSnapshot.value = snapshotForm()
  showModal.value = true
}

const closeModal = async (force = false) => {
  if (!force && snapshotForm() !== initialFormSnapshot.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn đóng không?' })
    if (!confirmed) return
  }
  showModal.value = false
}

const modalPos = ref({ x: 0, y: 0 })

function createDragHandler(posRef) {
  let dragStart = { x: 0, y: 0 }
  let isDragging = false
  let rafId = null

  function onMouseMove(e) {
    if (!isDragging) return
    if (rafId) return
    rafId = requestAnimationFrame(() => {
      posRef.value.x = e.clientX - dragStart.x
      posRef.value.y = e.clientY - dragStart.y
      rafId = null
    })
  }

  function onMouseUp() {
    isDragging = false
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  }

  return function startDrag(e) {
    const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL', 'SVG', 'PATH']
    if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
    
    isDragging = true
    dragStart.x = e.clientX - posRef.value.x
    dragStart.y = e.clientY - posRef.value.y
    
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
  }
}

const startDragModal = createDragHandler(modalPos)

function handleKeyDown(event) {
  if (event.key === 'Escape' && showModal.value) {
    closeModal()
  }
}

watch(showModal, (visible) => {
  window.removeEventListener('keydown', handleKeyDown)
  if (visible) {
    window.addEventListener('keydown', handleKeyDown)
  } else {
    modalPos.value = { x: 0, y: 0 }
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
})

const setStatus = (status) => {
  form.status = status
}

const handleImages = (event) => {
  const files = Array.from(event.target.files || [])
  const oversized = files.find((file) => file.size > 5 * 1024 * 1024)
  if (oversized) {
    uiStore.showToast('Mỗi hình ảnh không được vượt quá 5MB', 'warning')
    event.target.value = ''
    return
  }

  files.forEach((file) => {
    const reader = new FileReader()
    reader.onload = () => images.value.push(reader.result)
    reader.readAsDataURL(file)
  })
  event.target.value = ''
}

const removeImage = (index) => {
  images.value.splice(index, 1)
}

const saveItem = async () => {
  if (!form.item_found.trim() || !form.date_reported) {
    uiStore.showToast('Vui lòng nhập Mặt hàng và Ngày báo cáo', 'warning')
    return
  }

  isSaving.value = true
  const payload = { ...form, image: [...images.value] }
  delete payload.id
  delete payload.created_by
  delete payload.created_at
  delete payload.updated_at

  try {
    if (isEditing.value) {
      await http.put(`${API_URL}/${form.id}`, payload)
      uiStore.showToast('Cập nhật phiếu thành công', 'success')
    } else {
      await http.post(API_URL, payload)
      uiStore.showToast('Thêm phiếu thành công', 'success')
    }
    await fetchItems()
    initialFormSnapshot.value = snapshotForm()
    await closeModal(true)
    currentPage.value = 1
  } catch (error) {
    console.error(error)
    const message = error?.response?.data?.message || 'Không thể lưu phiếu Lost & Found'
    uiStore.showToast(message, 'error')
  } finally {
    isSaving.value = false
  }
}

const deleteItem = async (item) => {
  const confirmed = await uiStore.confirm({ message: `Xóa phiếu “${item.item_found}”?` })
  if (!confirmed) return

  isDeleting.value = true
  try {
    await http.delete(`${API_URL}/${item.id}`)
    await fetchItems()
    uiStore.showToast('Xóa phiếu thành công', 'success')
  } catch (error) {
    console.error(error)
    uiStore.showToast('Không thể xóa phiếu', 'error')
  } finally {
    isDeleting.value = false
  }
}

onMounted(async () => {
  isLoading.value = true
  await loadSystemDate()
  resetFilters()
  if (route.query.selectedMethod === 'Chưa xử lý') statusFilter.value = 'lost'
  await fetchItems()
  if (route.query.openAdd === 'true') openCreateModal()
})
</script>

<template>
  <div class="lost-found-page h-full flex flex-col overflow-hidden bg-[#f0f2f5] font-['Roboto',sans-serif] text-xs">
    <LoadingOverlay :show="isLoading || isDeleting" />

    <main class="content flex-1 min-h-0 overflow-hidden flex flex-col gap-2.5 p-3.5">
      <!-- Toolbar -->
      <div class="filter-bar flex items-center gap-2.5 flex-wrap p-2.5 bg-white border border-slate-200 rounded-lg shadow-xs shrink-0">
        <div class="flex items-center gap-1.5">
          <label for="lost-found-status" class="text-xs font-semibold text-[#000000D9] whitespace-nowrap">Tình trạng</label>
          <select id="lost-found-status" v-model="statusFilter" class="h-8 px-2.5 text-xs text-[#000000D9] bg-white border border-slate-300 rounded-lg outline-none focus:border-blue-500 cursor-pointer">
            <option value="">Tất cả</option>
            <option value="lost">Lost</option>
            <option value="found">Found</option>
          </select>
        </div>
        
        <span class="w-[1px] h-5 bg-slate-200 mx-1"></span>

        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-[#000000D9] whitespace-nowrap">Từ ngày</label>
          <SingleDatePicker 
            v-model="dateFrom" 
            placeholder="dd/mm/yyyy" 
            four-digit-year 
            input-class="h-8 w-[135px]"
            @update:model-value="onDateFromChange"
          />
        </div>

        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-[#000000D9] whitespace-nowrap">Đến ngày</label>
          <SingleDatePicker 
            v-model="dateTo" 
            placeholder="dd/mm/yyyy" 
            four-digit-year 
            input-class="h-8 w-[135px]"
          />
        </div>

        <span class="w-[1px] h-5 bg-slate-200 mx-1"></span>

        <!-- Search with fast clear (x) -->
        <div class="relative flex-1 min-w-[220px]">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Tên khách, mặt hàng, địa điểm..."
            class="w-full h-8 pl-3 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] bg-white border border-slate-300 rounded-lg outline-none focus:border-blue-500 shadow-xs"
          />
          <button 
            v-if="searchQuery" 
            type="button" 
            title="Xóa tìm kiếm" 
            @click="searchQuery = ''"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>

        <button class="btn-pms-primary h-8 px-4 text-xs flex items-center gap-1.5 cursor-pointer shrink-0" type="button" @click="openCreateModal">
          <Plus :size="14" />
          <span>Thêm phiếu</span>
        </button>
      </div>

      <!-- Table Wrap -->
      <div class="table-wrap flex-1 min-h-0 overflow-auto bg-white border border-slate-200 rounded-lg shadow-xs">
        <table class="w-full min-w-[1280px] border-collapse text-xs">
          <thead class="sticky top-0 z-10">
            <tr class="bg-slate-100 text-xs text-[#000000D9]">
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200 w-12">STT</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Tình Trạng</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Thông Tin Khách</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Mặt Hàng</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Ngày Báo Cáo</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Ngày Tìm Thấy</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Người Tìm Thấy</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Khu Vực</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Kho Lưu Trữ</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Ngày Nhận</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Người Giao</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Người Nhận</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200">Người Tạo</th>
              <th class="py-2.5 px-3 text-center align-middle font-semibold border border-slate-200 w-24">Thao Tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="isLoading">
              <td class="empty-cell py-10 text-center text-slate-400 text-xs" colspan="14">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="!paginatedItems.length">
              <td class="empty-cell py-10 text-center text-slate-400 text-xs" colspan="14">Không có dữ liệu</td>
            </tr>
            <tr v-for="(item, index) in paginatedItems" v-else :key="item.id" class="hover:bg-blue-50/50 transition-colors border-b border-slate-100">
              <td class="py-2 px-3 text-center align-middle text-slate-500 font-normal">{{ (currentPage - 1) * PAGE_SIZE + index + 1 }}</td>
              <td class="py-2 px-3 text-center align-middle">
                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold" :class="item.status === 'found' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'">
                  {{ item.status === 'found' ? 'Found' : 'Lost' }}
                </span>
              </td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.guest_info || '—' }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-semibold">{{ item.item_found }}</td>
              <td class="py-2 px-3 text-center align-middle text-[#000000D9] font-normal">{{ formatDate(item.date_reported) }}</td>
              <td class="py-2 px-3 text-center align-middle text-[#000000D9] font-normal">{{ formatDate(item.date_found) }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.who_found || '—' }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.where_found || '—' }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.storage_location || '—' }}</td>
              <td class="py-2 px-3 text-center align-middle text-[#000000D9] font-normal">{{ formatDate(item.date_handling) }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.delieved_handling || '—' }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.received_handling || '—' }}</td>
              <td class="py-2 px-3 align-middle text-[#000000D9] font-normal">{{ item.created_by || '—' }}</td>
              <td class="py-2 px-3 text-center align-middle">
                <div class="flex items-center justify-center gap-1.5">
                  <button class="w-7 h-7 rounded text-blue-600 hover:bg-blue-100 flex items-center justify-center transition-colors cursor-pointer border-none bg-transparent" type="button" title="Sửa" @click="openEditModal(item)">
                    <Pencil :size="14" />
                  </button>
                  <button class="w-7 h-7 rounded text-rose-500 hover:bg-rose-100 flex items-center justify-center transition-colors cursor-pointer border-none bg-transparent" type="button" title="Xóa" @click="deleteItem(item)">
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <footer class="pager-row flex items-center justify-between text-xs text-slate-600 shrink-0 px-1 py-1">
        <span>Hiển thị {{ paginatedItems.length }} / {{ filteredItems.length }} phiếu</span>
        <div class="pager flex items-center gap-1">
          <button type="button" title="Trang trước" :disabled="currentPage === 1" @click="currentPage--" class="min-w-7 h-7 px-1.5 flex items-center justify-center rounded border border-slate-300 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer text-xs">
            <ChevronLeft :size="14" />
          </button>
          <button v-for="page in totalPages" :key="page" type="button" :class="currentPage === page ? 'btn-pms-primary text-white font-semibold' : 'bg-white border-slate-300 hover:bg-slate-50 text-[#000000D9]'" @click="currentPage = page" class="min-w-7 h-7 px-2 flex items-center justify-center rounded border text-xs cursor-pointer">
            {{ page }}
          </button>
          <button type="button" title="Trang sau" :disabled="currentPage === totalPages" @click="currentPage++" class="min-w-7 h-7 px-1.5 flex items-center justify-center rounded border border-slate-300 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer text-xs">
            <ChevronRight :size="14" />
          </button>
        </div>
      </footer>
    </main>

    <!-- Modal Thêm / Chỉnh Sửa Phiếu Lost & Found -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 md:p-4" @click.self="closeModal()">
        <section 
          class="modal-box bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-300 text-xs font-['Roboto',sans-serif]" 
          role="dialog" 
          aria-modal="true" 
          aria-labelledby="lost-found-modal-title"
          :style="{ transform: 'translate(' + modalPos.x + 'px, ' + modalPos.y + 'px)' }"
        >
          <LoadingOverlay :show="isSaving" />

          <!-- Modal Header with theme color (Draggable) -->
          <header 
            class="modal-header px-4 py-2.5 flex items-center justify-between font-semibold shrink-0 shadow-xs cursor-move select-none" 
            :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
            @mousedown="startDragModal"
          >
            <div class="flex items-center gap-2 text-white">
              <FileText :size="16" />
              <strong id="lost-found-modal-title" class="text-sm font-semibold text-white">
                {{ isEditing ? 'Chỉnh Sửa Phiếu Lost & Found' : 'Thêm Phiếu Lost & Found' }}
              </strong>
            </div>
            <button 
              type="button" 
              title="Đóng" 
              @click="closeModal()" 
              @mousedown.stop
              class="text-white hover:bg-white/20 p-1 rounded transition-colors cursor-pointer border-none bg-transparent"
            >
              <X :size="18" />
            </button>
          </header>

          <!-- Modal Body -->
          <div class="modal-body flex-1 overflow-y-auto p-4 flex flex-col gap-3.5 bg-white">
            <!-- Status Picker -->
            <div class="status-picker flex gap-2.5">
              <button 
                type="button" 
                :class="form.status === 'lost' ? 'border-rose-500 bg-rose-50 text-rose-600 font-bold' : 'border-slate-300 bg-white text-slate-600 font-semibold'" 
                @click="setStatus('lost')" 
                class="flex items-center gap-2 px-4 py-1.5 rounded-lg border text-xs cursor-pointer select-none transition-colors"
              >
                <span class="w-2.5 h-2.5 rounded-full" :class="form.status === 'lost' ? 'bg-rose-500' : 'bg-slate-300'"></span>
                Lost (thất lạc)
              </button>
              <button 
                type="button" 
                :class="form.status === 'found' ? 'border-emerald-500 bg-emerald-50 text-emerald-600 font-bold' : 'border-slate-300 bg-white text-slate-600 font-semibold'" 
                @click="setStatus('found')" 
                class="flex items-center gap-2 px-4 py-1.5 rounded-lg border text-xs cursor-pointer select-none transition-colors"
              >
                <span class="w-2.5 h-2.5 rounded-full" :class="form.status === 'found' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                Found (tìm thấy)
              </button>
            </div>

            <!-- Group 1: Thông tin chung -->
            <section class="form-section flex flex-col gap-2.5">
              <h4 class="text-xs font-bold text-[#000000D9] pb-1 border-b border-slate-200 uppercase tracking-wide">
                Thông tin chung
              </h4>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <!-- Ngày báo cáo (Bắt buộc - nền vàng viền vàng) -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Ngày báo cáo <span class="text-red-500">*</span></label>
                  <SingleDatePicker 
                    v-model="form.date_reported" 
                    placeholder="dd/mm/yyyy" 
                    four-digit-year 
                    input-class="input-required h-8 w-full"
                  />
                </div>

                <!-- Thông tin khách -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Thông tin khách</label>
                  <div class="relative">
                    <input 
                      v-model="form.guest_info" 
                      type="text" 
                      placeholder="Tên, số phòng, điện thoại..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.guest_info" 
                      type="button" 
                      title="Xóa" 
                      @click="form.guest_info = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Mặt hàng (Bắt buộc - nền vàng viền vàng) -->
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-[#000000D9]">Mặt hàng <span class="text-red-500">*</span></label>
                <div class="relative">
                  <input 
                    v-model="form.item_found" 
                    type="text" 
                    placeholder="VD: iPhone 15, Ví da nâu..."
                    class="input-required h-8 w-full rounded-lg px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-amber-500 shadow-xs"
                  />
                  <button 
                    v-if="form.item_found" 
                    type="button" 
                    title="Xóa" 
                    @click="form.item_found = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <!-- Ngày tìm thấy -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Ngày tìm thấy</label>
                  <SingleDatePicker 
                    v-model="form.date_found" 
                    placeholder="dd/mm/yyyy" 
                    four-digit-year 
                    input-class="h-8 w-full"
                  />
                </div>

                <!-- Người tìm thấy -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Người tìm thấy</label>
                  <div class="relative">
                    <input 
                      v-model="form.who_found" 
                      type="text" 
                      placeholder="Tên nhân viên tìm thấy..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.who_found" 
                      type="button" 
                      title="Xóa" 
                      @click="form.who_found = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>

              <!-- Người nhận quản lý -->
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-[#000000D9]">Người nhận quản lý</label>
                <div class="relative">
                  <input 
                    v-model="form.received" 
                    type="text" 
                    placeholder="Nhân viên tiếp nhận..."
                    class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                  />
                  <button 
                    v-if="form.received" 
                    type="button" 
                    title="Xóa" 
                    @click="form.received = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>

              <!-- Địa điểm tìm thấy -->
              <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-[#000000D9]">Địa điểm tìm thấy</label>
                <div class="relative">
                  <input 
                    v-model="form.where_found" 
                    type="text" 
                    placeholder="Phòng, sảnh, nhà hàng..."
                    class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                  />
                  <button 
                    v-if="form.where_found" 
                    type="button" 
                    title="Xóa" 
                    @click="form.where_found = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <!-- Kho lưu trữ -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Kho lưu trữ</label>
                  <div class="relative">
                    <input 
                      v-model="form.storage_location" 
                      type="text" 
                      placeholder="Tủ đồ, kho buồng phòng..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.storage_location" 
                      type="button" 
                      title="Xóa" 
                      @click="form.storage_location = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Ghi chú -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Ghi chú</label>
                  <div class="relative">
                    <input 
                      v-model="form.remarks" 
                      type="text" 
                      placeholder="Mô tả thêm, đặc điểm nhận dạng..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.remarks" 
                      type="button" 
                      title="Xóa" 
                      @click="form.remarks = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- Group 2: Thông tin giao nhận -->
            <section class="form-section flex flex-col gap-2.5">
              <h4 class="text-xs font-bold text-[#000000D9] pb-1 border-b border-slate-200 uppercase tracking-wide">
                Thông tin giao nhận
              </h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <!-- Ngày nhận -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Ngày nhận</label>
                  <SingleDatePicker 
                    v-model="form.date_handling" 
                    placeholder="dd/mm/yyyy" 
                    four-digit-year 
                    input-class="h-8 w-full"
                  />
                </div>

                <!-- Phương thức xử lý -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Phương thức xử lý</label>
                  <div class="relative">
                    <input 
                      v-model="form.method_handling" 
                      type="text" 
                      placeholder="Đã trả khách, tiêu hủy, chuyển tiếp..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.method_handling" 
                      type="button" 
                      title="Xóa" 
                      @click="form.method_handling = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <!-- Người giao -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Người giao</label>
                  <div class="relative">
                    <input 
                      v-model="form.delieved_handling" 
                      type="text" 
                      placeholder="Người bàn giao đồ vật..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.delieved_handling" 
                      type="button" 
                      title="Xóa" 
                      @click="form.delieved_handling = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Người nhận -->
                <div class="flex flex-col gap-1">
                  <label class="text-xs font-semibold text-[#000000D9]">Người nhận</label>
                  <div class="relative">
                    <input 
                      v-model="form.received_handling" 
                      type="text" 
                      placeholder="Người nhận lại đồ..."
                      class="h-8 w-full rounded-lg border border-slate-300 bg-white px-2.5 pr-8 text-xs font-normal text-[#000000D9] placeholder-[#A8B0BF] outline-none focus:border-blue-500 shadow-xs"
                    />
                    <button 
                      v-if="form.received_handling" 
                      type="button" 
                      title="Xóa" 
                      @click="form.received_handling = ''"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 cursor-pointer border-none bg-transparent p-0.5"
                    >
                      <X class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </section>

            <!-- Group 3: Hình ảnh đồ vật -->
            <section class="form-section flex flex-col gap-2">
              <h4 class="text-xs font-bold text-[#000000D9] pb-1 border-b border-slate-200 uppercase tracking-wide">
                Hình ảnh đồ vật
              </h4>

              <div class="image-zone min-h-[78px] flex flex-wrap items-center gap-2 p-2.5 border-1.5 border-dashed border-slate-300 rounded-lg bg-slate-50/50">
                <div v-for="(image, index) in images" :key="`${index}-${image.slice(-16)}`" class="image-wrap relative">
                  <img :src="image" alt="Hình ảnh đồ vật" class="w-16 h-16 object-cover border border-slate-200 rounded cursor-zoom-in shadow-xs" @click="lightboxImage = image">
                  <button type="button" title="Xóa ảnh" @click="removeImage(index)" class="absolute -top-1.5 -right-1.5 w-4.5 h-4.5 flex items-center justify-center bg-rose-500 text-white rounded-full border-none cursor-pointer hover:bg-rose-600 transition-colors">
                    <X :size="10" />
                  </button>
                </div>
                <button class="image-add w-16 h-16 flex flex-col items-center justify-center gap-1 bg-white border border-dashed border-slate-300 rounded hover:border-blue-500 hover:text-blue-600 text-slate-400 text-xs cursor-pointer transition-colors" type="button" @click="fileInput?.click()">
                  <Camera :size="16" />
                  <span class="text-xs font-normal">Thêm ảnh</span>
                </button>
                <input ref="fileInput" hidden type="file" accept="image/*" multiple @change="handleImages">
              </div>
            </section>
          </div>

          <!-- Modal Footer -->
          <footer class="modal-footer flex items-center justify-end gap-2 p-3 bg-slate-50 border-t border-slate-200 shrink-0">
            <button class="btn-pms-close h-8 px-4 text-xs flex items-center gap-1.5 cursor-pointer" type="button" @click="closeModal()">
              <X :size="14" />
              <span>Hủy</span>
            </button>
            <button class="btn-pms-primary h-8 px-4 text-xs flex items-center gap-1.5 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="isSaving" @click="saveItem">
              <span v-if="isSaving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <template v-else>
                <Save v-if="isEditing" :size="14" />
                <Plus v-else :size="14" />
              </template>
              <span>{{ isSaving ? 'Đang lưu...' : isEditing ? 'Lưu' : 'Thêm' }}</span>
            </button>
          </footer>
        </section>
      </div>

      <!-- Lightbox preview -->
      <div v-if="lightboxImage" class="lightbox fixed inset-0 z-[2000] flex items-center justify-center bg-black/80 cursor-zoom-out p-4" @click="lightboxImage = ''">
        <img :src="lightboxImage" alt="Xem hình ảnh đồ vật" class="max-w-[90vw] max-h-[90vh] rounded shadow-2xl">
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* Scoped custom scrollbar */
::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

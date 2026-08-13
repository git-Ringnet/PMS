<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  Camera,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  FileText,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  Trash2,
  X,
} from '@lucide/vue'
import http from '@/services/http'
import { fetchSystemDate } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

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
  return year && month && day ? `${day}/${month}/${year.slice(-2)}` : value
}

const DateField = defineComponent({
  name: 'DateField',
  props: {
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const nativeInput = ref(null)
    const openPicker = () => {
      if (typeof nativeInput.value?.showPicker === 'function') nativeInput.value.showPicker()
      else nativeInput.value?.click()
    }

    return () => h('div', { class: 'date-field' }, [
      h('input', {
        class: 'date-display',
        type: 'text',
        value: formatDate(props.modelValue),
        placeholder: 'dd/mm/yy',
        readonly: true,
        onClick: openPicker,
      }),
      h('button', { class: 'date-trigger', type: 'button', title: 'Chọn ngày', onClick: openPicker }, [
        h(CalendarDays, { size: 15 }),
      ]),
      h('input', {
        ref: nativeInput,
        class: 'native-date-picker',
        type: 'date',
        value: props.modelValue,
        tabindex: -1,
        onInput: (event) => emit('update:modelValue', event.target.value),
      }),
    ])
  },
})

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
  <div class="lost-found-page">
    <LoadingOverlay :show="isLoading || isDeleting" />

    <main class="content">
      <div class="filter-bar">
        <label for="lost-found-status">Tình trạng</label>
        <select id="lost-found-status" v-model="statusFilter">
          <option value="">Tất cả</option>
          <option value="lost">Lost</option>
          <option value="found">Found</option>
        </select>
        <span class="filter-sep"></span>
        <label for="lost-found-from">Từ ngày</label>
        <DateField id="lost-found-from" v-model="dateFrom" @update:model-value="onDateFromChange" />
        <label for="lost-found-to">Đến ngày</label>
        <DateField id="lost-found-to" v-model="dateTo" />
        <span class="filter-sep"></span>
        <input v-model="searchQuery" data-hk-search class="filter-search" type="search" placeholder="Tên khách, mặt hàng, địa điểm...">
        <button class="btn btn-ghost btn-sm" type="button" @click="resetFilters">
          <RotateCcw :size="13" /> Đặt lại
        </button>
        <button class="btn btn-primary add-button" type="button" @click="openCreateModal">
          <Plus :size="14" /> Thêm phiếu
        </button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th class="center index-column">#</th>
              <th>Tình trạng</th>
              <th>Thông tin khách</th>
              <th>Mặt hàng</th>
              <th>Ngày báo cáo</th>
              <th>Ngày tìm thấy</th>
              <th>Người tìm thấy</th>
              <th>Khu vực</th>
              <th>Kho</th>
              <th>Ngày nhận</th>
              <th>Người giao</th>
              <th>Người nhận</th>
              <th>User tạo</th>
              <th class="center action-column">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td class="empty-cell" colspan="14">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="!paginatedItems.length">
              <td class="empty-cell" colspan="14">Không có dữ liệu</td>
            </tr>
            <tr v-for="(item, index) in paginatedItems" v-else :key="item.id">
              <td class="center">{{ (currentPage - 1) * PAGE_SIZE + index + 1 }}</td>
              <td><span class="badge" :class="`badge-${item.status}`">{{ item.status === 'found' ? 'Found' : 'Lost' }}</span></td>
              <td>{{ item.guest_info || '' }}</td>
              <td>{{ item.item_found }}</td>
              <td>{{ formatDate(item.date_reported) }}</td>
              <td>{{ formatDate(item.date_found) }}</td>
              <td>{{ item.who_found || '' }}</td>
              <td>{{ item.where_found || '' }}</td>
              <td>{{ item.storage_location || '' }}</td>
              <td>{{ formatDate(item.date_handling) }}</td>
              <td>{{ item.delieved_handling || '' }}</td>
              <td>{{ item.received_handling || '' }}</td>
              <td>{{ item.created_by || '' }}</td>
              <td class="center">
                <div class="action-buttons">
                  <button class="btn btn-edit btn-icon" type="button" title="Sửa" @click="openEditModal(item)"><Pencil :size="13" /></button>
                  <button class="btn btn-danger btn-icon" type="button" title="Xóa" @click="deleteItem(item)"><Trash2 :size="13" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer class="pager-row">
        <span>Hiển thị {{ paginatedItems.length }} / {{ filteredItems.length }} phiếu</span>
        <div class="pager">
          <button type="button" title="Trang trước" :disabled="currentPage === 1" @click="currentPage--"><ChevronLeft :size="14" /></button>
          <button v-for="page in totalPages" :key="page" type="button" :class="{ active: currentPage === page }" @click="currentPage = page">{{ page }}</button>
          <button type="button" title="Trang sau" :disabled="currentPage === totalPages" @click="currentPage++"><ChevronRight :size="14" /></button>
        </div>
      </footer>
    </main>

    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal()">
        <section class="modal-box" role="dialog" aria-modal="true" aria-labelledby="lost-found-modal-title">
          <LoadingOverlay :show="isSaving" />

          <header class="modal-header">
            <FileText :size="15" />
            <strong id="lost-found-modal-title">{{ isEditing ? 'Chỉnh sửa phiếu' : 'Thêm phiếu Lost & Found' }}</strong>
            <button type="button" title="Đóng" @click="closeModal()"><X :size="18" /></button>
          </header>

          <div class="modal-body">
            <div class="status-picker">
              <button type="button" :class="{ 'selected-lost': form.status === 'lost' }" @click="setStatus('lost')"><span></span>Lost (thất lạc)</button>
              <button type="button" :class="{ 'selected-found': form.status === 'found' }" @click="setStatus('found')"><span></span>Found (tìm thấy)</button>
            </div>

            <section class="form-section">
              <h4>Thông tin chung</h4>
              <div class="row-two">
                <label>Ngày báo cáo *<em></em><DateField v-model="form.date_reported" /></label>
                <label>Thông tin khách<input v-model="form.guest_info" type="text" placeholder="Tên, số phòng, điện thoại..."></label>
              </div>
              <label>Mặt hàng *<em></em><input v-model="form.item_found" type="text" placeholder="VD: iPhone 15, Ví da nâu..."></label>
              <div class="row-two">
                <label>Ngày tìm thấy<DateField v-model="form.date_found" /></label>
                <label>Người tìm thấy<input v-model="form.who_found" type="text"></label>
              </div>
              <label>Người nhận quản lý<input v-model="form.received" type="text"></label>
              <label>Địa điểm tìm thấy<input v-model="form.where_found" type="text"></label>
              <div class="row-two">
                <label>Kho lưu trữ<input v-model="form.storage_location" type="text"></label>
                <label>Ghi chú<input v-model="form.remarks" type="text" placeholder="Mô tả thêm, đặc điểm nhận dạng..."></label>
              </div>
            </section>

            <section class="form-section">
              <h4>Thông tin giao nhận</h4>
              <div class="row-two">
                <label>Ngày nhận<DateField v-model="form.date_handling" /></label>
                <label>Phương thức xử lý<input v-model="form.method_handling" type="text"></label>
              </div>
              <div class="row-two">
                <label>Người giao<input v-model="form.delieved_handling" type="text" placeholder="Người bàn giao đồ vật..."></label>
                <label>Người nhận<input v-model="form.received_handling" type="text"></label>
              </div>
            </section>

            <section class="form-section image-section">
              <h4>Hình ảnh đồ vật</h4>
              <div class="image-zone">
                <div v-for="(image, index) in images" :key="`${index}-${image.slice(-16)}`" class="image-wrap">
                  <img :src="image" alt="Hình ảnh đồ vật" @click="lightboxImage = image">
                  <button type="button" title="Xóa ảnh" @click="removeImage(index)"><X :size="11" /></button>
                </div>
                <button class="image-add" type="button" @click="fileInput?.click()"><Camera :size="17" /><span>Thêm ảnh</span></button>
                <input ref="fileInput" hidden type="file" accept="image/*" multiple @change="handleImages">
              </div>
            </section>
          </div>

          <footer class="modal-footer">
            <button class="btn btn-cancel" type="button" @click="closeModal()">Hủy</button>
            <button class="btn btn-primary" type="button" :disabled="isSaving" @click="saveItem">
              <Save v-if="isEditing" :size="14" /><Plus v-else :size="14" />
              {{ isSaving ? 'Đang lưu...' : isEditing ? 'Lưu' : 'Thêm' }}
            </button>
          </footer>
        </section>
      </div>

      <div v-if="lightboxImage" class="lightbox" @click="lightboxImage = ''">
        <img :src="lightboxImage" alt="Xem hình ảnh đồ vật">
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.lost-found-page {
  --lf-bg: #f0f2f5;
  --lf-white: #fff;
  --lf-border: #dde2ea;
  --lf-border-strong: #c8d0dc;
  --lf-accent: #2f6fed;
  --lf-text: #1a2638;
  --lf-text-secondary: #536b8a;
  --lf-text-muted: #8fa3be;
  --lf-red: #e53935;
  --lf-green: #16a34a;
  height: 100%;
  position: relative;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--lf-bg);
  color: var(--lf-text);
  font: 13px/1.5 "Segoe UI", sans-serif;
}

button, input, select { font-family: inherit; }
.content { flex: 1; min-height: 0; overflow: hidden; display: flex; flex-direction: column; gap: 10px; padding: 14px 16px; }
.filter-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 9px 12px; background: white; border: 1px solid var(--lf-border); border-radius: 6px; box-shadow: 0 1px 4px rgb(0 0 0 / 8%); }
.filter-bar label { font-size: 11px; white-space: nowrap; }
.filter-bar select, .filter-bar input { height: 30px; border: 1px solid var(--lf-border-strong); border-radius: 6px; background: white; color: var(--lf-text); font-size: 12.5px; outline: none; }
.filter-bar select { width: 110px; padding: 0 8px; }
.filter-bar input:focus, .filter-bar select:focus { border-color: var(--lf-accent); }
.filter-bar .date-field { width: 112px; }
.filter-search { flex: 1; min-width: 160px; padding: 0 10px; }
.filter-sep { width: 1px; height: 20px; margin: 0 2px; background: var(--lf-border); }
.btn { min-height: 30px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; padding: 6px 14px; border: 0; border-radius: 6px; font-size: 12px; font-weight: 600; line-height: 1; cursor: pointer; white-space: nowrap; }
.btn:hover { opacity: .85; }
.btn:disabled { cursor: wait; opacity: .6; }
.btn-primary { background: var(--lf-accent); color: white; }
.btn-primary:hover { background: #1d4ed8; color: white; opacity: 1; }
.btn-ghost { background: transparent; color: var(--lf-text-secondary); border: 1px solid var(--lf-border-strong); }
.btn-ghost:hover { background: #f0f4fa; opacity: 1; }
.btn-danger { background: var(--lf-red); color: white; }
.btn-cancel { background: #dc2626; color: white; border: 1px solid #dc2626; }
.btn-cancel:hover { background: #b91c1c; border-color: #b91c1c; color: white; opacity: 1; }
.btn-edit { background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; }
.btn-edit:hover { background: #dbeafe; border-color: #3b82f6; color: #1e40af; opacity: 1; }
.btn-sm { min-height: 26px; padding: 4px 10px; font-size: 11px; }
.btn-icon { width: 28px; min-height: 26px; padding: 5px; }
.add-button { height: 34px; }
.table-wrap { flex: 1; min-height: 0; overflow: auto; background: white; border: 1px solid var(--lf-border); border-radius: 6px; box-shadow: 0 1px 4px rgb(0 0 0 / 8%); }
table { width: 100%; min-width: 1280px; border-collapse: collapse; }
thead { position: sticky; top: 0; z-index: 2; }
th { padding: 8px 10px; background: #f5f7fb; border-bottom: 2px solid var(--lf-border-strong); color: var(--lf-text); font-size: 11px; font-weight: 600; text-align: left; white-space: nowrap; }
td { padding: 7px 10px; border-bottom: 1px solid var(--lf-border); color: var(--lf-text); font-size: 12.5px; vertical-align: middle; white-space: nowrap; }
tbody tr:hover td { background: #f5f8ff; }
.center { text-align: center; }
.index-column { width: 34px; }
.action-column { width: 70px; }
.empty-cell { padding: 40px; color: var(--lf-text-muted); text-align: center; }
.badge { display: inline-flex; padding: 2px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; line-height: 1.6; }
.badge-lost { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }
.badge-found { background: #f0fdf4; color: #16a34a; border: 1px solid #86efac; }
.action-buttons { display: flex; justify-content: center; gap: 4px; }
.pager-row { display: flex; align-items: center; flex-shrink: 0; color: var(--lf-text-secondary); font-size: 12px; }
.pager { margin-left: auto; display: flex; align-items: center; gap: 5px; }
.pager button { min-width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; background: white; border: 1px solid var(--lf-border-strong); border-radius: 3px; color: var(--lf-text-secondary); font-size: 11px; cursor: pointer; }
.pager button:hover:not(:disabled) { background: #f0f4fa; }
.pager button.active { background: var(--lf-accent); border-color: var(--lf-accent); color: white; }
.pager button:disabled { cursor: default; opacity: .4; }

.modal-overlay { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgb(20 30 50 / 40%); }
.modal-box { --lf-accent: #2f6fed; --lf-text-secondary: #536b8a; --lf-border-strong: #c8d0dc; position: relative; width: 800px; max-width: 97vw; max-height: 93vh; display: flex; flex-direction: column; overflow: hidden; background: white; border-radius: 10px; box-shadow: 0 6px 28px rgb(0 0 0 / 18%); color: #1a2638; font: 13px/1.5 "Segoe UI", sans-serif; }
.modal-header { display: flex; align-items: center; gap: 8px; flex-shrink: 0; padding: 11px 16px; background: #1e2d4a; color: #7a9bbf; }
.modal-header strong { flex: 1; color: white; font-size: 13.5px; }
.modal-header button { display: flex; padding: 2px 7px; background: transparent; border: 0; border-radius: 4px; color: #7a9bbf; cursor: pointer; }
.modal-header button:hover { background: rgb(255 255 255 / 15%); color: white; }
.modal-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; padding: 16px 18px; background: white; }
.status-picker { display: flex; gap: 10px; }
.status-picker button { display: flex; align-items: center; gap: 7px; padding: 7px 16px; background: white; border: 1.5px solid #c8d0dc; border-radius: 6px; color: #536b8a; font-size: 12.5px; font-weight: 600; cursor: pointer; }
.status-picker button > span { width: 9px; height: 9px; background: #c8d0dc; border-radius: 50%; }
.status-picker .selected-lost { background: #fff5f5; border-color: #e53935; color: #e53935; }
.status-picker .selected-lost > span { background: #e53935; }
.status-picker .selected-found { background: #f0fdf4; border-color: #16a34a; color: #16a34a; }
.status-picker .selected-found > span { background: #16a34a; }
.form-section { display: flex; flex-direction: column; gap: 10px; }
.form-section h4 { margin: 0; padding-bottom: 5px; border-bottom: 1.5px solid #dde2ea; font-size: 11px; text-transform: uppercase; letter-spacing: 0; }
.form-section label { display: flex; flex-direction: column; gap: 3px; color: #1a2638; font-size: 11.5px; font-weight: 600; }
.form-section em { color: #e53935; font-style: normal; }
.form-section input { width: 100%; height: 34px; padding: 0 10px; background: white; border: 1px solid #c8d0dc; border-radius: 6px; color: #1a2638; font-size: 12.5px; outline: none; }
.form-section input:focus { border-color: #2f6fed; box-shadow: 0 0 0 3px rgb(47 111 237 / 10%); }
.date-field { position: relative; width: 100%; }
.date-field :deep(.date-display) { width: 100%; height: 34px; padding: 0 36px 0 10px; background: white; border: 1px solid #c8d0dc; border-radius: 6px; color: #1a2638; font-size: 12.5px; outline: none; cursor: pointer; }
.filter-bar .date-field :deep(.date-display) { height: 30px; }
.date-field :deep(.date-display:focus) { border-color: #2f6fed; box-shadow: 0 0 0 3px rgb(47 111 237 / 10%); }
.date-field :deep(.date-trigger) { position: absolute; top: 50%; right: 4px; width: 28px; height: 26px; display: flex; align-items: center; justify-content: center; padding: 0; transform: translateY(-50%); background: transparent; border: 0; border-radius: 4px; color: #536b8a; cursor: pointer; }
.date-field :deep(.date-trigger:hover) { background: #f0f4fa; color: #2f6fed; }
.date-field :deep(.native-date-picker) { position: absolute; right: 0; bottom: 0; width: 1px !important; height: 1px !important; padding: 0 !important; border: 0 !important; opacity: 0; pointer-events: none; }
.row-two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.image-section { gap: 8px; }
.image-zone { min-height: 78px; display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px; padding: 10px; border: 1.5px dashed #c8d0dc; border-radius: 6px; }
.image-wrap { position: relative; }
.image-wrap img { width: 62px; height: 62px; object-fit: cover; border: 1px solid #dde2ea; border-radius: 4px; cursor: zoom-in; }
.image-wrap button { position: absolute; top: -5px; right: -5px; width: 17px; height: 17px; display: flex; align-items: center; justify-content: center; padding: 0; background: #e53935; border: 0; border-radius: 50%; color: white; cursor: pointer; }
.image-add { width: 62px; height: 62px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; background: white; border: 1px dashed #c8d0dc; border-radius: 4px; color: #8fa3be; font-size: 10px; cursor: pointer; }
.image-add:hover { border-color: #2f6fed; color: #2f6fed; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; padding: 11px 18px; background: white; border-top: 1px solid #dde2ea; }
.lightbox { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; background: rgb(0 0 0 / 82%); cursor: zoom-out; }
.lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 4px; }

:global(html.dark .lost-found-page input),
:global(html.dark .lost-found-page select),
:global(html.dark .modal-box input) {
  background-color: white !important;
  border-color: #c8d0dc !important;
  color: #1a2638 !important;
  color-scheme: light;
}
:global(html.dark .lost-found-page input::placeholder),
:global(html.dark .modal-box input::placeholder) {
  color: #8fa3be !important;
}

@media (max-width: 720px) {
  .content { padding: 10px; }
  .filter-sep { display: none; }
  .filter-search { min-width: 100%; }
  .row-two { grid-template-columns: 1fr; }
  .modal-box { max-height: 96vh; }
}
</style>

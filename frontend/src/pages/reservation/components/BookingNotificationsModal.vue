<template>
  <div v-if="show" class="fixed inset-0 z-[99998] flex items-center justify-center bg-black/25 p-4 animate-in" @click.self="close">
    <section 
      class="flex max-h-[calc(100vh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- MODAL HEADER -->
      <header 
        class="flex items-center justify-between px-5 py-2.5 text-white select-none shrink-0 rounded-t-xl cursor-move"
        :style="{ background: topbarThemeBg, color: 'var(--pms-custom-theme-text, #ffffff)' }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-semibold text-xs uppercase tracking-wider">
          <i class="fa-solid fa-bell"></i>
          <span>Thông báo booking</span>
        </div>
        <button 
          class="hover:opacity-80 p-1 rounded cursor-pointer border-none bg-transparent text-white" 
          aria-label="Đóng" 
          @click="close"
          title="Đóng"
        >
          <i class="fa-solid fa-xmark text-sm"></i>
        </button>
      </header>

      <!-- MODAL BODY -->
      <div class="overflow-y-auto p-5 text-xs font-normal text-[#000000D9]">
        <!-- Thông tin đăng ký -->
        <section class="border-b border-slate-200 pb-3">
          <h3 class="mb-2 font-semibold text-xs text-[#000000D9]">Thông tin đăng ký</h3>
          <div class="grid gap-2 text-xs text-[#000000D9] md:grid-cols-2">
            <p>Tên đăng ký: <strong class="font-semibold">{{ booking?.bookingName || '—' }}</strong></p>
            <p>Người dùng: <strong class="font-semibold">{{ userName }}</strong></p>
            <p class="md:col-start-2">Ngày đến/ngày đi: <strong class="font-semibold">{{ formatDate(booking?.checkIn) }} - {{ formatDate(booking?.checkOut) }}</strong></p>
          </div>
        </section>

        <!-- Form nhập thông báo -->
        <section class="grid gap-4 border-b border-slate-200 py-3.5 lg:grid-cols-[1.15fr_0.9fr]">
          <div>
            <h3 class="mb-2 font-semibold text-xs text-[#000000D9]">Thông báo</h3>
            <div class="mb-3 flex gap-5 text-xs text-[#000000D9]">
              <label class="flex cursor-pointer items-center gap-2 font-normal">
                <input v-model="form.scope_type" type="radio" value="booking" class="text-sky-500 focus:ring-sky-500"> Đăng ký
              </label>
              <label class="flex cursor-pointer items-center gap-2 font-normal">
                <input v-model="form.scope_type" type="radio" value="room" class="text-sky-500 focus:ring-sky-500"> Phòng
              </label>
            </div>
            
            <div v-if="form.scope_type === 'booking'" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
              Thông báo sẽ áp dụng cho toàn bộ đăng ký này.
            </div>
            
            <div v-else class="relative">
              <button 
                type="button" 
                class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-1.5 text-left text-xs bg-white hover:border-sky-400" 
                @click="roomDropdownOpen = !roomDropdownOpen"
              >
                <span><strong class="font-semibold text-[#000000D9]">Chọn phòng</strong><span class="ml-2 text-slate-500">{{ appliedRoomIds.length ? `(${appliedRoomIds.length} phòng)` : '(chưa chọn)' }}</span></span>
                <i class="fa-solid fa-chevron-down text-slate-400 text-xs"></i>
              </button>
              <div v-if="roomDropdownOpen" class="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white p-3 shadow-xl">
                <label class="flex cursor-pointer items-center gap-2 border-b border-slate-100 pb-2 text-xs font-semibold text-[#000000D9]">
                  <input type="checkbox" :checked="allRoomsSelected" @change="toggleAllRooms" class="rounded text-sky-500"> Tất cả
                </label>
                <div class="max-h-40 overflow-y-auto py-1 text-xs">
                  <label v-for="room in rooms" :key="room.id" class="flex cursor-pointer items-center gap-2 py-1.5 text-[#000000D9] hover:bg-slate-50 px-1 rounded">
                    <input v-model="pickerRoomIds" type="checkbox" :value="String(room.id)" class="rounded text-sky-500"> Phòng <strong class="font-semibold">{{ room.roomNumber || 'Chưa gán số phòng' }}</strong>
                  </label>
                  <p v-if="!rooms.length" class="py-2 text-slate-400">Đăng ký chưa có phòng để chọn.</p>
                </div>
                <button type="button" class="btn-pms-primary !h-7 !text-xs mt-2 w-full" @click="applyRoomSelection">Lưu</button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-[#000000D9] mb-1">Mô tả <span class="text-red-500">*</span></label>
            <textarea 
              v-model.trim="form.description" 
              rows="4" 
              maxlength="5000" 
              placeholder="Nhập nội dung thông báo..." 
              class="w-full resize-y rounded-lg border border-[#F1DD8A] bg-[#FFF8DB] px-3 py-2 text-xs text-[#000000D9] outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 placeholder:text-slate-400 placeholder:font-normal" 
            />
          </div>

          <div class="flex flex-wrap gap-4 lg:col-span-2">
            <div>
              <label class="block text-xs font-semibold text-[#000000D9] mb-1">Ngày bắt đầu <span class="text-red-500">*</span></label>
              <SingleDatePicker
                v-model="form.starts_on"
                placeholder="dd/mm/yy"
                input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-slate-200 !text-xs !font-normal"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-[#000000D9] mb-1">Ngày kết thúc <span class="text-red-500">*</span></label>
              <SingleDatePicker
                v-model="form.ends_on"
                :min-date="form.starts_on"
                placeholder="dd/mm/yy"
                input-class="!h-[32px] !py-0 !px-2.5 !rounded-lg !border-slate-200 !text-xs !font-normal"
              />
            </div>
          </div>
        </section>

        <!-- Danh sách thông báo -->
        <section class="pt-3">
          <div class="mb-2 flex items-center justify-between">
            <h3 class="font-semibold text-xs text-[#000000D9]">Thông tin</h3>
            <span class="text-xs text-slate-500">{{ notifications.length }} thông báo</span>
          </div>
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[620px] text-left text-xs">
              <thead class="bg-slate-50 text-[#000000D9] border-b border-slate-200">
                <tr>
                  <th class="px-3 py-2.5 font-semibold">Phạm vi</th>
                  <th class="px-3 py-2.5 font-semibold">Ngày bắt đầu</th>
                  <th class="px-3 py-2.5 font-semibold">Ngày kết thúc</th>
                  <th class="px-3 py-2.5 font-semibold">Mô tả</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="4" class="px-3 py-6 text-center text-slate-400">Đang tải...</td>
                </tr>
                <tr v-else-if="!notifications.length">
                  <td colspan="4" class="px-3 py-6 text-center text-slate-400">Chưa có thông báo cho đăng ký này.</td>
                </tr>
                <tr 
                  v-for="item in notifications" 
                  :key="item.id" 
                  class="cursor-pointer border-t border-slate-100 hover:bg-sky-50/60 transition-colors" 
                  :class="selectedId === item.id ? 'bg-sky-100/70 font-medium' : ''" 
                  @click="selectedId = item.id"
                >
                  <td class="px-3 py-2 text-[#000000D9]">{{ scopeLabel(item) }}</td>
                  <td class="px-3 py-2 text-[#000000D9]">{{ formatDate(item.starts_on) }}</td>
                  <td class="px-3 py-2 text-[#000000D9]">{{ formatDate(item.ends_on) }}</td>
                  <td class="max-w-96 whitespace-pre-wrap px-3 py-2 text-[#000000D9]">{{ item.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- MODAL FOOTER -->
      <footer class="flex flex-wrap justify-center gap-2.5 border-t border-slate-100 bg-slate-50 px-5 py-3 shrink-0 rounded-b-xl">
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="saving" 
          @click="addNotification"
        >
          <i class="fa-solid fa-plus"></i>
          <span>Thêm</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-secondary" 
          :disabled="!selectedId || saving" 
          @click="editSelected"
        >
          <i class="fa-solid fa-pen"></i>
          <span>Sửa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-primary" 
          :disabled="!editing || saving" 
          @click="saveEdited"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-danger" 
          :disabled="!selectedId || saving" 
          @click="remove"
        >
          <i class="fa-solid fa-trash"></i>
          <span>Xóa</span>
        </button>
        <button 
          type="button" 
          class="btn-pms-close" 
          @click="close"
        >
          <i class="fa-solid fa-xmark"></i>
          <span>Đóng</span>
        </button>
      </footer>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import { createBookingNotification, deleteBookingNotification, fetchBookingNotifications, updateBookingNotification } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const props = defineProps({
  show: Boolean,
  booking: Object,
  userName: String,
  systemDate: String
})

const emit = defineEmits(['update:show', 'saved'])

const uiStore = useUiStore()
const authStore = useAuthStore()

const topbarThemeBg = computed(() => {
  return authStore.themeColor || 'var(--pms-custom-theme, #006bdb)'
})

// Drag modal logic
const modalPos = ref({ x: 0, y: 0 })
let isDragging = false
let startX = 0
let startY = 0

function startDragModal(e) {
  if (e.target.closest('button, input, select, textarea, [data-no-drag]')) return
  isDragging = true
  startX = e.clientX - modalPos.value.x
  startY = e.clientY - modalPos.value.y
  window.addEventListener('mousemove', onDragModal)
  window.addEventListener('mouseup', stopDragModal)
}

function onDragModal(e) {
  if (!isDragging) return
  modalPos.value = {
    x: e.clientX - startX,
    y: e.clientY - startY
  }
}

function stopDragModal() {
  isDragging = false
  window.removeEventListener('mousemove', onDragModal)
  window.removeEventListener('mouseup', stopDragModal)
}

const notifications = ref([])
const selectedId = ref(null)
const loading = ref(false)
const saving = ref(false)
const editing = ref(false)
const roomDropdownOpen = ref(false)
const pickerRoomIds = ref([])
const appliedRoomIds = ref([])

const rooms = computed(() => {
  return (props.booking?.rooms || [])
    .filter(room => room.bookingRoomId)
    .map(room => ({ id: room.bookingRoomId, roomNumber: room.roomNumber }))
})

const blankForm = () => ({ 
  scope_type: 'booking', 
  booking_room_ids: [], 
  starts_on: props.booking?.checkIn ? String(props.booking.checkIn).slice(0, 10) : (props.systemDate || ''), 
  ends_on: props.booking?.checkOut ? String(props.booking.checkOut).slice(0, 10) : (props.systemDate || ''), 
  description: '' 
})

const form = ref(blankForm())
const allRoomsSelected = computed(() => rooms.value.length > 0 && pickerRoomIds.value.length === rooms.value.length)

function formatDate(value) { 
  if (!value) return '—'
  const [y, m, d] = String(value).slice(0, 10).split('-')
  return y && m && d ? `${d}/${m}/${y.slice(-2)}` : value
}

function scopeLabel(item) { 
  if (item.scope_type === 'booking') return 'Toàn đăng ký'
  return (item.booking_room_ids || [])
    .map(id => rooms.value.find(room => String(room.id) === String(id))?.roomNumber || id)
    .join(', ') || 'Phòng'
}

function resetForm() { 
  form.value = blankForm()
  pickerRoomIds.value = []
  appliedRoomIds.value = []
  selectedId.value = null
  editing.value = false
  roomDropdownOpen.value = false
}

function toggleAllRooms(event) { 
  pickerRoomIds.value = event.target.checked ? rooms.value.map(room => String(room.id)) : []
}

function applyRoomSelection() { 
  appliedRoomIds.value = [...pickerRoomIds.value]
  form.value.booking_room_ids = [...pickerRoomIds.value]
  roomDropdownOpen.value = false
}

function payload() { 
  return { 
    ...form.value, 
    booking_room_ids: form.value.scope_type === 'room' ? appliedRoomIds.value : [] 
  }
}

function validate() { 
  if (!form.value.description) return 'Vui lòng nhập mô tả thông báo.'
  if (form.value.scope_type === 'room' && !appliedRoomIds.value.length) return 'Vui lòng chọn phòng và bấm Lưu trong dropdown.'
  if (!form.value.starts_on || !form.value.ends_on) return 'Vui lòng chọn ngày bắt đầu và ngày kết thúc.'
  if (form.value.ends_on < form.value.starts_on) return 'Ngày kết thúc không được trước ngày bắt đầu.'
  return ''
}

async function load() { 
  if (!props.booking?.dbId) return
  loading.value = true
  try { 
    const res = await fetchBookingNotifications(props.booking.dbId)
    notifications.value = res.data?.data || [] 
  } catch (err) { 
    uiStore.showToast(err.response?.data?.message || 'Không thể tải thông báo.', 'error') 
  } finally { 
    loading.value = false 
  }
}

async function addNotification() { 
  const error = validate()
  if (error) return uiStore.showToast(error, 'warning')
  saving.value = true
  try { 
    await createBookingNotification(props.booking.dbId, payload())
    await load()
    resetForm()
    emit('saved')
    uiStore.showToast('Đã thêm thông báo booking.', 'success') 
  } catch (err) { 
    uiStore.showToast(err.response?.data?.message || 'Không thể thêm thông báo.', 'error') 
  } finally { 
    saving.value = false 
  }
}

function editSelected() { 
  const item = notifications.value.find(n => n.id === selectedId.value)
  if (!item) return
  form.value = { 
    scope_type: item.scope_type, 
    booking_room_ids: (item.booking_room_ids || []).map(String), 
    starts_on: String(item.starts_on).slice(0, 10), 
    ends_on: String(item.ends_on).slice(0, 10), 
    description: item.description || '' 
  }
  pickerRoomIds.value = [...form.value.booking_room_ids]
  appliedRoomIds.value = [...form.value.booking_room_ids]
  editing.value = true
}

async function saveEdited() { 
  const error = validate()
  if (error) return uiStore.showToast(error, 'warning')
  saving.value = true
  try { 
    await updateBookingNotification(props.booking.dbId, selectedId.value, payload())
    await load()
    resetForm()
    emit('saved')
    uiStore.showToast('Đã cập nhật thông báo.', 'success') 
  } catch (err) { 
    uiStore.showToast(err.response?.data?.message || 'Không thể lưu thông báo.', 'error') 
  } finally { 
    saving.value = false 
  }
}

async function remove() { 
  const ok = await uiStore.confirm({ 
    title: 'Xóa thông báo', 
    message: 'Bạn có chắc muốn xóa thông báo đã chọn?', 
    confirmText: 'Xóa', 
    cancelText: 'Hủy' 
  })
  if (!ok) return
  saving.value = true
  try { 
    await deleteBookingNotification(props.booking.dbId, selectedId.value)
    await load()
    resetForm()
    emit('saved')
    uiStore.showToast('Đã xóa thông báo.', 'success') 
  } catch (err) { 
    uiStore.showToast(err.response?.data?.message || 'Không thể xóa thông báo.', 'error') 
  } finally { 
    saving.value = false 
  }
}

function close() { 
  emit('update:show', false) 
}

function handleKeyDown(e) {
  if (e.key === 'Escape' && props.show) {
    close()
  }
}

watch(() => props.show, value => { 
  if (value) { 
    modalPos.value = { x: 0, y: 0 }
    resetForm()
    load() 
    window.addEventListener('keydown', handleKeyDown)
  } else {
    window.removeEventListener('keydown', handleKeyDown)
  }
})

watch(() => form.value.scope_type, value => { 
  if (value === 'booking') { 
    appliedRoomIds.value = []
    pickerRoomIds.value = []
    form.value.booking_room_ids = [] 
  } 
})

onBeforeUnmount(() => {
  stopDragModal()
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

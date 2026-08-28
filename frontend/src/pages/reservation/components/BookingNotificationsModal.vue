<template>
  <div v-if="show" class="fixed inset-0 z-[99998] flex items-center justify-center bg-slate-950/35 p-4" @click.self="close">
    <section class="flex max-h-[calc(100vh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-blue-400 bg-white shadow-2xl">
      <header class="flex items-center justify-between bg-[#357ff0] px-5 py-3 text-white"><h2 class="text-base font-bold">Thông báo booking</h2><button class="rounded p-1 text-2xl leading-none hover:bg-white/15" aria-label="Đóng" @click="close">×</button></header>
      <div class="overflow-y-auto p-5 text-sm">
        <section class="border-b border-slate-200 pb-4"><h3 class="mb-2 font-bold text-slate-800">Thông tin đăng ký<span class="text-red-500">*</span></h3><div class="grid gap-2 text-slate-700 md:grid-cols-2"><p>Tên đăng ký: <strong>{{ booking?.bookingName || '—' }}</strong></p><p>Người dùng: <strong>{{ userName }}</strong></p><p class="md:col-start-2">Ngày đến/ngày đi: <strong>{{ formatDate(booking?.checkIn) }} - {{ formatDate(booking?.checkOut) }}</strong></p></div></section>

        <section class="grid gap-5 border-b border-slate-200 py-4 lg:grid-cols-[1.15fr_0.9fr]">
          <div><h3 class="mb-3 font-bold text-slate-800">Thông báo<span class="text-red-500">*</span></h3><div class="mb-4 flex gap-5"><label class="flex cursor-pointer items-center gap-2"><input v-model="form.scope_type" type="radio" value="booking"> Đăng ký</label><label class="flex cursor-pointer items-center gap-2"><input v-model="form.scope_type" type="radio" value="room"> Phòng</label></div>
            <div v-if="form.scope_type === 'booking'" class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-blue-800">Thông báo sẽ áp dụng cho toàn bộ đăng ký này.</div>
            <div v-else class="relative"><button type="button" class="flex w-full items-center justify-between rounded-md border border-slate-300 px-3 py-2 text-left hover:border-blue-400" @click="roomDropdownOpen = !roomDropdownOpen"><span><strong>Chọn phòng</strong><span class="ml-2 text-slate-500">{{ appliedRoomIds.length ? `(${appliedRoomIds.length} phòng)` : '(chưa chọn)' }}</span></span><span>⌄</span></button>
              <div v-if="roomDropdownOpen" class="absolute z-20 mt-1 w-full rounded-md border border-slate-300 bg-white p-3 shadow-xl"><label class="flex cursor-pointer items-center gap-2 border-b border-slate-200 pb-2 font-bold"><input type="checkbox" :checked="allRoomsSelected" @change="toggleAllRooms"> Tất cả</label><div class="max-h-40 overflow-y-auto py-1"><label v-for="room in rooms" :key="room.id" class="flex cursor-pointer items-center gap-2 py-2"><input v-model="pickerRoomIds" type="checkbox" :value="String(room.id)"> Phòng <strong>{{ room.roomNumber || 'Chưa gán số phòng' }}</strong></label><p v-if="!rooms.length" class="py-2 text-slate-500">Đăng ký chưa có phòng để chọn.</p></div><button type="button" class="mt-2 rounded bg-[#357ff0] px-5 py-1.5 font-bold text-white hover:bg-blue-700" @click="applyRoomSelection">Lưu</button></div>
            </div>
          </div>
          <label class="block">Mô tả<span class="text-red-500">*</span><textarea v-model.trim="form.description" rows="5" maxlength="5000" placeholder="Nhập nội dung thông báo..." class="mt-2 w-full resize-y rounded-md border border-slate-300 px-3 py-2 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100" /></label>
          <div class="flex flex-wrap gap-3 lg:col-span-2"><label class="block">Ngày bắt đầu<input v-model="form.starts_on" type="date" class="mt-1 block rounded-md border border-slate-300 px-3 py-2" /></label><label class="block">Ngày kết thúc<input v-model="form.ends_on" type="date" class="mt-1 block rounded-md border border-slate-300 px-3 py-2" /></label></div>
        </section>

        <section class="pt-4"><div class="mb-3 flex items-center justify-between"><h3 class="font-bold text-slate-800">Thông tin<span class="text-red-500">*</span></h3><span class="text-xs text-slate-500">{{ notifications.length }} thông báo</span></div><div class="overflow-x-auto rounded-lg border border-slate-200"><table class="w-full min-w-[620px] text-left text-sm"><thead class="bg-slate-100 text-slate-800"><tr><th class="px-3 py-3">Phạm vi</th><th class="px-3 py-3">Ngày bắt đầu</th><th class="px-3 py-3">Ngày kết thúc</th><th class="px-3 py-3">Mô tả</th></tr></thead><tbody><tr v-if="loading"><td colspan="4" class="px-3 py-8 text-center text-slate-500">Đang tải...</td></tr><tr v-else-if="!notifications.length"><td colspan="4" class="px-3 py-8 text-center text-slate-500">Chưa có thông báo cho đăng ký này.</td></tr><tr v-for="item in notifications" :key="item.id" class="cursor-pointer border-t border-slate-100 hover:bg-blue-50" :class="selectedId === item.id ? 'bg-blue-100' : ''" @click="selectedId = item.id"><td class="px-3 py-3">{{ scopeLabel(item) }}</td><td class="px-3 py-3">{{ formatDate(item.starts_on) }}</td><td class="px-3 py-3">{{ formatDate(item.ends_on) }}</td><td class="max-w-96 whitespace-pre-wrap px-3 py-3">{{ item.description }}</td></tr></tbody></table></div></section>
      </div>
      <footer class="flex flex-wrap justify-center gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3"><button class="action" :disabled="saving" @click="addNotification">＋ Thêm</button><button class="action" :disabled="!selectedId || saving" @click="editSelected">✎ Sửa</button><button class="action" :disabled="!editing || saving" @click="saveEdited">▣ Lưu</button><button class="action danger" :disabled="!selectedId || saving" @click="remove">▣ Xóa</button><button class="action" @click="close">⊗ Close</button></footer>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { createBookingNotification, deleteBookingNotification, fetchBookingNotifications, updateBookingNotification } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
const props = defineProps({ show: Boolean, booking: Object, userName: String, systemDate: String })
const emit = defineEmits(['update:show', 'saved'])
const uiStore = useUiStore(); const notifications = ref([]); const selectedId = ref(null); const loading = ref(false); const saving = ref(false); const editing = ref(false); const roomDropdownOpen = ref(false); const pickerRoomIds = ref([]); const appliedRoomIds = ref([])
const rooms = computed(() => (props.booking?.rooms || []).filter(room => room.bookingRoomId).map(room => ({ id: room.bookingRoomId, roomNumber: room.roomNumber })))
const blankForm = () => ({ 
  scope_type: 'booking', 
  booking_room_ids: [], 
  starts_on: props.booking?.checkIn ? String(props.booking.checkIn).slice(0, 10) : (props.systemDate || ''), 
  ends_on: props.booking?.checkOut ? String(props.booking.checkOut).slice(0, 10) : (props.systemDate || ''), 
  description: '' 
})
const form = ref(blankForm())
const allRoomsSelected = computed(() => rooms.value.length > 0 && pickerRoomIds.value.length === rooms.value.length)
function formatDate(value) { if (!value) return '—'; const [y, m, d] = String(value).slice(0, 10).split('-'); return y ? `${d}/${m}/${y}` : value }
function scopeLabel(item) { if (item.scope_type === 'booking') return 'Toàn đăng ký'; return (item.booking_room_ids || []).map(id => rooms.value.find(room => String(room.id) === String(id))?.roomNumber || id).join(', ') || 'Phòng' }
function resetForm() { form.value = blankForm(); pickerRoomIds.value = []; appliedRoomIds.value = []; selectedId.value = null; editing.value = false; roomDropdownOpen.value = false }
function toggleAllRooms(event) { pickerRoomIds.value = event.target.checked ? rooms.value.map(room => String(room.id)) : [] }
function applyRoomSelection() { appliedRoomIds.value = [...pickerRoomIds.value]; form.value.booking_room_ids = [...pickerRoomIds.value]; roomDropdownOpen.value = false }
function payload() { return { ...form.value, booking_room_ids: form.value.scope_type === 'room' ? appliedRoomIds.value : [] } }
function validate() { if (!form.value.description) return 'Vui lòng nhập mô tả thông báo.'; if (form.value.scope_type === 'room' && !appliedRoomIds.value.length) return 'Vui lòng chọn phòng và bấm Lưu trong dropdown.'; if (!form.value.starts_on || !form.value.ends_on) return 'Vui lòng chọn ngày bắt đầu và ngày kết thúc.'; if (form.value.ends_on < form.value.starts_on) return 'Ngày kết thúc không được trước ngày bắt đầu.'; return '' }
async function load() { if (!props.booking?.dbId) return; loading.value = true; try { const res = await fetchBookingNotifications(props.booking.dbId); notifications.value = res.data?.data || [] } catch (err) { uiStore.showToast(err.response?.data?.message || 'Không thể tải thông báo.', 'error') } finally { loading.value = false } }
async function addNotification() { const error = validate(); if (error) return uiStore.showToast(error, 'warning'); saving.value = true; try { await createBookingNotification(props.booking.dbId, payload()); await load(); resetForm(); emit('saved'); uiStore.showToast('Đã thêm thông báo booking.', 'success') } catch (err) { uiStore.showToast(err.response?.data?.message || 'Không thể thêm thông báo.', 'error') } finally { saving.value = false } }
function editSelected() { const item = notifications.value.find(n => n.id === selectedId.value); if (!item) return; form.value = { scope_type: item.scope_type, booking_room_ids: (item.booking_room_ids || []).map(String), starts_on: String(item.starts_on).slice(0, 10), ends_on: String(item.ends_on).slice(0, 10), description: item.description || '' }; pickerRoomIds.value = [...form.value.booking_room_ids]; appliedRoomIds.value = [...form.value.booking_room_ids]; editing.value = true }
async function saveEdited() { const error = validate(); if (error) return uiStore.showToast(error, 'warning'); saving.value = true; try { await updateBookingNotification(props.booking.dbId, selectedId.value, payload()); await load(); resetForm(); emit('saved'); uiStore.showToast('Đã cập nhật thông báo.', 'success') } catch (err) { uiStore.showToast(err.response?.data?.message || 'Không thể lưu thông báo.', 'error') } finally { saving.value = false } }
async function remove() { const ok = await uiStore.confirm({ title: 'Xóa thông báo', message: 'Bạn có chắc muốn xóa thông báo đã chọn?', confirmText: 'Xóa', cancelText: 'Hủy' }); if (!ok) return; saving.value = true; try { await deleteBookingNotification(props.booking.dbId, selectedId.value); await load(); resetForm(); emit('saved'); uiStore.showToast('Đã xóa thông báo.', 'success') } catch (err) { uiStore.showToast(err.response?.data?.message || 'Không thể xóa thông báo.', 'error') } finally { saving.value = false } }
function close() { emit('update:show', false) }
watch(() => props.show, value => { if (value) { resetForm(); load() } })
watch(() => form.value.scope_type, value => { if (value === 'booking') { appliedRoomIds.value = []; pickerRoomIds.value = []; form.value.booking_room_ids = [] } })
</script>
<style scoped>.action { border: 0; border-radius: .375rem; background: #357ff0; color: white; font-weight: 700; padding: .55rem 1.1rem; cursor: pointer; }.action:hover:not(:disabled) { background: #1968dc; }.action:disabled { cursor: not-allowed; opacity: .48; }.danger { background: #e34545; }</style>

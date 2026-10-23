<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { GripVertical, Pencil, Trash2 } from '@lucide/vue'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import http from '@/services/http'
import {
  fetchHousekeepingOutlets,
  createHousekeepingOutlet,
  updateHousekeepingOutlet,
  forceDeleteHousekeepingOutlet,
  reorderHousekeepingOutlets
} from '@/services/housekeeping-outlet-service'

const uiStore = useUiStore()
const authStore = useAuthStore()
const topbarThemeBg = computed(() => authStore.themeColor || authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)')

const outlets = ref([])
const hotelServices = ref([])
const definitionTabs = ['Outlet']
const activeDefinitionTab = ref('Outlet')
const loading = ref(false)
const editing = ref(null)
const draggedIndex = ref(null)
const dragOverIndex = ref(null)
const updatingFlags = ref(new Set())
const form = reactive({
  code: '',
  name: '',
  service_code: '',
  is_active: true,
  show_in_add_service: true,
  default_service_charge_percent: 5,
  default_special_tax_percent: 0,
  default_tax_percent: 8,
  order_index: 0,
  image: null,
  imageFile: null,
  image_url: null,
  remove_image: false
})

const reset = () => {
  Object.assign(form, {
    code: '',
    name: '',
    service_code: '',
    is_active: true,
    show_in_add_service: true,
    default_service_charge_percent: 5,
    default_special_tax_percent: 0,
    default_tax_percent: 8,
    order_index: outlets.value.length + 1,
    image: null,
    imageFile: null,
    image_url: null,
    remove_image: false
  })
}

const onImageSelected = (event) => {
  const file = event.target.files?.[0]
  if (file) {
    form.imageFile = file
    form.image_url = URL.createObjectURL(file)
    form.remove_image = false
  }
}

const removeImage = () => {
  form.image = null
  form.imageFile = null
  form.image_url = null
  form.remove_image = true
}

const load = async () => {
  loading.value = true
  try { outlets.value = (await fetchHousekeepingOutlets()).data || [] }
  catch (e) { uiStore.showToast(e.response?.data?.message || 'Không tải được cấu hình menu HK', 'error') }
  finally { loading.value = false }
}

const fetchHotelServices = async () => {
  try {
    const res = await http.get('/hotel-services')
    hotelServices.value = res.data?.data || []
  } catch (e) {
    console.error('Không tải được danh sách dịch vụ:', e)
  }
}

const normalizeImageUrl = (url) => {
  if (!url) return null
  const clean = String(url).trim()
  if (clean.startsWith('/storage/')) return clean
  if (clean.startsWith('storage/')) return '/' + clean
  const match = clean.match(/\/storage\/.+$/i)
  if (match) return match[0]
  if (!clean.startsWith('http') && !clean.startsWith('/')) {
    return '/storage/' + clean
  }
  return clean
}

const edit = (item) => {
  editing.value = item.id
  Object.assign(form, item)
  if (form.default_service_charge_percent === undefined || form.default_service_charge_percent === null) {
    form.default_service_charge_percent = 5
  }
  if (form.default_special_tax_percent === undefined || form.default_special_tax_percent === null) {
    form.default_special_tax_percent = 0
  }
  if (form.default_tax_percent === undefined || form.default_tax_percent === null) {
    form.default_tax_percent = 8
  }
  form.imageFile = null
  form.remove_image = false
  form.image_url = normalizeImageUrl(item.image_url || item.image)
}

const save = async () => {
  if (!form.code || !form.name) return uiStore.showToast('Vui lòng nhập mã và tên outlet', 'warning')
  try {
    const fd = new FormData()
    fd.append('code', form.code)
    fd.append('name', form.name)
    fd.append('service_code', form.service_code || '')
    fd.append('is_active', form.is_active ? 1 : 0)
    fd.append('show_in_add_service', form.show_in_add_service ? 1 : 0)
    fd.append('default_service_charge_percent', form.default_service_charge_percent ?? 5)
    fd.append('default_special_tax_percent', form.default_special_tax_percent ?? 0)
    fd.append('default_tax_percent', form.default_tax_percent ?? 8)
    fd.append('order_index', form.order_index || 0)
    if (form.imageFile) {
      fd.append('image', form.imageFile)
    } else if (form.remove_image) {
      fd.append('remove_image', '1')
    }

    if (editing.value) await updateHousekeepingOutlet(editing.value, fd)
    else await createHousekeepingOutlet(fd)

    uiStore.showToast('Đã lưu cấu hình outlet HK', 'success')
    editing.value = null
    reset()
    await load()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Không lưu được cấu hình', 'error')
  }
}
const forceRemove = async (item) => {
  const ok = await uiStore.confirm({ title: 'Xóa vĩnh viễn outlet HK', message: `Xóa vĩnh viễn ${item.name}? Thao tác này không thể hoàn tác.` })
  if (!ok) return
  try {
    await forceDeleteHousekeepingOutlet(item.id)
    uiStore.showToast('Đã xóa vĩnh viễn outlet HK', 'success')
    await load()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Outlet đã có dữ liệu, chỉ có thể tắt', 'warning')
  }
}
const toggleOutletFlag = async (item, field) => {
  const key = `${item.id}:${field}`
  if (updatingFlags.value.has(key)) return

  updatingFlags.value = new Set([...updatingFlags.value, key])
  const value = !Boolean(item[field])
  try {
    const response = await updateHousekeepingOutlet(item.id, {
      code: item.code,
      name: item.name,
      service_code: item.service_code,
      is_active: field === 'is_active' ? value : Boolean(item.is_active),
      show_in_add_service: field === 'show_in_add_service' ? value : Boolean(item.show_in_add_service),
      order_index: item.order_index
    })
    Object.assign(item, response.data)
    uiStore.showToast('Đã cập nhật trạng thái outlet', 'success')
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Không cập nhật được trạng thái outlet', 'error')
  } finally {
    const next = new Set(updatingFlags.value)
    next.delete(key)
    updatingFlags.value = next
  }
}
const isFlagUpdating = (item, field) => updatingFlags.value.has(`${item.id}:${field}`)
const move = async (index, delta) => {
  const target = index + delta
  if (target < 0 || target >= outlets.value.length) return
  const next = [...outlets.value]; [next[index], next[target]] = [next[target], next[index]]
  outlets.value = next
  await reorderHousekeepingOutlets(next.map((x, i) => ({ id: x.id, order_index: i + 1 })))
  await load()
}
const startDrag = (index) => { draggedIndex.value = index }
const setDragOver = (index) => {
  if (draggedIndex.value !== null && draggedIndex.value !== index) dragOverIndex.value = index
}
const dropOutlet = async (targetIndex) => {
  const sourceIndex = draggedIndex.value
  draggedIndex.value = null
  dragOverIndex.value = null
  if (sourceIndex === null || sourceIndex === targetIndex) return
  const next = [...outlets.value]
  const [moved] = next.splice(sourceIndex, 1)
  next.splice(targetIndex, 0, moved)
  outlets.value = next
  try {
    await reorderHousekeepingOutlets(next.map((item, index) => ({ id: item.id, order_index: index + 1 })))
    uiStore.showToast('Cập nhật thứ tự outlet thành công', 'success')
    await load()
  } catch (e) {
    uiStore.showToast(e.response?.data?.message || 'Không cập nhật được thứ tự outlet', 'error')
    await load()
  }
}
onMounted(() => { reset(); load(); fetchHotelServices() })
</script>

<template>
  <div class="flex flex-col gap-2.5 flex-1 min-h-0 font-roboto text-xs overflow-hidden">
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in definitionTabs" :key="tab" @click="activeDefinitionTab = tab"
          class="px-2.5 py-1.5 text-xs font-semibold border-none bg-transparent cursor-pointer relative pb-1.5 transition-colors font-roboto"
          :class="activeDefinitionTab === tab ? 'text-[#006bdb] border-b-2 border-[#006bdb] font-bold' : 'text-[#000000D9] hover:text-[#006bdb]'">
          {{ tab }}
        </button>
      </div>
    </div>

    <template v-if="activeDefinitionTab === 'Outlet'">
      <div class="flex items-center justify-between gap-3 shrink-0">
        <button @click="editing = null; reset()"
          class="btn-pms-primary h-8 px-3 text-xs flex items-center gap-1.5 font-medium cursor-pointer shadow-xs">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          <span>Thêm Outlet</span>
        </button>
      </div>

      <div class="flex flex-col xl:grid xl:grid-cols-[minmax(0,1fr)_360px] gap-3 flex-1 min-h-0 overflow-y-auto xl:overflow-hidden">
        <!-- Outlets Table -->
        <div class="border border-slate-200 rounded-xl overflow-auto bg-white shadow-2xs h-64 shrink-0 xl:h-full">
          <table class="w-full text-xs border-collapse">
            <thead class="bg-slate-50 text-[#000000D9] font-semibold sticky top-0 z-10">
              <tr>
                <th class="p-2 text-center align-middle border border-slate-200 w-14">Thứ Tự</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Ảnh</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Mã Outlet</th>
                <th class="p-2 text-center align-middle border border-slate-200">Tên Outlet</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-24">Mã Dịch Vụ</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Phí PV (%)</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Thuế ĐB (%)</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Thuế (%)</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-20">Trạng Thái</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-24">Hiện Ở Menu</th>
                <th class="p-2 text-center align-middle border border-slate-200 w-16">Thao Tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in outlets" :key="item.id" draggable="true" @dblclick="edit(item)"
                @dragstart="startDrag(index)" @dragover.prevent="setDragOver(index)" @dragleave="dragOverIndex = null"
                @drop="dropOutlet(index)"
                class="border-b border-slate-100 hover:bg-slate-50 cursor-move relative text-gray-900"
                :class="[draggedIndex === index ? 'opacity-50' : '', dragOverIndex === index ? 'border-t-2 border-t-sky-500' : '']">
                <td class="p-2 text-center border-r border-slate-100 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1 font-normal text-[#000000D9]">
                    <GripVertical class="w-3.5 h-3.5 text-slate-400" />{{ index + 1 }}
                  </span>
                </td>
                <td class="p-1.5 text-center border-r border-slate-100">
                  <div class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center mx-auto shadow-2xs">
                    <img v-if="item.image_url || item.image" :src="normalizeImageUrl(item.image_url || item.image)" class="w-full h-full object-cover" />
                    <span v-else class="text-[10px] text-slate-400 font-normal">{{ item.code }}</span>
                  </div>
                </td>
                <td class="p-2 border-r border-slate-100 font-normal text-center text-[#000000D9]">{{ item.code }}</td>
                <td class="p-2 border-r border-slate-100 font-normal text-[#000000D9]">{{ item.name }}</td>
                <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ item.service_code || '-' }}</td>
                <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ item.default_service_charge_percent ?? 0 }}</td>
                <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ item.default_special_tax_percent ?? 0 }}</td>
                <td class="p-2 border-r border-slate-100 text-center font-normal text-[#000000D9]">{{ item.default_tax_percent ?? 0 }}</td>
                <td class="p-2 border-r border-slate-100 text-center">
                  <button type="button" role="switch" :aria-checked="item.is_active" :disabled="isFlagUpdating(item, 'is_active')"
                    title="Bật/tắt outlet" @click.stop="toggleOutletFlag(item, 'is_active')"
                    class="relative inline-flex h-4.5 w-8 shrink-0 items-center rounded-full transition-colors disabled:cursor-wait disabled:opacity-50 cursor-pointer"
                    :class="item.is_active ? 'bg-emerald-500' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"
                      :class="item.is_active ? 'translate-x-4' : 'translate-x-0.5'"></span>
                  </button>
                </td>
                <td class="p-2 border-r border-slate-100 text-center">
                  <button type="button" role="switch" :aria-checked="item.show_in_add_service" :disabled="isFlagUpdating(item, 'show_in_add_service')"
                    title="Hiện/ẩn ở Thêm dịch vụ" @click.stop="toggleOutletFlag(item, 'show_in_add_service')"
                    class="relative inline-flex h-4.5 w-8 shrink-0 items-center rounded-full transition-colors disabled:cursor-wait disabled:opacity-50 cursor-pointer"
                    :class="item.show_in_add_service ? 'bg-sky-500' : 'bg-slate-300'">
                    <span class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"
                      :class="item.show_in_add_service ? 'translate-x-4' : 'translate-x-0.5'"></span>
                  </button>
                </td>
                <td class="p-2 text-center whitespace-nowrap">
                  <button @click="edit(item)" title="Sửa outlet"
                    class="inline-flex p-1 text-sky-600 hover:bg-sky-50 rounded transition-colors cursor-pointer mr-1">
                    <Pencil class="w-3.5 h-3.5" />
                  </button>
                  <button @click="forceRemove(item)" title="Xóa vĩnh viễn"
                    class="inline-flex p-1 text-rose-500 hover:bg-rose-50 rounded transition-colors cursor-pointer">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
              <tr v-if="!loading && !outlets.length">
                <td colspan="11" class="p-8 text-center text-gray-500 font-medium">Chưa có outlet HK.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Form Edit / Add Outlet -->
        <div class="border border-slate-200 rounded-xl p-3 bg-white flex flex-col gap-2 h-fit shadow-2xs">
          <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wide border-b border-slate-100 pb-1.5">
            {{ editing ? 'Sửa Outlet HK' : 'Thêm Outlet HK' }}
          </h3>
          
          <!-- Outlet Image Upload -->
          <div class="flex items-center gap-3 bg-slate-50/60 p-2.5 rounded-lg border border-slate-200">
            <div class="w-14 h-14 rounded-lg border border-slate-200 bg-white overflow-hidden flex items-center justify-center shrink-0 shadow-2xs">
              <img v-if="form.image_url" :src="form.image_url" class="w-full h-full object-cover" />
              <span v-else class="text-[10px] text-gray-400 font-normal">Chưa có</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="px-3 py-1 rounded border border-slate-300 hover:bg-white text-gray-700 text-xs font-normal cursor-pointer text-center shadow-2xs transition-colors">
                <span>{{ form.image_url ? 'Đổi ảnh' : 'Chọn ảnh' }}</span>
                <input type="file" accept="image/*" class="hidden" @change="onImageSelected" />
              </label>
              <button v-if="form.image_url" type="button" @click="removeImage"
                class="text-xs text-rose-500 hover:text-rose-700 font-normal cursor-pointer border-none bg-transparent text-left pl-1">
                Xóa ảnh
              </button>
            </div>
          </div>

          <!-- Tên outlet -->
          <div class="flex flex-col gap-0.5">
            <label class="text-xs font-semibold text-[#000000D9]">Tên Outlet <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="form.name" placeholder="Nhập tên outlet"
                class="w-full bg-[#FFF8DB] border border-[#F1DD8A] focus:border-[#eab308] rounded px-2.5 py-1 text-xs focus:outline-none font-normal text-[#000000D9] transition-colors" />
              <button v-if="form.name" @click="form.name = ''" type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">✕</button>
            </div>
          </div>

          <!-- Mã outlet -->
          <div class="flex flex-col gap-0.5">
            <label class="text-xs font-semibold text-[#000000D9]">Mã Outlet <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input v-model="form.code" placeholder="Nhập mã outlet" :disabled="Boolean(editing)"
                class="w-full border rounded px-2.5 py-1 text-xs focus:outline-none font-normal text-[#000000D9] transition-colors disabled:bg-slate-100 disabled:border-slate-200 disabled:text-[#000000D9] disabled:cursor-not-allowed"
                :class="editing ? '' : 'bg-[#FFF8DB] border-[#F1DD8A] focus:border-[#eab308]'" />
              <button v-if="form.code && !editing" @click="form.code = ''" type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-xs">✕</button>
            </div>
          </div>

          <!-- Mã dịch vụ -->
          <div class="flex flex-col gap-0.5">
            <label class="text-xs font-semibold text-[#000000D9]">Mã Dịch Vụ</label>
            <select v-model="form.service_code"
              class="w-full border border-slate-200 rounded px-2.5 py-1 text-xs focus:outline-sky-400 bg-white font-normal text-[#000000D9]">
              <option value="">-- Chưa gán dịch vụ --</option>
              <option v-for="srv in hotelServices" :key="srv.code" :value="srv.code">
                {{ srv.code }} - {{ srv.name }}
              </option>
            </select>
          </div>

          <!-- Nhóm thuế phí: Phí phục vụ (5) -> Thuế ĐB (0) -> Thuế (8) -->
          <div class="grid grid-cols-3 gap-2">
            <label class="flex min-w-0 flex-col gap-0.5 text-xs font-semibold text-[#000000D9]">
              <span class="truncate">Phí PV (%)</span>
              <input v-model.number="form.default_service_charge_percent" type="number" min="0" max="100" step="0.01"
                class="w-full min-w-0 px-2 py-1 rounded border border-slate-200 text-xs font-normal text-[#000000D9] focus:outline-sky-400 bg-white" />
            </label>
            <label class="flex min-w-0 flex-col gap-0.5 text-xs font-semibold text-[#000000D9]">
              <span class="truncate">Thuế ĐB (%)</span>
              <input v-model.number="form.default_special_tax_percent" type="number" min="0" max="100" step="0.01"
                class="w-full min-w-0 px-2 py-1 rounded border border-slate-200 text-xs font-normal text-[#000000D9] focus:outline-sky-400 bg-white" />
            </label>
            <label class="flex min-w-0 flex-col gap-0.5 text-xs font-semibold text-[#000000D9]">
              <span class="truncate">Thuế (%)</span>
              <input v-model.number="form.default_tax_percent" type="number" min="0" max="100" step="0.01"
                class="w-full min-w-0 px-2 py-1 rounded border border-slate-200 text-xs font-normal text-[#000000D9] focus:outline-sky-400 bg-white" />
            </label>
          </div>

          <!-- Switches / Checkboxes -->
          <div class="flex flex-col gap-1.5 pt-1 border-t border-slate-100">
            <label class="text-xs font-medium text-gray-800 flex items-center gap-2 cursor-pointer select-none">
              <input v-model="form.is_active" type="checkbox" class="rounded text-sky-500 cursor-pointer" />
              <span>Đang hoạt động</span>
            </label>
            <label class="text-xs font-medium text-gray-800 flex items-center gap-2 cursor-pointer select-none">
              <input v-model="form.show_in_add_service" type="checkbox" class="rounded text-sky-500 cursor-pointer" />
              <span>Hiện ở Thêm dịch vụ</span>
            </label>
          </div>

          <!-- Buttons -->
          <div class="flex items-center justify-end gap-2 pt-1.5 border-t border-slate-100">
            <button v-if="editing" @click="editing = null; reset()" type="button"
              class="btn-pms-secondary h-8 px-3 text-xs font-medium cursor-pointer">
              Hủy
            </button>
            <button @click="save" type="button"
              class="btn-pms-primary h-8 px-4 text-xs flex items-center justify-center gap-1.5 font-medium cursor-pointer shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span>Lưu Cấu Hình</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

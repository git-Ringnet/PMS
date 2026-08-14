<script setup>
import { onMounted, reactive, ref } from 'vue'
import { GripVertical, Pencil, Trash2 } from '@lucide/vue'
import { useUiStore } from '@/stores/ui-store'
import {
  fetchHousekeepingOutlets,
  createHousekeepingOutlet,
  updateHousekeepingOutlet,
  forceDeleteHousekeepingOutlet,
  reorderHousekeepingOutlets
} from '@/services/housekeeping-outlet-service'

const uiStore = useUiStore()
const outlets = ref([])
const definitionTabs = ['Outlet']
const activeDefinitionTab = ref('Outlet')
const loading = ref(false)
const editing = ref(null)
const draggedIndex = ref(null)
const dragOverIndex = ref(null)
const updatingFlags = ref(new Set())
const form = reactive({ code: '', name: '', service_code: '', is_active: true, show_in_add_service: true, default_service_charge_percent: 0, default_tax_percent: 0, default_special_tax_percent: 0, order_index: 0 })

const reset = () => Object.assign(form, { code: '', name: '', service_code: '', is_active: true, show_in_add_service: true, default_service_charge_percent: 0, default_tax_percent: 0, default_special_tax_percent: 0, order_index: outlets.value.length + 1 })
const load = async () => {
  loading.value = true
  try { outlets.value = (await fetchHousekeepingOutlets()).data || [] }
  catch (e) { uiStore.showToast(e.response?.data?.message || 'Không tải được cấu hình menu HK', 'error') }
  finally { loading.value = false }
}
const edit = (item) => { editing.value = item.id; Object.assign(form, item) }
const save = async () => {
  if (!form.code || !form.name) return uiStore.showToast('Vui lòng nhập mã và tên outlet', 'warning')
  try {
    if (editing.value) await updateHousekeepingOutlet(editing.value, { ...form })
    else await createHousekeepingOutlet({ ...form })
    uiStore.showToast('Đã lưu cấu hình outlet HK', 'success'); editing.value = null; reset(); await load()
  } catch (e) { uiStore.showToast(e.response?.data?.message || 'Không lưu được cấu hình', 'error') }
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
onMounted(() => { reset(); load() })
</script>

<template>
  <div class="flex flex-col gap-5 h-full min-h-0">
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button v-for="tab in definitionTabs" :key="tab" @click="activeDefinitionTab = tab" class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors" :class="activeDefinitionTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'">{{ tab }}</button>
      </div>
    </div>

    <template v-if="activeDefinitionTab === 'Outlet'">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <button @click="editing = null; reset()" class="px-3 py-2 rounded-lg bg-[#8dcbf4] hover:bg-[#70b2db] text-white text-xs font-bold">+ Thêm outlet</button>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-5 min-h-0 overflow-auto">
      <div class="border border-slate-200 rounded-xl overflow-auto bg-white">
        <table class="w-full text-xs border-collapse">
          <thead class="bg-slate-50 text-slate-500 font-bold"><tr><th class="p-3 text-left">Thứ tự</th><th class="p-3 text-left">Mã</th><th class="p-3 text-left">Tên outlet</th><th class="p-3 text-left">Mã dịch vụ</th><th class="p-3 text-center">Phí phục vụ (%)</th><th class="p-3 text-center">Thuế (%)</th><th class="p-3 text-center">Thuế ĐB (%)</th><th class="p-3 text-left">Trạng thái</th><th class="p-3 text-center">Hiện khi thêm DV</th><th class="p-3 text-right">Thao tác</th></tr></thead>
          <tbody>
            <tr v-for="(item, index) in outlets" :key="item.id" draggable="true" @dblclick="edit(item)" @dragstart="startDrag(index)" @dragover.prevent="setDragOver(index)" @dragleave="dragOverIndex = null" @drop="dropOutlet(index)" class="border-t border-slate-100 hover:bg-slate-50 cursor-move relative" :class="[draggedIndex === index ? 'opacity-50' : '', dragOverIndex === index ? 'border-t-2 border-t-sky-500' : '']">
              <td class="p-3 whitespace-nowrap"><span class="inline-flex items-center gap-2 font-bold text-slate-600"><GripVertical class="w-4 h-4 text-slate-400" />{{ index + 1 }}</span></td>
              <td class="p-3 font-bold">{{ item.code }}</td><td class="p-3 font-bold">{{ item.name }}</td><td class="p-3">{{ item.service_code || '-' }}</td>
              <td class="p-3 text-center">{{ item.default_service_charge_percent || 0 }}</td>
              <td class="p-3 text-center">{{ item.default_tax_percent || 0 }}</td>
              <td class="p-3 text-center">{{ item.default_special_tax_percent || 0 }}</td>
              <td class="p-3">
                <button type="button" role="switch" :aria-checked="item.is_active" :disabled="isFlagUpdating(item, 'is_active')" title="Bật/tắt outlet" @click.stop="toggleOutletFlag(item, 'is_active')" class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:cursor-wait disabled:opacity-50" :class="item.is_active ? 'bg-emerald-500' : 'bg-slate-300'">
                  <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" :class="item.is_active ? 'translate-x-4.5' : 'translate-x-0.5'"></span>
                </button>
              </td>
              <td class="p-3 text-center">
                <button type="button" role="switch" :aria-checked="item.show_in_add_service" :disabled="isFlagUpdating(item, 'show_in_add_service')" title="Hiện/ẩn ở Thêm dịch vụ" @click.stop="toggleOutletFlag(item, 'show_in_add_service')" class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:cursor-wait disabled:opacity-50" :class="item.show_in_add_service ? 'bg-sky-500' : 'bg-slate-300'">
                  <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform" :class="item.show_in_add_service ? 'translate-x-4.5' : 'translate-x-0.5'"></span>
                </button>
              </td>
              <td class="p-3 text-right whitespace-nowrap"><button @click="edit(item)" title="Sửa outlet" class="inline-flex p-1.5 text-sky-600 hover:bg-sky-50 hover:scale-110 rounded transition-all duration-150"><Pencil class="w-4 h-4" /></button><button @click="forceRemove(item)" title="Xóa vĩnh viễn" class="inline-flex p-1.5 text-red-500 hover:bg-red-50 hover:scale-110 rounded transition-all duration-150"><Trash2 class="w-4 h-4" /></button></td>
            </tr>
            <tr v-if="!loading && !outlets.length"><td colspan="10" class="p-8 text-center text-slate-400">Chưa có outlet HK.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 flex flex-col gap-3 h-fit">
        <h3 class="text-sm font-black text-slate-700">{{ editing ? 'Sửa outlet HK' : 'Thêm outlet HK' }}</h3>
        <input v-model="form.name" placeholder="Tên outlet" class="p-2 rounded border border-slate-200 text-xs" />
        <input v-model="form.code" placeholder="Mã outlet" class="p-2 rounded border border-slate-200 text-xs" />
        <input v-model="form.service_code" placeholder="Mã dịch vụ" class="p-2 rounded border border-slate-200 text-xs" />
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
          <label class="flex min-w-0 flex-col gap-1 text-xs font-bold"><span class="flex min-h-8 items-end">Phí phục vụ (%)</span><input v-model.number="form.default_service_charge_percent" type="number" min="0" max="100" step="0.01" class="w-full min-w-0 p-2 rounded border border-slate-200 text-xs font-normal" /></label>
          <label class="flex min-w-0 flex-col gap-1 text-xs font-bold"><span class="flex min-h-8 items-end">Thuế (%)</span><input v-model.number="form.default_tax_percent" type="number" min="0" max="100" step="0.01" class="w-full min-w-0 p-2 rounded border border-slate-200 text-xs font-normal" /></label>
          <label class="flex min-w-0 flex-col gap-1 text-xs font-bold"><span class="flex min-h-8 items-end">Thuế ĐB (%)</span><input v-model.number="form.default_special_tax_percent" type="number" min="0" max="100" step="0.01" class="w-full min-w-0 p-2 rounded border border-slate-200 text-xs font-normal" /></label>
        </div>
        <label class="text-xs font-bold"><input v-model="form.is_active" type="checkbox" class="mr-2" /> Đang hoạt động</label>
        <label class="text-xs font-bold"><input v-model="form.show_in_add_service" type="checkbox" class="mr-2" /> Hiện ở Thêm dịch vụ</label>
        <button @click="save" class="px-3 py-2 rounded-lg bg-sky-500 hover:bg-sky-600 text-white text-xs font-bold">Lưu cấu hình</button>
      </div>
      </div>
    </template>
  </div>
</template>

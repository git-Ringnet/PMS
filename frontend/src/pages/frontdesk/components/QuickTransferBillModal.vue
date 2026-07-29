<script setup>
import { computed, ref, watch } from 'vue'
import { HelpCircle, X, Inbox, ArrowRightLeft, Minus } from '@lucide/vue'

const props = defineProps({
  show: Boolean,
  targetLabel: { type: String, default: '' },
  candidates: { type: Array, default: () => [] },
  loading: Boolean,
})
const emit = defineEmits(['close', 'submit'])
const selectedBillIds = ref([])
const collapsedGroups = ref({})

watch(() => props.show, visible => { if (visible) { selectedBillIds.value = []; collapsedGroups.value = {} } })
const money = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0)
const groupLabels = { MB: 'Minibar/Phí Minibar', LA: 'Laundry/Giặt ủi', BR: 'Broken/Phí Hư Hỏng', RM: 'Dịch vụ phòng nghỉ' }
const groupedCandidates = computed(() => {
  const groups = new Map()
  props.candidates.forEach(item => {
    const key = item.category || 'DV'
    if (!groups.has(key)) groups.set(key, { key, label: groupLabels[key] || item.description || 'Dịch vụ khác', items: [] })
    groups.get(key).items.push(item)
  })
  return [...groups.values()]
})
const isSelected = id => selectedBillIds.value.includes(id)
const toggle = (id, checked) => { selectedBillIds.value = checked ? [...new Set([...selectedBillIds.value, id])] : selectedBillIds.value.filter(value => value !== id) }
const isAllSelected = computed(() => props.candidates.length > 0 && props.candidates.every(item => isSelected(item.bill_id)))
const toggleAll = checked => { selectedBillIds.value = checked ? props.candidates.map(item => item.bill_id) : [] }
const isGroupSelected = group => group.items.length > 0 && group.items.every(item => isSelected(item.bill_id))
const toggleGroup = (group, checked) => group.items.forEach(item => toggle(item.bill_id, checked))
const toggleCollapsed = key => { collapsedGroups.value[key] = !collapsedGroups.value[key] }
const submit = () => { if (selectedBillIds.value.length && !props.loading) emit('submit', selectedBillIds.value) }
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="flex w-full max-w-[795px] flex-col overflow-hidden rounded-xl border border-sky-500 bg-white text-xs shadow-2xl">
      <header class="flex items-center justify-between bg-[#0788f5] px-4 py-2 text-white"><span class="font-bold">Chuyển bill nhanh</span><div class="flex gap-2"><HelpCircle class="h-5 w-5" /><button :disabled="loading" @click="emit('close')" class="rounded transition hover:bg-white/20 active:scale-90"><X class="h-5 w-5" /></button></div></header>
      <div class="space-y-3 p-4">
        <input :value="targetLabel" readonly class="w-full rounded border border-gray-300 bg-gray-100 px-2.5 py-1 text-gray-800" />
        <div class="relative min-h-[260px] max-h-[578px] overflow-auto rounded border border-gray-300 bg-white">
          <table class="w-full border-collapse text-left text-xs"><thead class="sticky top-0 bg-[#f0f2ea] text-gray-700"><tr><th class="w-10 border-r border-gray-300 p-2 text-center"></th><th class="w-10 border-r border-gray-300 p-2 text-center"><input type="checkbox" :checked="isAllSelected" :disabled="loading || !candidates.length" @change="toggleAll($event.target.checked)" /></th><th class="border-r border-gray-300 p-2">Mã đăng ký</th><th class="border-r border-gray-300 p-2">Tên khách</th><th class="border-r border-gray-300 p-2">Phòng</th><th class="p-2 text-right">Số tiền</th></tr></thead><tbody>
            <template v-for="group in groupedCandidates" :key="group.key"><tr class="border-t border-gray-300 bg-white"><td class="border-r border-gray-200 p-2 text-center"><button type="button" @click="toggleCollapsed(group.key)"><Minus v-if="!collapsedGroups[group.key]" class="inline h-4 w-4 rounded bg-[#0788f5] p-0.5 text-white" /><span v-else class="inline-block h-4 w-4 rounded bg-[#0788f5] text-center leading-4 text-white">+</span></button></td><td class="border-r border-gray-200 p-2 text-center"><input type="checkbox" :checked="isGroupSelected(group)" :disabled="loading" @change="toggleGroup(group, $event.target.checked)" /></td><td colspan="4" class="p-2 font-medium">{{ group.label }}</td></tr><tr v-for="item in (collapsedGroups[group.key] ? [] : group.items)" :key="item.bill_id" class="border-t border-gray-200 bg-sky-50/50 hover:bg-sky-100"><td class="border-r border-gray-200"></td><td class="border-r border-gray-200 p-2 text-center"><input type="checkbox" :checked="isSelected(item.bill_id)" :disabled="loading" @change="toggle(item.bill_id, $event.target.checked)" /></td><td class="border-r border-gray-200 p-2">{{ item.booking_code }}</td><td class="border-r border-gray-200 p-2 whitespace-normal">{{ item.guest_name }}</td><td class="border-r border-gray-200 p-2">{{ item.room_number }}</td><td class="p-2 text-right font-mono">{{ money(item.amount) }}</td></tr></template>
          </tbody></table>
          <div v-if="!loading && !candidates.length" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400"><Inbox class="mb-1 h-10 w-10 text-gray-300" /><span>No data</span></div>
        </div>
      </div>
      <footer class="flex justify-end gap-2 border-t border-gray-300 bg-gray-50 p-3"><button :disabled="loading" @click="emit('close')" class="rounded bg-[#38bdf8] px-4 py-1.5 font-bold text-white transition hover:bg-sky-500 active:scale-95 disabled:opacity-40"><X class="mr-1 inline h-4 w-4" />Đóng</button><button :disabled="loading || !selectedBillIds.length" @click="submit" class="rounded bg-[#38bdf8] px-4 py-1.5 font-bold text-white transition hover:bg-sky-500 active:scale-95 disabled:opacity-40"><ArrowRightLeft class="mr-1 inline h-4 w-4" />{{ loading ? 'Đang chuyển...' : 'Chuyển bill nhanh' }}</button></footer>
    </div>
  </div>
</template>

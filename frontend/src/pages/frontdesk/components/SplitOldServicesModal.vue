<script setup>
import { computed, onMounted, ref } from 'vue'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { vi } from 'date-fns/locale'
import { X, CalendarDays } from '@lucide/vue'
import { fetchSystemDate, splitOldServices } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const emit = defineEmits(['close'])
const uiStore = useUiStore()
const isSubmitting = ref(false)
const systemDate = ref(new Date())
const selectedRange = ref([new Date(), new Date()])

const maxDate = computed(() => systemDate.value)

function toYmd(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

onMounted(async () => {
  try {
    const res = await fetchSystemDate()
    const value = res.data?.data?.system_date || res.data?.system_date
    if (value) {
      const [year, month, day] = String(value).slice(0, 10).split('-').map(Number)
      systemDate.value = new Date(year, month - 1, day)
      selectedRange.value = [new Date(year, month - 1, day), new Date(year, month - 1, day)]
    }
  } catch {
    uiStore.showToast('Không lấy được ngày hệ thống, đang dùng ngày hiện tại.', 'warning')
  }
})

async function submit() {
  const [fromDate, toDate] = selectedRange.value || []
  if (!fromDate || !toDate) {
    uiStore.showToast('Vui lòng chọn khoảng ngày cần tách dịch vụ.', 'warning')
    return
  }
  if (toDate > maxDate.value) {
    uiStore.showToast('Ngày tách dịch vụ không được lớn hơn ngày hệ thống.', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận tách dịch vụ ngày cũ',
    message: 'Hệ thống sẽ tính lại chi tiết tiền ăn sáng cho các bill chưa xuất VAT trong khoảng ngày đã chọn.',
    confirmText: 'Tách dịch vụ',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  try {
    isSubmitting.value = true
    const res = await splitOldServices({ from_date: toYmd(fromDate), to_date: toYmd(toDate) })
    const data = res.data?.data || {}
    uiStore.showToast(`${res.data?.message || 'Tách dịch vụ thành công.'} Bỏ qua ${data.skipped_vat || 0} bill đã xuất VAT.`, 'success')
    emit('close')
  } catch (error) {
    uiStore.showToast(error.response?.data?.message || 'Không thể tách dịch vụ ngày cũ.', 'error')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-[1100] flex items-start justify-center bg-slate-900/45 pt-24" @mousedown.self="emit('close')">
    <section class="w-[370px] overflow-visible rounded-xl bg-white shadow-2xl">
      <header class="flex items-center justify-between rounded-t-xl bg-[#2f5bea] px-5 py-4 text-white">
        <h2 class="text-base font-bold">Tách dịch vụ ngày cũ</h2>
        <button type="button" class="rounded p-1 hover:bg-white/15" @click="emit('close')" aria-label="Đóng">
          <X class="h-6 w-6" />
        </button>
      </header>

      <div class="p-5">
        <label class="mb-2 block text-sm font-medium text-slate-700">Ngày</label>
        <VueDatePicker
          v-model="selectedRange"
          range
          :max-date="maxDate"
          :locale="vi"
          :enable-time-picker="false"
          :text-input="{ format: 'dd/MM/yyyy' }"
          format="dd/MM/yyyy"
          auto-apply
          :clearable="false"
          class="old-service-date-picker"
        >
          <template #trigger>
            <button type="button" class="flex h-10 w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 text-left text-base font-medium text-slate-700 shadow-sm hover:border-blue-400">
              <span>{{ selectedRange?.[0]?.toLocaleDateString('vi-VN') || '--/--/----' }} ~ {{ selectedRange?.[1]?.toLocaleDateString('vi-VN') || '--/--/----' }}</span>
              <CalendarDays class="h-5 w-5 text-emerald-500" />
            </button>
          </template>
        </VueDatePicker>
        <p class="mt-3 text-xs leading-5 text-slate-500">Chỉ tách lại chi tiết dịch vụ của bill chưa xuất VAT. Ngày được chọn không vượt quá ngày hệ thống.</p>
      </div>

      <footer class="flex justify-end gap-3 border-t border-slate-200 px-5 py-3">
        <button type="button" class="rounded-md bg-slate-100 px-5 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200" @click="emit('close')">Hủy</button>
        <button type="button" class="rounded-md bg-[#2f5bea] px-5 py-2 text-sm font-semibold text-white hover:bg-[#254bd0] disabled:cursor-not-allowed disabled:opacity-60" :disabled="isSubmitting" @click="submit">
          {{ isSubmitting ? 'Đang tách...' : 'Tách dịch vụ' }}
        </button>
      </footer>
    </section>
  </div>
</template>

<style>
.old-service-date-picker .dp__theme_light {
  --dp-primary-color: #2f5bea;
  --dp-border-color: #cbd5e1;
}
</style>

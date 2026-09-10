<script setup>
import { computed, reactive, useId, watch } from 'vue'
import ReportDateRangePicker from '@/components/ReportDateRangePicker.vue'

const FILTER_KEYS = [
  'p_from_date',
  'p_to_date',
  'p_shift',
  'p_department',
  'p_user',
  'p_view_type',
  'p_order_by',
  'p_order_type',
  'p_show_details',
]

const DEFAULTS = Object.freeze({
  p_from_date: '',
  p_to_date: '',
  p_shift: '',
  p_department: '',
  p_user: '',
  p_view_type: 'post',
  p_order_by: 'Ma',
  p_order_type: 'ASC',
  p_show_details: false,
})

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  systemDate: { type: String, default: '' },
  shifts: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  sortOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const instanceId = useId()
const controlId = (name) => `hk-invoice-${instanceId}-${name}`
const dateLabelId = controlId('date-label')
const dateRangeId = controlId('date-range')
const shiftId = controlId('shift')
const departmentId = controlId('department')
const userId = controlId('user')
const orderById = controlId('order-by')
const orderTypeId = controlId('order-type')
const detailsId = controlId('show-details')
const viewTypeName = controlId('view-type')

const viewTypes = [
  { value: 'all', label: 'All' },
  { value: 'post', label: 'Post' },
  { value: 'correct', label: 'Correct' },
  { value: 'free', label: 'Free' },
]

const isTrue = (value) => value === true || value === 1 || value === '1' || value === 'true'

const normalizeViewType = (value) => {
  const normalized = String(value || '').toLowerCase()
  return viewTypes.some((option) => option.value === normalized) ? normalized : DEFAULTS.p_view_type
}

const normalizeOrderType = (value) => {
  const normalized = String(value || '').toUpperCase()
  return normalized === 'DESC' ? 'DESC' : DEFAULTS.p_order_type
}

const normalizeModel = (value, fallbackDate = '') => {
  const source = value && typeof value === 'object' ? value : {}
  const model = Object.fromEntries(FILTER_KEYS.map((key) => [key, source[key] ?? DEFAULTS[key]]))
  return {
    ...DEFAULTS,
    ...model,
    p_from_date: source.p_from_date || fallbackDate,
    p_to_date: source.p_to_date || source.p_from_date || fallbackDate || '',
    p_view_type: normalizeViewType(source.p_view_type),
    p_order_type: normalizeOrderType(source.p_order_type),
    p_show_details: isTrue(source.p_show_details),
  }
}

const draft = reactive(normalizeModel(props.modelValue, props.systemDate))

const replaceDraft = (value) => {
  const next = normalizeModel(value, props.systemDate)
  Object.assign(draft, next)
}

watch(() => [props.modelValue, props.systemDate], ([value]) => replaceDraft(value), { deep: true })

const isLocked = () => props.disabled || props.loading

const updateField = (key, value) => {
  if (isLocked() || !FILTER_KEYS.includes(key)) return
  draft[key] = value
  emit('update:modelValue', { ...draft })
}

const updateDate = (key, value) => updateField(key, value)

const optionValue = (option) => {
  if (option && typeof option === 'object') {
    return option.value ?? option.id ?? option.code ?? option.username ?? ''
  }
  return option ?? ''
}

const optionLabel = (option) => {
  if (option && typeof option === 'object') {
    return option.label ?? option.name ?? option.title ?? option.text ?? option.username ?? option.code ?? option.value ?? option.id ?? ''
  }
  return option ?? ''
}

const optionKey = (option, index) => `${String(optionValue(option))}-${index}`

const submit = () => {
  if (isLocked()) return
  emit('submit', { ...draft })
}

const orderTypes = [
  { value: 'ASC', label: 'ASC' },
  { value: 'DESC', label: 'DESC' },
]

const availableSortOptions = computed(() => props.sortOptions.length
  ? props.sortOptions
  : [{ value: 'Ma', label: 'Mã' }])
</script>

<template>
  <form
    class="w-full text-slate-600"
    :aria-busy="loading ? 'true' : 'false'"
    @submit.prevent="submit"
  >
    <fieldset :disabled="disabled || loading" class="m-0 min-w-0 border-0 p-0 disabled:cursor-not-allowed">
      <legend class="sr-only">Điều kiện báo cáo</legend>

      <div class="mb-3 block text-[11px] font-bold text-slate-600">
        <span :id="dateLabelId">Chọn ngày</span>
        <div :id="dateRangeId" :aria-labelledby="dateLabelId">
          <ReportDateRangePicker
            :start-date="draft.p_from_date"
            :end-date="draft.p_to_date"
            :system-date="systemDate"
            @update:start-date="updateDate('p_from_date', $event)"
            @update:end-date="updateDate('p_to_date', $event)"
          />
        </div>
      </div>

      <div class="mb-3 block text-[11px] font-bold text-slate-600">
        <label :for="shiftId">Ca làm việc</label>
        <select
          :id="shiftId"
          class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-slate-700 outline-none focus:border-sky-400 disabled:opacity-50"
          :value="draft.p_shift"
          @change="updateField('p_shift', $event.target.value)"
        >
          <option value="">-- Chọn --</option>
          <option v-for="(option, index) in shifts" :key="optionKey(option, index)" :value="optionValue(option)">
            {{ optionLabel(option) }}
          </option>
        </select>
      </div>

      <div class="mb-3 block text-[11px] font-bold text-slate-600">
        <label :for="departmentId">Chọn bộ phận</label>
        <select
          :id="departmentId"
          class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-slate-700 outline-none focus:border-sky-400 disabled:opacity-50"
          :value="draft.p_department"
          @change="updateField('p_department', $event.target.value)"
        >
          <option value="">-- Chọn --</option>
          <option v-for="(option, index) in departments" :key="optionKey(option, index)" :value="optionValue(option)">
            {{ optionLabel(option) }}
          </option>
        </select>
      </div>

      <div class="mb-3 block text-[11px] font-bold text-slate-600">
        <label :for="userId">Chọn người dùng</label>
        <select
          :id="userId"
          class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-slate-700 outline-none focus:border-sky-400 disabled:opacity-50"
          :value="draft.p_user"
          @change="updateField('p_user', $event.target.value)"
        >
          <option value="">-- Chọn --</option>
          <option v-for="(option, index) in users" :key="optionKey(option, index)" :value="optionValue(option)">
            {{ optionLabel(option) }}
          </option>
        </select>
      </div>

      <div class="mb-3 block text-[11px] font-bold text-slate-600">
        <span>Sắp xếp theo</span>
        <div class="mt-1 grid grid-cols-[minmax(0,1fr)_96px] gap-2">
          <label class="sr-only" :for="orderById">Trường sắp xếp</label>
          <select
            :id="orderById"
            class="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-slate-700 outline-none focus:border-sky-400 disabled:opacity-50"
            :value="draft.p_order_by"
            @change="updateField('p_order_by', $event.target.value)"
          >
            <option value="">-- Chọn --</option>
            <option v-for="(option, index) in availableSortOptions" :key="optionKey(option, index)" :value="optionValue(option)">
              {{ optionLabel(option) }}
            </option>
          </select>

          <label class="sr-only" :for="orderTypeId">Thứ tự sắp xếp</label>
          <select
            :id="orderTypeId"
            class="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-normal text-slate-700 outline-none focus:border-sky-400 disabled:opacity-50"
            :value="draft.p_order_type"
            @change="updateField('p_order_type', $event.target.value)"
          >
            <option v-for="option in orderTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
      </div>

      <fieldset class="mb-3 border-0 p-0">
        <legend class="sr-only">Loại dữ liệu</legend>
        <div class="mt-1 flex flex-wrap items-center gap-4 text-xs font-normal text-slate-700">
          <label v-for="option in viewTypes" :key="option.value" class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap">
            <input
              :checked="draft.p_view_type === option.value"
              :name="viewTypeName"
              type="radio"
              :value="option.value"
              class="h-4 w-4 border-sky-500 text-sky-600 accent-sky-600 focus:ring-sky-500"
              @change="updateField('p_view_type', option.value)"
            />
            <span>{{ option.label }}</span>
          </label>
        </div>
      </fieldset>

      <label class="mb-3 flex h-8 cursor-pointer items-center gap-2 text-[11px] font-bold text-slate-600" :for="detailsId">
        <input
          :id="detailsId"
          :checked="draft.p_show_details"
          type="checkbox"
          role="switch"
          class="sr-only"
          @change="updateField('p_show_details', $event.target.checked)"
        />
        <span
          class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
          :class="draft.p_show_details ? 'bg-sky-500' : 'bg-slate-300'"
          aria-hidden="true"
        >
          <span
            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform"
            :class="draft.p_show_details ? 'translate-x-[18px]' : 'translate-x-1'"
          ></span>
        </span>
        <span>Xem chi tiết</span>
      </label>

      <button
        class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg border-none bg-sky-600 px-4 py-2.5 text-xs font-black text-white shadow-sm hover:bg-sky-700 disabled:opacity-50"
        type="submit"
      >
        <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>
        {{ loading ? 'Đang tải dữ liệu...' : 'Hiển thị báo cáo' }}
      </button>
    </fieldset>
  </form>
</template>

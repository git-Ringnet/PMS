<template>
  <div class="relative w-full" ref="containerRef">
    <!-- Trigger input / display box -->
    <div
      @click="toggleDropdown"
      :class="[
        'w-full h-8 px-2.5 flex items-center justify-between rounded-lg cursor-pointer transition-all select-none text-[12px]',
        required
          ? 'bg-[#FFF8DB] border border-[#F1DD8A] focus-within:border-[#eab308] focus-within:ring-1 focus-within:ring-[#eab308]'
          : 'bg-white border border-slate-300 focus-within:border-[#006bdb] focus-within:ring-1 focus-within:ring-[#006bdb]',
        disabled ? 'opacity-60 cursor-not-allowed bg-slate-100' : 'hover:border-slate-400'
      ]"
    >
      <span
        class="truncate pr-5"
        :class="selectedOption ? 'text-[#000000D9] font-normal' : 'text-[#A8B0BF]'"
      >
        {{ selectedOption ? selectedOption.label : placeholder }}
      </span>

      <div class="flex items-center gap-1 shrink-0">
        <!-- Clear button (x) -->
        <button
          v-if="allowClear && selectedOption && !disabled"
          type="button"
          @click.stop="clearSelection"
          class="text-slate-400 hover:text-slate-600 p-0.5 rounded-full hover:bg-slate-200/50 transition-colors"
          title="Xóa lựa chọn"
        >
          <X class="w-3.5 h-3.5" />
        </button>

        <!-- Dropdown chevron -->
        <ChevronDown
          class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
          :class="{ 'rotate-180': isOpen }"
        />
      </div>
    </div>

    <!-- Dropdown Popover -->
    <div
      v-if="isOpen && !disabled"
      class="absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 flex flex-col overflow-hidden text-[12px]"
      style="max-height: 220px;"
    >
      <!-- Search Input inside Popover -->
      <div class="p-1.5 border-b border-slate-100 bg-slate-50 flex items-center gap-1.5">
        <Search class="w-3.5 h-3.5 text-slate-400 shrink-0 ml-1" />
        <input
          ref="searchInputRef"
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          @keydown.esc="closeDropdown"
          @keydown.down.prevent="navigateOptions(1)"
          @keydown.up.prevent="navigateOptions(-1)"
          @keydown.enter.prevent="selectHighlighted"
          class="w-full bg-transparent text-[12px] text-[#000000D9] outline-none placeholder:text-[#A8B0BF]"
        />
        <button
          v-if="searchQuery"
          type="button"
          @click.stop="searchQuery = ''"
          class="text-slate-400 hover:text-slate-600 p-0.5"
        >
          <X class="w-3 h-3" />
        </button>
      </div>

      <!-- Options List -->
      <div class="overflow-y-auto flex-1 p-1 flex flex-col gap-0.5" style="max-height: 170px;">
        <div
          v-for="(option, idx) in filteredOptions"
          :key="option.value"
          @click="selectOption(option)"
          :class="[
            'px-2.5 py-1.5 rounded cursor-pointer flex items-center justify-between transition-colors',
            highlightedIndex === idx ? 'bg-sky-50 text-[#006bdb]' : 'text-[#000000D9] hover:bg-slate-100',
            option.value === modelValue ? 'font-semibold text-[#006bdb] bg-sky-50/70' : 'font-normal'
          ]"
        >
          <div class="flex flex-col truncate">
            <span class="truncate">{{ option.label }}</span>
            <span v-if="option.sublabel" class="text-xs text-slate-400">{{ option.sublabel }}</span>
          </div>
          <Check v-if="option.value === modelValue" class="w-3.5 h-3.5 text-[#006bdb] shrink-0" />
        </div>

        <div v-if="filteredOptions.length === 0" class="py-3 text-center text-slate-400 text-xs">
          Không tìm thấy kết quả phù hợp
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { ChevronDown, X, Search, Check } from '@lucide/vue'

const props = defineProps({
  modelValue: {
    type: [String, Number, Boolean, null],
    default: null
  },
  options: {
    type: Array,
    default: () => []
  },
  placeholder: {
    type: String,
    default: 'Chọn giá trị...'
  },
  searchPlaceholder: {
    type: String,
    default: 'Tìm kiếm nhanh...'
  },
  required: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  allowClear: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)
const searchQuery = ref('')
const containerRef = ref(null)
const searchInputRef = ref(null)
const highlightedIndex = ref(0)

const selectedOption = computed(() => {
  return props.options.find(opt => String(opt.value) === String(props.modelValue))
})

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) return props.options
  const q = searchQuery.value.toLowerCase().trim()
  return props.options.filter(opt => {
    const labelMatch = String(opt.label || '').toLowerCase().includes(q)
    const sublabelMatch = opt.sublabel ? String(opt.sublabel).toLowerCase().includes(q) : false
    return labelMatch || sublabelMatch
  })
})

const toggleDropdown = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    searchQuery.value = ''
    highlightedIndex.value = 0
    nextTick(() => {
      searchInputRef.value?.focus()
    })
  }
}

const closeDropdown = () => {
  isOpen.value = false
  searchQuery.value = ''
}

const selectOption = (option) => {
  emit('update:modelValue', option.value)
  emit('change', option.value)
  closeDropdown()
}

const clearSelection = () => {
  emit('update:modelValue', '')
  emit('change', '')
}

const navigateOptions = (dir) => {
  if (filteredOptions.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + dir + filteredOptions.value.length) % filteredOptions.value.length
}

const selectHighlighted = () => {
  if (filteredOptions.value.length > 0 && filteredOptions.value[highlightedIndex.value]) {
    selectOption(filteredOptions.value[highlightedIndex.value])
  }
}

const handleClickOutside = (e) => {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

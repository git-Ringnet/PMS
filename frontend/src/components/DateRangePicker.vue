<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ start: '24/06/2026', end: '24/06/2026' })
  }
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const popoverRef = ref(null)

const toggleOpen = () => {
  isOpen.value = !isOpen.value
}

const closePopover = (e) => {
  if (popoverRef.value && !popoverRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', closePopover)
})

onUnmounted(() => {
  document.removeEventListener('click', closePopover)
})

const quickLinks = [
  'Hôm nay', 'Hôm qua', '7 ngày trước', 'Tuần này', 'Tháng này', 
  'Kì này', 'Năm này', 'Ngày mai', '7 ngày tới', '30 ngày tới', 
  '30 ngày trước', 'Tháng trước'
]

const handleOk = () => {
  isOpen.value = false
}
</script>

<template>
  <div class="relative" ref="popoverRef" @click.stop>
    <!-- Trigger Input -->
    <div 
      class="flex items-center border border-slate-300 rounded-md bg-white cursor-pointer hover:border-sky-500 transition-colors px-3 py-1.5 min-w-[260px]"
      @click="toggleOpen"
    >
      <svg class="w-4 h-4 text-slate-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
      <span class="text-sm text-slate-700 select-none">{{ modelValue.start }} ~ {{ modelValue.end }}</span>
    </div>

    <!-- Popover -->
    <div 
      v-if="isOpen"
      class="absolute top-full left-0 mt-1 bg-white rounded-md shadow-[0_4px_20px_rgba(0,0,0,0.15)] border border-slate-200 z-50 flex flex-col w-[760px] overflow-hidden"
    >
      <div class="flex flex-1">
        <!-- Sidebar -->
        <div class="w-[140px] border-r border-slate-200 bg-white py-2 flex flex-col">
          <div 
            v-for="link in quickLinks" 
            :key="link"
            class="px-4 py-1.5 text-[13px] text-slate-600 hover:bg-slate-50 hover:text-sky-600 cursor-pointer transition-colors"
            :class="{'text-sky-600 bg-sky-50': link === 'Hôm nay'}"
          >
            {{ link }}
          </div>
        </div>

        <!-- Calendars Area -->
        <div class="flex-1 flex flex-col">
          <!-- Header -->
          <div class="px-4 py-3 border-b border-slate-200 text-[14px] text-slate-800">
            {{ modelValue.start }} ~ {{ modelValue.end }}
          </div>

          <!-- Two Calendars -->
          <div class="flex p-4 gap-6">
            <!-- Left Calendar -->
            <div class="flex-1">
              <div class="flex justify-between items-center mb-4">
                <button class="text-slate-400 hover:text-slate-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <div class="text-[13px] font-medium text-slate-700">thg 6, 2026</div>
                <button class="text-slate-400 hover:text-slate-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
              </div>
              <div class="grid grid-cols-7 gap-1 text-center text-[13px] mb-2">
                <div class="text-slate-500 py-1">T2</div>
                <div class="text-slate-500 py-1">T3</div>
                <div class="text-slate-500 py-1">T4</div>
                <div class="text-slate-500 py-1">T5</div>
                <div class="text-slate-500 py-1">T6</div>
                <div class="text-slate-500 py-1">T7</div>
                <div class="text-slate-500 py-1">CN</div>
              </div>
              <div class="grid grid-cols-7 gap-y-2 gap-x-1 text-center text-[13px]">
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">1</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">2</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">3</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">4</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">5</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">6</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">7</div>
                
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">8</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">9</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">10</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">11</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">12</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">13</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">14</div>

                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">15</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">16</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">17</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">18</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">19</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">20</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">21</div>

                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">22</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">23</div>
                <div class="py-1 cursor-pointer bg-sky-400 text-white rounded shadow-sm">24</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">25</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">26</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">27</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">28</div>

                <div class="py-1 cursor-pointer text-slate-400 border border-sky-400 rounded">29</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">30</div>
                <div class="py-1 text-slate-300">1</div>
                <div class="py-1 text-slate-300">2</div>
                <div class="py-1 text-slate-300">3</div>
                <div class="py-1 text-slate-300">4</div>
                <div class="py-1 text-slate-300">5</div>
              </div>
            </div>

            <div class="w-px bg-slate-200 mx-2"></div>

            <!-- Right Calendar -->
            <div class="flex-1">
              <div class="flex justify-between items-center mb-4">
                <button class="text-slate-400 hover:text-slate-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <div class="text-[13px] font-medium text-slate-700">thg 7, 2026</div>
                <button class="text-slate-400 hover:text-slate-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </button>
              </div>
              <div class="grid grid-cols-7 gap-1 text-center text-[13px] mb-2">
                <div class="text-slate-500 py-1">T2</div>
                <div class="text-slate-500 py-1">T3</div>
                <div class="text-slate-500 py-1">T4</div>
                <div class="text-slate-500 py-1">T5</div>
                <div class="text-slate-500 py-1">T6</div>
                <div class="text-slate-500 py-1">T7</div>
                <div class="text-slate-500 py-1">CN</div>
              </div>
              <div class="grid grid-cols-7 gap-y-2 gap-x-1 text-center text-[13px]">
                <div class="py-1 cursor-pointer text-slate-400 border border-sky-400 rounded">29</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">30</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">1</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">2</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">3</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">4</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">5</div>
                
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">6</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">7</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">8</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">9</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">10</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">11</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">12</div>

                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">13</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">14</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">15</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">16</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">17</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">18</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">19</div>

                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">20</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">21</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">22</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">23</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">24</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">25</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">26</div>

                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">27</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">28</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">29</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">30</div>
                <div class="py-1 cursor-pointer hover:bg-slate-100 rounded">31</div>
                <div class="py-1 text-slate-300">1</div>
                <div class="py-1 text-slate-300">2</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Action -->
      <div class="border-t border-slate-200 p-3 flex justify-between items-center bg-white">
        <div class="flex gap-2">
          <button class="px-3 py-1.5 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition-colors">
            7 ngày trước
          </button>
          <button class="px-3 py-1.5 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition-colors">
            7 ngày tới
          </button>
        </div>
        <button 
          @click="handleOk"
          class="px-5 py-1.5 bg-[#70c0e0] text-white text-sm rounded hover:bg-sky-400 transition-colors font-medium"
        >
          OK
        </button>
      </div>
    </div>
  </div>
</template>

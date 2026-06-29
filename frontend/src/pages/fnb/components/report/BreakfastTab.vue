<script setup>
import { ref, computed } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const selectedDate = ref('24/06/2026')
const searchRoom = ref('')
const searchGuest = ref('')
const selectedDevice = ref('')
const isNotBreakfast = ref(false)
const isLateCheck = ref(false)

const handleAdd = () => uiStore.showToast('Chức năng thêm mới đang được phát triển', 'info')
const handleReadCard = () => uiStore.showToast('Vui lòng quẹt thẻ...', 'info')
const handleAutoRead = () => uiStore.showToast('Đã bật chế độ đọc thẻ tự động', 'success')

const records = ref([
  { id: 'GAL2320', room: '1012', guest: 'Mr.Guest 1', checkIn: '22/06/2026', checkOut: '03/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL2320', room: '1012', guest: 'Mr.Guest 2', checkIn: '22/06/2026', checkOut: '03/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL2320', room: '1012', guest: 'Mr.Guest 3', checkIn: '22/06/2026', checkOut: '03/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL2321', room: '912',  guest: 'Mr.Guest 1', checkIn: '22/06/2026', checkOut: '03/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL2321', room: '912',  guest: 'Mr.Guest 2', checkIn: '22/06/2026', checkOut: '03/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL3860', room: '811',  guest: 'Ms.ZAMARALEVA SVETLANA', checkIn: '07/06/2026', checkOut: '24/06/2026', nationality: 'Russia ( Liên bang Nga )', passport: '674809199' },
  { id: 'GAL3860', room: '811',  guest: 'Mr.KSENZOV ALEKSE',      checkIn: '07/06/2026', checkOut: '24/06/2026', nationality: 'Russia ( Liên bang Nga )', passport: '674949899' },
  { id: 'GAL3962', room: '1506', guest: 'Mr.Guest 1', checkIn: '16/06/2026', checkOut: '28/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL3962', room: '1506', guest: 'Mr.Guest 2', checkIn: '16/06/2026', checkOut: '28/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL3963', room: '1204', guest: 'Mr.Guest 1', checkIn: '16/06/2026', checkOut: '28/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL3963', room: '1204', guest: 'Mr.Guest 2', checkIn: '16/06/2026', checkOut: '28/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL4412', room: '801',  guest: 'Mr.Guest 1', checkIn: '23/06/2026', checkOut: '04/07/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL4470', room: '612',  guest: 'Mr.Guest 1', checkIn: '13/06/2026', checkOut: '24/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
  { id: 'GAL4470', room: '612',  guest: 'Mr.Guest 2', checkIn: '13/06/2026', checkOut: '24/06/2026', nationality: 'Việt Nam ( Việt Nam )', passport: '' },
])

const currentPage = ref(1)
const perPage = ref(20)
const totalGuests = ref(199)
// BUG FIX: was `computed =>`, must be `computed(() =>)`
const totalPages = computed(() => Math.ceil(totalGuests.value / perPage.value))
</script>

<template>
  <div class="flex flex-col h-full bg-slate-50 overflow-hidden">
    <!-- ── Toolbar ─────────────────────────────────────────── -->
    <div class="shrink-0 bg-white border-b border-slate-200">

      <!-- Row 1: Filters -->
      <div class="flex flex-wrap items-center gap-3 px-6 py-3">
        <!-- Ngày -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide whitespace-nowrap">Ngày</span>
          <div class="relative">
            <input
              type="text"
              v-model="selectedDate"
              class="pl-3 pr-8 py-1.5 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 w-32 transition font-medium text-slate-700"
            />
            <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
        </div>

        <div class="w-px h-6 bg-slate-200 mx-0.5 hidden sm:block"></div>

        <!-- Tìm kiếm phòng -->
        <div class="flex items-center gap-1.5 flex-1 min-w-[140px] max-w-[200px]">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide whitespace-nowrap">Phòng</span>
          <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" v-model="searchRoom" placeholder="Tìm phòng..." class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 w-full transition text-slate-700" />
          </div>
        </div>

        <!-- Tìm kiếm khách -->
        <div class="flex items-center gap-1.5 flex-1 min-w-[160px] max-w-xs">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide whitespace-nowrap">Khách</span>
          <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" v-model="searchGuest" placeholder="Tìm khách..." class="pl-9 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 w-full transition text-slate-700" />
          </div>
        </div>

        <div class="w-px h-6 bg-slate-200 mx-0.5 hidden sm:block"></div>

        <!-- Thiết bị -->
        <div class="flex items-center gap-1.5">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide whitespace-nowrap">Thiết bị</span>
          <div class="relative">
            <select v-model="selectedDevice" class="pl-3 pr-8 py-1.5 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none w-36 text-slate-700 font-medium transition cursor-pointer">
              <option value="">Chọn...</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </div>

        <!-- Action buttons (right-aligned) -->
        <div class="flex items-center gap-2 ml-auto">
          <button @click="handleAdd" class="flex items-center gap-1.5 bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-600 active:scale-[0.98] transition-all shadow-sm shadow-sky-100 hover:shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Thêm
          </button>
        </div>
      </div>

      <!-- Row 2: Actions & Toggles -->
      <div class="flex flex-wrap items-center gap-3 px-6 py-2.5 border-t border-slate-100 bg-slate-50/50">
        <button @click="handleReadCard" class="flex items-center gap-1.5 bg-white border border-slate-200 text-slate-700 px-3.5 py-1.5 rounded-lg text-sm font-semibold hover:bg-sky-50 hover:border-sky-300 hover:text-sky-700 active:scale-[0.98] transition-all shadow-sm">
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          Đọc thẻ
        </button>
        <button @click="handleAutoRead" class="flex items-center gap-1.5 bg-white border border-slate-200 text-slate-700 px-3.5 py-1.5 rounded-lg text-sm font-semibold hover:bg-sky-50 hover:border-sky-300 hover:text-sky-700 active:scale-[0.98] transition-all shadow-sm">
          <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Đọc thẻ tự động
        </button>

        <div class="w-px h-5 bg-slate-200 mx-1"></div>

        <!-- Toggle: Chưa ăn sáng -->
        <button
          @click="isNotBreakfast = !isNotBreakfast"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-semibold transition-all active:scale-[0.98]"
          :class="isNotBreakfast
            ? 'bg-sky-50 border-sky-300 text-sky-700 shadow-sm shadow-sky-50'
            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
        >
          <span
            class="relative inline-flex items-center rounded-full transition-colors shrink-0"
            :class="isNotBreakfast ? 'bg-sky-500' : 'bg-slate-200'"
            style="height:18px;width:32px;"
          >
            <span
              class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform"
              :class="isNotBreakfast ? 'translate-x-4' : 'translate-x-1'"
            ></span>
          </span>
          Chưa ăn sáng
        </button>

        <!-- Toggle: Check in trễ -->
        <button
          @click="isLateCheck = !isLateCheck"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-semibold transition-all active:scale-[0.98]"
          :class="isLateCheck
            ? 'bg-amber-50 border-amber-300 text-amber-700 shadow-sm shadow-amber-50'
            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
        >
          <span
            class="relative inline-flex items-center rounded-full transition-colors shrink-0"
            :class="isLateCheck ? 'bg-amber-400' : 'bg-slate-200'"
            style="height:18px;width:32px;"
          >
            <span
              class="inline-block h-3 w-3 transform rounded-full bg-white shadow-sm transition-transform"
              :class="isLateCheck ? 'translate-x-4' : 'translate-x-1'"
            ></span>
          </span>
          Check in trễ
        </button>
      </div>
    </div>

    <!-- ── Table Content ────────────────────────────────────── -->
    <div class="flex-1 overflow-auto p-4 bg-slate-50">
      <div class="bg-white rounded-xl shadow-sm border border-slate-200/80 overflow-hidden flex flex-col h-full">
        <div class="flex-1 overflow-auto">
          <table class="w-full text-sm border-collapse whitespace-nowrap">
            <thead class="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-md">
              <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <th class="px-3 py-3 text-left w-10 border-r border-slate-100">
                  <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500" />
                </th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Mã đăng ký</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Số phòng</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Tên khách</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Ngày đến</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Ngày đi</th>
                <th class="px-4 py-3 text-left border-r border-slate-100">Quốc tịch</th>
                <th class="px-4 py-3 text-left">Số giấy tờ</th>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr
                v-for="(row, idx) in records"
                :key="idx"
                class="border-b border-slate-100 hover:bg-sky-50/20 transition-colors cursor-default"
              >
                <td class="px-3 py-2.5 border-r border-slate-100 text-center">
                  <input type="checkbox" class="w-4 h-4 rounded border-slate-300 accent-sky-500" />
                </td>
                <td class="px-4 py-2.5 text-slate-800 font-semibold border-r border-slate-100">{{ row.id }}</td>
                <td class="px-4 py-2.5 border-r border-slate-100">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-100">{{ row.room }}</span>
                </td>
                <td class="px-4 py-2.5 text-slate-800 font-medium border-r border-slate-100">{{ row.guest }}</td>
                <td class="px-4 py-2.5 text-slate-600 border-r border-slate-100">{{ row.checkIn }}</td>
                <td class="px-4 py-2.5 text-slate-600 border-r border-slate-100">{{ row.checkOut }}</td>
                <td class="px-4 py-2.5 text-slate-600 border-r border-slate-100">{{ row.nationality }}</td>
                <td class="px-4 py-2.5 text-slate-600 font-mono text-xs">{{ row.passport }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Footer & Pagination ───────────────────────────── -->
        <div class="border-t border-slate-200 px-5 py-3 bg-slate-50/50 flex items-center justify-between shrink-0">
          <div class="text-sm font-semibold text-slate-600">
            Tổng khách: <span class="text-sky-600 font-bold">{{ totalGuests }}</span>
          </div>
          <div class="flex items-center gap-1.5">
            <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40 transition-colors shadow-sm active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button
              v-for="p in [1,2,3,4,5]" :key="p"
              class="w-8 h-8 flex items-center justify-center rounded-lg border text-sm font-semibold transition-all active:scale-95"
              :class="p === currentPage
                ? 'border-sky-500 bg-sky-500 text-white shadow-sm'
                : 'border-slate-200 bg-white text-slate-600 hover:bg-sky-50 hover:border-sky-300'"
              @click="currentPage = p"
            >{{ p }}</button>
            <span class="text-slate-400 text-sm px-1 select-none">…</span>
            <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-semibold hover:bg-sky-50 hover:border-sky-300 transition-colors active:scale-95">{{ totalPages }}</button>
            <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40 transition-colors shadow-sm active:scale-95">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
            <select v-model="perPage" class="ml-2 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-600 font-medium transition cursor-pointer">
              <option :value="20">20 / trang</option>
              <option :value="50">50 / trang</option>
              <option :value="100">100 / trang</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

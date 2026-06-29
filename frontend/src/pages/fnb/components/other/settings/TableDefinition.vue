<script setup>
import { ref } from 'vue'

defineEmits(['back'])

const selectedOutlet = ref('Nhà Hàng')
const outlets = ['Nhà Hàng', 'Pool Bar', 'Room Service']

const areas = ref([
  { id: 'nha-hang', name: 'NHÀ HÀNG' }
])
const activeArea = ref('nha-hang')

const rows = ref([
  {
    id: 1,
    name: 'Hàng 1',
    tables: ['A1', 'A2', 'A3', 'A4', 'A5']
  },
  {
    id: 2,
    name: 'Hàng 2',
    tables: ['A6', 'A7', 'A8', 'A9', 'A10']
  }
])

const handleAddArea = () => {
  const name = prompt('Nhập tên khu vực mới:')
  if (name) {
    const id = name.toLowerCase().replace(/\s+/g, '-')
    areas.value.push({ id, name: name.toUpperCase() })
  }
}

const handleAddRow = () => {
  const nextId = rows.value.length + 1
  rows.value.push({
    id: nextId,
    name: `Hàng ${nextId}`,
    tables: []
  })
}

const handleAddTable = () => {
  if (rows.value.length === 0) {
    handleAddRow()
  }
  // Find row with minimum tables or just add to last row
  const targetRow = rows.value[rows.value.length - 1]
  const nextNum = rows.value.reduce((acc, row) => acc + row.tables.length, 0) + 1
  targetRow.tables.push(`A${nextNum}`)
}

const handleAddTableQuick = () => {
  const count = parseInt(prompt('Nhập số lượng bàn muốn thêm nhanh (Ví dụ: 5):', '5'))
  if (!isNaN(count) && count > 0) {
    for (let i = 0; i < count; i++) {
      handleAddTable()
    }
  }
}

const handleDeleteRow = (rowId) => {
  if (confirm('Bạn có chắc chắn muốn xoá hàng này không?')) {
    rows.value = rows.value.filter(r => r.id !== rowId)
  }
}

const handleDeleteTable = (row, tableIdx) => {
  row.tables.splice(tableIdx, 1)
}
</script>

<template>
  <div class="flex-1 flex flex-col bg-slate-50 p-6 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between shrink-0 mb-4">
      <div class="flex items-center gap-2">
        <button @click="$emit('back')" class="p-1.5 rounded-full hover:bg-slate-200 text-slate-600 transition active:scale-95" title="Quay lại">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 class="text-base font-bold text-slate-800">Định nghĩa bàn</h1>
      </div>

      <!-- Help symbol -->
      <button class="w-7 h-7 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm" title="Trợ giúp">
        <span class="text-sm font-bold">?</span>
      </button>
    </div>

    <!-- Outlet picker at top center -->
    <div class="flex justify-center mb-6 shrink-0">
      <div class="relative w-full max-w-lg bg-white rounded-xl shadow-sm border border-slate-200/80 p-2 flex items-center gap-3">
        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider pl-2 whitespace-nowrap">Outlet</label>
        <div class="relative flex-1">
          <select v-model="selectedOutlet" class="w-full text-center py-2 pl-3 pr-8 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition cursor-pointer font-semibold uppercase tracking-wider">
            <option v-for="o in outlets" :key="o" :value="o">{{ o }}</option>
          </select>
          <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Layout: Left Sidebar for Areas, Right for Grid -->
    <div class="flex-1 flex gap-6 overflow-hidden min-h-0">
      <!-- Left Areas Sidebar -->
      <div class="w-60 bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm flex flex-col shrink-0">
        <button @click="handleAddArea" class="w-full flex items-center justify-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition mb-4">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Thêm khu vực
        </button>

        <div class="flex-1 overflow-y-auto flex flex-col gap-1 pr-1">
          <button
            v-for="a in areas"
            :key="a.id"
            @click="activeArea = a.id"
            class="w-full text-left px-4 py-2.5 rounded-lg text-xs font-bold tracking-wide uppercase transition relative overflow-hidden"
            :class="activeArea === a.id ? 'bg-sky-100 text-sky-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'"
          >
            <!-- Highlight bar -->
            <span v-if="activeArea === a.id" class="absolute left-0 top-1 bottom-1 w-[3px] rounded-r-full bg-sky-500"></span>
            {{ a.name }}
          </button>
        </div>
      </div>

      <!-- Right Main Grid area -->
      <div class="flex-1 bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm flex flex-col overflow-hidden">
        <!-- Top Toolbar -->
        <div class="flex items-center justify-end gap-3 mb-6 shrink-0">
          <button @click="handleAddTable" class="flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm hover:shadow active:scale-[0.98] transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Thêm bàn
          </button>
          <button @click="handleAddTableQuick" class="flex items-center gap-2 bg-sky-50 border border-sky-200 hover:bg-sky-100/70 text-sky-700 px-4 py-2 rounded-lg text-sm font-semibold shadow-sm active:scale-[0.98] transition">
            <!-- Lightning icon -->
            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            Thêm bàn nhanh
          </button>
        </div>

        <!-- Grid Container -->
        <div class="flex-1 overflow-y-auto pr-2 flex flex-col gap-6">
          <div v-for="row in rows" :key="row.id" class="flex flex-col gap-3 border-b border-slate-100 pb-4 last:border-0 last:pb-0">
            <!-- Row header with label and Delete button -->
            <div class="flex items-center gap-3">
              <span class="text-sm font-bold text-slate-700 uppercase tracking-wide">{{ row.name }}</span>
              <button @click="handleDeleteRow(row.id)" class="flex items-center gap-1 bg-rose-50 hover:bg-rose-100/80 text-rose-600 px-2.5 py-1 rounded-md text-xs font-semibold active:scale-[0.95] transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Xóa
              </button>
            </div>

            <!-- List of tables in row -->
            <div class="flex flex-wrap gap-4">
              <div v-for="(tbl, tIdx) in row.tables" :key="tbl" class="relative group">
                <div class="w-28 h-16 rounded-xl border border-slate-200/80 bg-white hover:bg-sky-50/20 hover:border-sky-300 flex items-center justify-center shadow-sm transition cursor-pointer font-bold text-sky-600">
                  {{ tbl }}
                </div>
                
                <!-- Tiny delete dot top-right -->
                <button @click="handleDeleteTable(row, tIdx)" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-500 hover:bg-rose-600 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-sm active:scale-90">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Placeholder if row empty -->
              <div v-if="row.tables.length === 0" class="text-xs text-slate-400 italic py-2 pl-2">
                Hàng này chưa có bàn nào. Nhấn "+ Thêm bàn" để bắt đầu.
              </div>
            </div>
          </div>

          <!-- Empty fallback -->
          <div v-if="rows.length === 0" class="flex-1 flex flex-col items-center justify-center text-slate-400 py-12">
            <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9h18M9 21V9m6 12V9m3-6H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2z" />
            </svg>
            <span class="text-sm font-medium">Chưa có hàng nào được định nghĩa.</span>
            <button @click="handleAddRow" class="mt-3 text-xs bg-sky-500 hover:bg-sky-600 text-white px-3 py-1.5 rounded-lg font-semibold active:scale-95 transition">Thêm hàng mới</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

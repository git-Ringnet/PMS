<script setup>
import { computed, ref, watch, nextTick } from 'vue'
import http from '@/services/http'
import { languages, SOURCE_LANG } from '@/utils/i18n-dictionary'
import { editorState, isEditMode, setEditMode, reloadTranslations } from '@/utils/translator'

// Popup sửa bản dịch ngay trên màn hình (Chế độ dịch) + thanh nhắc ở cuối màn hình.

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const entry = ref(null)
const form = ref({})
const firstInput = ref(null)

const popupStyle = computed(() => {
  const width = 380
  const a = editorState.anchor
  if (!a) return { width: `${width}px`, top: '20%', left: `calc(50% - ${width / 2}px)` }
  const left = Math.min(Math.max(8, a.left), window.innerWidth - width - 8)
  const below = a.bottom + 8
  const top = below + 340 > window.innerHeight ? Math.max(8, a.top - 348) : below
  return { width: `${width}px`, top: `${top}px`, left: `${left}px` }
})

function defaultOf(code) {
  if (code === SOURCE_LANG) return entry.value?.source || editorState.source
  return entry.value?.values?.[code]?.default || ''
}

async function load() {
  loading.value = true
  error.value = ''
  entry.value = null
  form.value = {}
  try {
    const res = await http.get('/i18n/admin/lookup', { params: { source: editorState.source } })
    entry.value = res.data.data
    const values = {}
    for (const lang of languages.value) {
      values[lang.code] = entry.value?.values?.[lang.code]?.value ?? ''
    }
    form.value = values
    await nextTick()
    firstInput.value?.[0]?.focus()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Không tải được bản dịch.'
  } finally {
    loading.value = false
  }
}

watch(() => editorState.open && editorState.source, (on) => { if (on) load() })

async function save() {
  saving.value = true
  error.value = ''
  try {
    await http.put('/i18n/admin/entries', {
      items: [{
        id: entry.value?.id || null,
        source: editorState.source,
        values: form.value,
        area: editorState.area,
        route: editorState.route
      }]
    })
    await reloadTranslations()
    editorState.open = false
  } catch (e) {
    error.value = e?.response?.data?.message || 'Không lưu được bản dịch.'
  } finally {
    saving.value = false
  }
}

function resetLang(code) {
  form.value[code] = ''
}

function close() {
  editorState.open = false
}
</script>

<template>
  <div class="i18n-editor-root">
    <!-- Popup sửa bản dịch -->
    <div v-if="editorState.open" class="fixed inset-0 z-[10000]" @click.self="close">
      <div
        class="absolute bg-white border border-amber-300 rounded-xl shadow-2xl text-slate-800 text-xs overflow-hidden"
        :style="popupStyle"
        @keydown.esc="close"
        @keydown.ctrl.enter="save"
      >
        <div class="px-3 py-2 bg-amber-50 border-b border-amber-200">
          <div class="flex items-center justify-between gap-2">
            <span class="font-bold text-amber-800">Sửa bản dịch</span>
            <button class="border-none bg-transparent cursor-pointer text-slate-500 hover:text-slate-800 text-base leading-none" @click="close">×</button>
          </div>
          <div class="mt-0.5 text-[11px] text-slate-600 truncate" :title="editorState.area">📍 {{ editorState.area || 'Màn hình hiện tại' }}</div>
        </div>

        <div class="p-3 space-y-2 max-h-[300px] overflow-y-auto">
          <div class="text-[11px] text-slate-500">
            Chữ gốc: <span class="font-semibold text-slate-800">{{ editorState.source }}</span>
          </div>
          <div v-if="loading" class="py-4 text-center text-slate-400">Đang tải...</div>
          <template v-else>
            <div v-for="lang in languages" :key="lang.code" class="flex items-center gap-2">
              <label class="w-24 shrink-0 font-semibold text-slate-600 truncate" :title="lang.name">
                {{ lang.flag || '' }} {{ lang.name }}
              </label>
              <input
                ref="firstInput"
                v-model="form[lang.code]"
                :placeholder="defaultOf(lang.code) || '(chưa dịch)'"
                class="flex-1 min-w-0 px-2 py-1 border border-slate-300 rounded-md focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-300"
              />
              <button
                v-if="form[lang.code]"
                class="border-none bg-transparent cursor-pointer text-slate-400 hover:text-sky-600"
                title="Khôi phục mặc định"
                @click="resetLang(lang.code)"
              >↺</button>
            </div>
            <p class="text-[10.5px] text-slate-400 leading-snug">
              Ô để trống sẽ dùng chữ mặc định (chữ mờ trong ô). Ctrl+Enter để lưu.
            </p>
          </template>
          <div v-if="error" class="text-red-600 font-semibold">{{ error }}</div>
        </div>

        <div class="px-3 py-2 border-t border-slate-100 flex items-center justify-between">
          <a href="/system?tab=language" target="_blank" class="text-sky-600 hover:underline">Mở trang quản lý</a>
          <div class="flex gap-2">
            <button class="px-3 py-1 rounded-md border border-slate-300 bg-white cursor-pointer hover:bg-slate-50" @click="close">Hủy</button>
            <button
              class="px-3 py-1 rounded-md border-none bg-amber-500 text-white font-bold cursor-pointer hover:bg-amber-600 disabled:opacity-50"
              :disabled="saving || loading"
              @click="save"
            >{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Thanh nhắc khi đang bật Chế độ dịch -->
    <div
      v-if="isEditMode"
      class="fixed bottom-3 left-1/2 -translate-x-1/2 z-[9999] flex items-center gap-3 px-4 py-2 rounded-full bg-amber-500 text-white text-xs font-semibold shadow-xl"
    >
      <span>🌐 Chế độ dịch đang bật — bấm vào chữ có viền chấm để sửa bản dịch</span>
      <button class="px-2 py-0.5 rounded-full border border-white/70 bg-transparent text-white cursor-pointer hover:bg-white/20" @click="setEditMode(false)">Tắt</button>
    </div>

    <!-- Thông báo khi "Xem vị trí" không tìm thấy chữ -->
    <div
      v-if="editorState.notice"
      class="fixed top-16 left-1/2 -translate-x-1/2 z-[9999] max-w-md px-4 py-2 rounded-lg bg-slate-800 text-white text-xs shadow-xl flex items-start gap-3"
    >
      <span>{{ editorState.notice }}</span>
      <button class="border-none bg-transparent text-white/70 hover:text-white cursor-pointer" @click="editorState.notice = ''">×</button>
    </div>
  </div>
</template>

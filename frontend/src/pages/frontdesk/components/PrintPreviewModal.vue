<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { LoaderCircle, Minus, Plus, Printer, X } from '@lucide/vue'
import { printHtmlDocument, wrapPrintDocument } from '@/utils/print-html'

// Xem trước mẫu in giống trang Báo cáo: chỉ khi bấm icon máy in mới mở hộp thoại in.
// - html: tài liệu HTML hoàn chỉnh do máy chủ dựng (vd. Phiếu thu) — hiển thị trong iframe.
// - slot mặc định: tờ in dựng bằng Vue (vd. Hóa đơn thanh toán); in bằng cách sao chép đúng phần tờ in.
const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  html: { type: String, default: '' },
  showPriceToggle: { type: Boolean, default: false },
  showPrice: { type: Boolean, default: true },
})
const emit = defineEmits(['close', 'update:showPrice'])

const zoom = ref(100)
const frameRef = ref(null)
const paperRef = ref(null)

const changeZoom = (step) => {
  zoom.value = Math.min(200, Math.max(50, zoom.value + step))
}

const resizeFrame = () => {
  const frame = frameRef.value
  const doc = frame?.contentDocument
  if (!doc?.documentElement) return
  frame.style.height = `${Math.max(doc.documentElement.scrollHeight, 600)}px`
}

watch(() => props.html, async () => {
  zoom.value = 100
  await nextTick()
  resizeFrame()
})

const handlePrint = () => {
  if (props.loading) return
  if (props.html) {
    printHtmlDocument(props.html)
    return
  }
  if (paperRef.value) printHtmlDocument(wrapPrintDocument(paperRef.value.innerHTML, props.title))
}

const handleKeyDown = (event) => {
  if (event.key === 'Escape' && props.show) emit('close')
}
onMounted(() => window.addEventListener('keydown', handleKeyDown))
onUnmounted(() => window.removeEventListener('keydown', handleKeyDown))
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-2 md:p-4">
      <div class="flex h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl">
        <div
          class="flex shrink-0 items-center justify-between px-4 py-2 font-semibold text-white"
          :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        >
          <span class="text-xs font-semibold tracking-wide">{{ title }}</span>
          <button type="button" class="cursor-pointer rounded p-1 text-white hover:bg-white/20" title="Đóng" @click="emit('close')">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="flex h-10 shrink-0 items-center justify-center gap-2 border-b border-slate-200 bg-slate-50 px-3">
          <button type="button" class="btn-pms-secondary h-8 w-8 justify-center p-0" title="Thu nhỏ" @click="changeZoom(-10)">
            <Minus class="h-4 w-4" />
          </button>
          <span class="w-12 text-center font-semibold tabular-nums text-[#000000D9]">{{ zoom }}%</span>
          <button type="button" class="btn-pms-secondary h-8 w-8 justify-center p-0" title="Phóng to" @click="changeZoom(10)">
            <Plus class="h-4 w-4" />
          </button>
          <label v-if="showPriceToggle" class="ml-3 flex h-8 cursor-pointer select-none items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 font-semibold text-[#000000D9]">
            <input
              type="checkbox"
              class="h-4 w-4 cursor-pointer rounded"
              :checked="showPrice"
              @change="emit('update:showPrice', $event.target.checked)"
            />
            <span>Hiện giá</span>
          </label>
          <button
            type="button"
            class="btn-pms-primary ml-3 h-8 gap-1.5 px-3"
            :disabled="loading"
            title="In"
            @click="handlePrint"
          >
            <Printer class="h-4 w-4" />
            <span>In</span>
          </button>
        </div>

        <div class="relative flex-1 overflow-auto bg-slate-200 p-4">
          <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/60">
            <LoaderCircle class="h-6 w-6 animate-spin text-slate-500" />
          </div>
          <div class="mx-auto origin-top" :style="{ zoom: zoom / 100, width: '210mm' }">
            <iframe
              v-if="html"
              ref="frameRef"
              :srcdoc="html"
              class="block w-full border-0 bg-white shadow-md"
              style="min-height: 297mm"
              @load="resizeFrame"
            ></iframe>
            <div v-else ref="paperRef" class="bg-white shadow-md" style="min-height: 297mm">
              <slot />
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Printer, Save, Trash2, X } from '@lucide/vue'
import http from '@/services/http'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const props = defineProps({
  show: Boolean,
  payment: { type: Object, default: null },
  systemDate: { type: String, default: '' },
})

const emit = defineEmits(['close', 'success'])
const loading = ref(false)
const error = ref('')
const paymentInfo = ref(null)
const settlements = ref([])
const paymentMethods = ref([])
const form = ref({ payment_method_id: '', payment_date: '', amount: 0, description: '' })
const settlementPendingDelete = ref(null)
const amountInput = ref('0')

const modalRef = ref(null)
const modalPosition = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
const dragStart = ref({ x: 0, y: 0, left: 0, top: 0 })

function clampModalPosition(x, y) {
  const modal = modalRef.value
  if (!modal) return { x, y }

  const margin = 16
  const width = modal.offsetWidth
  const height = modal.offsetHeight
  const baseLeft = (window.innerWidth - width) / 2
  const baseTop = (window.innerHeight - height) / 2
  const minX = margin - baseLeft
  const maxX = window.innerWidth - margin - width - baseLeft
  const minY = margin - baseTop
  const maxY = window.innerHeight - margin - height - baseTop

  return {
    x: minX <= maxX ? Math.min(Math.max(x, minX), maxX) : 0,
    y: minY <= maxY ? Math.min(Math.max(y, minY), maxY) : 0,
  }
}

function beginModalDrag(event) {
  if (event.button !== 0 || event.target.closest('button, input, select, textarea')) return
  isDraggingModal.value = true
  dragStart.value = {
    x: event.clientX,
    y: event.clientY,
    left: modalPosition.value.x,
    top: modalPosition.value.y,
  }
  window.addEventListener('mousemove', onModalDrag)
  window.addEventListener('mouseup', stopModalDrag)
}

function onModalDrag(event) {
  if (!isDraggingModal.value) return
  const rawX = dragStart.value.left + (event.clientX - dragStart.value.x)
  const rawY = dragStart.value.top + (event.clientY - dragStart.value.y)
  modalPosition.value = clampModalPosition(rawX, rawY)
}

function stopModalDrag() {
  if (!isDraggingModal.value) return
  isDraggingModal.value = false
  window.removeEventListener('mousemove', onModalDrag)
  window.removeEventListener('mouseup', stopModalDrag)
}

function handleKeyDown(event) {
  if (event.key === 'Escape' && props.show) {
    if (settlementPendingDelete.value) {
      settlementPendingDelete.value = null
    } else {
      emit('close')
    }
  }
}

const money = value => new Intl.NumberFormat('en-US').format(Number(value) || 0)
const remainingAmount = computed(() => Number(paymentInfo.value?.remaining_amount) || 0)

function formatHistoryDate(value, time) {
  const rawDate = String(value || '')
  const dateMatch = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!dateMatch) return rawDate

  const isoTime = rawDate.match(/T(\d{2}:\d{2})/)
  const validTime = /^\d{2}:\d{2}/.test(String(time || ''))
    ? String(time).slice(0, 5)
    : isoTime?.[1]

  return `${dateMatch[3]}/${dateMatch[2]}/${dateMatch[1]}${validTime ? ` ${validTime}` : ''}`
}

function updateAmount(value) {
  const digits = String(value ?? '').replace(/\D/g, '')
  form.value.amount = Number(digits) || 0
  amountInput.value = digits ? money(form.value.amount) : ''
}

function showRawAmount() {
  amountInput.value = form.value.amount ? String(form.value.amount) : ''
}

function formatAmount() {
  amountInput.value = money(form.value.amount)
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
  })[char])
}

function printDebtSettlement() {
  if (!paymentInfo.value) return

  const historyRows = settlements.value.map(item => `
    <tr>
      <td>${escapeHtml(formatHistoryDate(item.payment_date, item.payment_time))}</td>
      <td>${escapeHtml(item.payment_method?.name || item.payment_method_id)}</td>
      <td>${escapeHtml(item.description)}</td>
      <td class="amount">${escapeHtml(money(item.amount))}</td>
      <td>${escapeHtml(item.created_by)}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="empty">Chưa có dữ liệu</td></tr>'
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) {
    error.value = 'Trình duyệt đang chặn cửa sổ in.'
    return
  }

  printWindow.document.write(`<!doctype html>
    <html lang="vi"><head><meta charset="utf-8"><title>Thanh toán công nợ</title>
    <style>
      body { font-family: Arial, sans-serif; color: #1f2937; margin: 28px; font-size: 12px; }
      h1 { margin: 0 0 22px; font-size: 18px; color: #087fda; }
      .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 56px; margin-bottom: 22px; font-weight: 600; }
      table { width: 100%; border-collapse: collapse; margin-top: 8px; }
      th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
      th { background: #f1f5f9; } .amount { text-align: right; } .empty { text-align: center; color: #64748b; }
    </style></head><body>
      <h1>Thanh toán công nợ</h1>
      <div class="summary">
        <div>Công ty: ${escapeHtml(paymentInfo.value.company_name || '—')}</div>
        <div>Mã thanh toán: ${escapeHtml(paymentInfo.value.payment_code || props.payment?.paymentCode || '—')}</div>
        <div>Tổng số tiền: ${escapeHtml(money(paymentInfo.value.amount))}</div>
        <div>Số tiền còn lại: ${escapeHtml(money(remainingAmount.value))}</div>
      </div>
      <strong>Lịch sử</strong>
      <table><thead><tr><th>Ngày</th><th>Hình thức thanh toán</th><th>Mô tả</th><th>Số tiền</th><th>Người dùng</th></tr></thead>
      <tbody>${historyRows}</tbody></table>
    </body></html>`)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

async function load() {
  if (!props.show || !props.payment?.id) return

  loading.value = true
  error.value = ''
  try {
    const [detail, methods] = await Promise.all([
      http.get(`/payments/${props.payment.id}/debt-settlements`),
      http.get('/payment-methods'),
    ])
    paymentInfo.value = detail.data?.data?.payment || null
    settlements.value = detail.data?.data?.settlements || []
    const items = methods.data?.data || methods.data || []
    paymentMethods.value = items.filter(method => (
      String(method.code || '').toUpperCase() !== 'AC'
      && Number(method.payment_group) !== 5
      && !method.is_free
      && !method.is_inactive
    ))
    form.value = {
      payment_method_id: paymentMethods.value[0]?.code || paymentMethods.value[0]?.id || '',
      payment_date: props.systemDate || new Date().toISOString().slice(0, 10),
      amount: remainingAmount.value,
      description: '',
    }
    formatAmount()
  } catch (err) {
    error.value = err.response?.data?.message || 'Không thể tải công nợ.'
  } finally {
    loading.value = false
  }
}

watch(() => props.show, visible => {
  if (visible) {
    modalPosition.value = { x: 0, y: 0 }
    window.removeEventListener('keydown', handleKeyDown)
    window.addEventListener('keydown', handleKeyDown)
  } else {
    stopModalDrag()
    window.removeEventListener('keydown', handleKeyDown)
  }
})

onBeforeUnmount(() => {
  stopModalDrag()
  window.removeEventListener('keydown', handleKeyDown)
})

watch(() => [props.show, props.payment?.id], load, { immediate: true })

async function submit() {
  const amount = Number(form.value.amount)
  if (!(amount > 0) || amount > remainingAmount.value) {
    error.value = 'Số tiền giải trừ phải lớn hơn 0 và không vượt quá công nợ còn lại.'
    return
  }

  loading.value = true
  error.value = ''
  try {
    await http.post(`/payments/${props.payment.id}/debt-settlements`, {
      ...form.value,
      amount,
      payment_time: new Date().toTimeString().slice(0, 5),
    })
    await load()
    emit('success', 'Đã thêm giải trừ công nợ thành công!')
  } catch (err) {
    error.value = err.response?.data?.message || 'Không thể lưu giải trừ công nợ.'
  } finally {
    loading.value = false
  }
}

async function removeSettlement(item) {
  if (Number(item.edit_flag) === 1) return

  loading.value = true
  try {
    await http.delete(`/payments/${props.payment.id}/debt-settlements/${item.id}`)
    await load()
    emit('success', 'Đã xóa giải trừ công nợ thành công!')
  } catch (err) {
    error.value = err.response?.data?.message || 'Không thể xóa giải trừ công nợ.'
  } finally {
    loading.value = false
  }
}

function requestDelete(item) {
  if (Number(item.edit_flag) !== 1) settlementPendingDelete.value = item
}

async function confirmDelete() {
  const item = settlementPendingDelete.value
  settlementPendingDelete.value = null
  if (item) await removeSettlement(item)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4 font-sans select-none" @click.self="emit('close')">
    <section
      ref="modalRef"
      class="relative w-[920px] max-w-[calc(100vw-32px)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      :style="{ transform: `translate3d(${modalPosition.x}px, ${modalPosition.y}px, 0)` }"
    >
      <LoadingOverlay :show="loading" />
      <header
        class="flex h-9 items-center justify-between px-4 text-white cursor-move select-none"
        :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        @mousedown="beginModalDrag"
      >
        <span class="text-xs font-semibold tracking-wide">Thanh Toán Công Nợ</span>
        <button class="rounded p-1 hover:bg-white/20 active:scale-90 cursor-pointer" @click="emit('close')"><X class="h-4 w-4" /></button>
      </header>

      <div class="p-4 text-[11px] text-slate-700">
        <div class="mb-4 grid grid-cols-2 gap-x-16 gap-y-2 font-semibold">
          <div>Công ty: {{ paymentInfo?.company_name || '—' }}</div>
          <div>Mã thanh toán: {{ paymentInfo?.payment_code || payment?.paymentCode || '—' }}</div>
          <div>Tổng số tiền: {{ money(paymentInfo?.amount) }}</div>
          <div>Số tiền còn lại: {{ money(remainingAmount) }}</div>
        </div>

        <p v-if="error" class="mb-3 rounded border border-red-200 bg-red-50 px-3 py-1.5 text-xs text-red-700">{{ error }}</p>

        <div class="grid grid-cols-[430px_1fr] gap-3">
          <div>
            <div class="grid grid-cols-[265px_155px] gap-2">
              <label>
                <span class="mb-1 block font-semibold">Hình thức thanh toán</span>
                <select v-model="form.payment_method_id" class="h-7 w-full rounded border border-slate-300 bg-white px-2 focus:border-blue-500 focus:outline-none" :disabled="loading">
                  <option value="" disabled>Select Value</option>
                  <option v-for="method in paymentMethods" :key="method.id || method.code" :value="method.code || method.id">{{ method.name }}</option>
                </select>
              </label>
              <label>
                <span class="mb-1 block font-semibold">Ngày</span>
                <input v-model="form.payment_date" type="date" class="h-7 w-full rounded border border-slate-300 px-2 focus:border-blue-500 focus:outline-none" :disabled="loading" />
              </label>
            </div>
            <label class="mt-2 block">
              <span class="mb-1 block font-semibold">Số tiền</span>
              <input :value="amountInput" inputmode="numeric" class="h-7 w-full rounded border border-slate-300 px-2 text-right focus:border-blue-500 focus:outline-none" :disabled="loading" @input="updateAmount($event.target.value)" @focus="showRawAmount" @blur="formatAmount" />
            </label>
          </div>
          <label>
            <span class="mb-1 block font-semibold">Mô tả</span>
            <textarea v-model="form.description" maxlength="500" class="h-[93px] w-full resize-none rounded border border-sky-300 px-2 py-1.5 focus:border-blue-500 focus:outline-none" :disabled="loading" />
          </label>
        </div>

        <div class="mt-3">
          <div class="mb-1 font-semibold">Lịch sử</div>
          <div class="max-h-[180px] overflow-y-auto rounded border border-slate-200">
            <table class="w-full border-collapse text-[11px]">
              <thead class="sticky top-0 bg-slate-100 text-left text-slate-700">
                <tr>
                  <th class="border-b border-r border-slate-200 px-2 py-2 font-semibold">Ngày</th>
                  <th class="border-b border-r border-slate-200 px-2 py-2 font-semibold">Hình thức thanh toán</th>
                  <th class="border-b border-r border-slate-200 px-2 py-2 font-semibold">Mô tả</th>
                  <th class="border-b border-r border-slate-200 px-2 py-2 text-right font-semibold">Số tiền</th>
                  <th class="border-b border-r border-slate-200 px-2 py-2 font-semibold">Người dùng</th>
                  <th class="border-b border-slate-200 px-2 py-2 text-center font-semibold">Xóa</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!loading && settlements.length === 0">
                  <td colspan="6" class="px-2 py-8 text-center text-slate-400">Chưa có dữ liệu</td>
                </tr>
                <tr v-for="item in settlements" :key="item.id" :class="Number(item.edit_flag) === 1 ? 'bg-slate-50 text-slate-400 line-through' : ''">
                  <td class="border-t border-r border-slate-100 px-2 py-1.5 whitespace-nowrap">{{ formatHistoryDate(item.payment_date, item.payment_time) }}</td>
                  <td class="border-t border-r border-slate-100 px-2 py-1.5">{{ item.payment_method?.name || item.payment_method_id }}</td>
                  <td class="border-t border-r border-slate-100 px-2 py-1.5">{{ item.description || '' }}</td>
                  <td class="border-t border-r border-slate-100 px-2 py-1.5 text-right">{{ money(item.amount) }}</td>
                  <td class="border-t border-r border-slate-100 px-2 py-1.5">{{ item.created_by || '' }}</td>
                  <td class="border-t border-slate-100 px-2 py-1 text-center">
                    <button class="rounded bg-[#0788eb] p-1 text-white hover:bg-blue-600 disabled:opacity-40" :disabled="loading || Number(item.edit_flag) === 1" title="Xóa" @click="requestDelete(item)"><Trash2 class="h-3.5 w-3.5" /></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-2.5">
        <button class="btn-pms-close" @click="emit('close')"><X class="h-3.5 w-3.5" /> Đóng</button>
        <button class="btn-pms-secondary" :disabled="loading" @click="printDebtSettlement"><Printer class="h-3.5 w-3.5" /> In</button>
        <button class="btn-pms-primary" :disabled="loading || remainingAmount <= 0" @click="submit"><Save class="h-3.5 w-3.5" /> Thêm</button>
      </footer>
    </section>

    <div v-if="settlementPendingDelete" class="absolute inset-0 z-10 flex items-center justify-center bg-black/35" @click.self="settlementPendingDelete = null">
      <section class="w-[360px] overflow-hidden rounded-xl bg-white shadow-2xl border border-slate-200">
        <header
          class="flex h-8 items-center justify-between px-3 text-xs font-semibold text-white select-none"
          :style="{ background: 'var(--pms-custom-theme, #006bdb)' }"
        >
          <span>Xác Nhận Xóa</span>
          <button class="rounded p-1 hover:bg-white/15 cursor-pointer" @click="settlementPendingDelete = null"><X class="h-4 w-4" /></button>
        </header>
        <div class="px-4 py-5 text-xs text-slate-700">Bạn có muốn xóa dòng giải trừ công nợ này?</div>
        <footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-2">
          <button class="btn-pms-close" @click="settlementPendingDelete = null">Hủy</button>
          <button class="btn-pms-danger" @click="confirmDelete">Đồng Ý</button>
        </footer>
      </section>
    </div>
  </div>
</template>

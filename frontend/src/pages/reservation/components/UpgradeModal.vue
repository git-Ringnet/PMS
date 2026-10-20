<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/20 z-[99999] flex items-center justify-center p-4 animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[600px] overflow-hidden border border-slate-300 flex flex-col max-h-[90vh]"
      :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
    >
      <!-- HEADER -->
      <div 
        class="text-white flex justify-between items-center px-4 py-2.5 shrink-0 select-none cursor-move rounded-t-xl"
        :style="{ background: topbarThemeBg }"
        @mousedown="startDragModal"
      >
        <div class="flex items-center space-x-2 font-semibold text-xs tracking-wider text-white">
          <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          <span>Nâng Hạng Phòng</span>
        </div>
        <button class="hover:bg-white/10 p-1.5 rounded-lg cursor-pointer border-none bg-transparent text-white transition-colors flex items-center justify-center" @click="close" title="Đóng (Esc)">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- BODY -->
      <div class="p-5 space-y-4 flex-1 overflow-y-auto">
        <!-- PHÒNG ĐÃ CHỌN -->
        <div class="text-xs font-semibold text-[#000000D9] tracking-wider uppercase">Phòng Đã Chọn ({{ targetRooms.length }})</div>
        <div class="border border-slate-200 rounded-lg overflow-hidden shadow-xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-[#000000D9] font-semibold h-8 select-none">
                <th class="p-2.5 text-center align-middle border border-slate-200">Phòng</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Hạng Hiện Tại</th>
                <th class="p-2.5 text-center align-middle border border-slate-200">Khách</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in targetRooms" :key="r.id" class="border-b border-slate-100 hover:bg-slate-50/30 h-9 font-normal text-[#000000D9]">
                <td class="p-2.5 text-center font-normal text-[#000000D9] border-slate-200">{{ r.roomNumber || 'Chưa gán' }}</td>
                <td class="p-2.5 border-slate-200">{{ r.type || r.shape || '-' }}</td>
                <td class="p-2.5 text-[#000000D9] border-slate-200">{{ r.guestName || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- CHỌN HẠNG MỚI (GRID LAYOUT) -->
        <div class="grid grid-cols-2 gap-4 text-xs text-[#000000D9]">
          <!-- Loại phòng -->
          <div>
            <label class="block text-[#000000D9] mb-1.5 font-semibold text-xs">Loại Phòng <span class="text-red-500">*</span></label>
            <select 
              v-model="upgradeTargetClassId" 
              @change="handleClassChange"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg h-9 px-3 text-xs font-normal focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308] cursor-pointer"
              :class="!upgradeTargetClassId ? 'text-[#A8B0BF]' : 'text-[#000000D9]'"
            >
              <option :value="null" disabled hidden>Chọn loại phòng</option>
              <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id" class="text-[#000000D9]">{{ rc.name }}</option>
            </select>
          </div>

          <!-- Dạng phòng -->
          <div>
            <label class="block text-[#000000D9] mb-1.5 font-semibold text-xs">Dạng Phòng <span class="text-red-500">*</span></label>
            <select 
              v-model="upgradeTargetFormId" 
              @change="handleFormChange"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg h-9 px-3 text-xs font-normal focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308] cursor-pointer"
              :class="!upgradeTargetFormId ? 'text-[#A8B0BF]' : 'text-[#000000D9]'"
            >
              <option :value="null" disabled hidden>Chọn dạng phòng</option>
              <option v-for="rf in availableForms" :key="rf.id" :value="rf.id" class="text-[#000000D9]">{{ rf.name }}</option>
            </select>
          </div>

          <!-- Mã giá phòng -->
          <div>
            <label class="block text-[#000000D9] mb-1.5 font-semibold text-xs">Mã Giá Phòng</label>
            <div class="relative flex items-center">
              <select 
                v-model="upgradeTargetRateCode" 
                @change="handleRateCodeChange"
                :disabled="!upgradeChangePrice"
                class="w-full border rounded-lg h-9 px-3 text-xs font-normal focus:outline-none transition-colors border-slate-200"
                :class="[
                  !upgradeChangePrice ? 'bg-[#f1f1f1] text-[#a8b0bf] cursor-not-allowed' : 'bg-white focus:ring-1 focus:ring-sky-500 cursor-pointer',
                  upgradeChangePrice && upgradeTargetRateCode ? 'pr-9 text-[#000000D9]' : '',
                  !upgradeTargetRateCode ? 'text-[#A8B0BF]' : 'text-[#000000D9]'
                ]"
              >
                <option value="" disabled hidden>Chọn mã giá phòng</option>
                <option v-for="rc in roomRateCodes" :key="rc.id" :value="rc.Ma" class="text-[#000000D9]">{{ rc.Ma }}</option>
              </select>
              <button 
                v-if="upgradeChangePrice && upgradeTargetRateCode"
                type="button"
                @click.stop="clearRateCode"
                class="absolute right-7 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 bg-transparent border-none cursor-pointer p-0.5 flex items-center justify-center z-10 select-none"
                title="Xóa mã giá"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Giá phòng -->
          <div>
            <label class="block text-[#000000D9] mb-1.5 font-semibold text-xs">Giá Phòng</label>
            <input 
              type="text" 
              :value="formatCurrencyInput(upgradeTargetPrice)"
              @input="e => upgradeTargetPrice = cleanCurrencyValue(e.target.value)"
              :disabled="!upgradeChangePrice"
              placeholder="0"
              class="w-full border rounded-lg h-9 px-3 text-xs font-normal focus:outline-none transition-colors border-slate-200 text-right"
              :class="!upgradeChangePrice ? 'bg-[#f1f1f1] text-[#a8b0bf] cursor-not-allowed' : 'bg-white text-[#000000D9] focus:ring-1 focus:ring-sky-500'"
            />
          </div>

          <!-- Checkbox Thay đổi giá -->
          <div class="col-span-2 flex items-center gap-2 py-1 select-none">
            <input 
              id="upgradeChangePrice"
              type="checkbox" 
              v-model="upgradeChangePrice"
              @change="handleToggleChangePrice"
              class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5 cursor-pointer"
            />
            <label for="upgradeChangePrice" class="text-xs font-semibold text-[#000000D9] cursor-pointer">Thay đổi giá</label>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 shrink-0 flex justify-end items-center rounded-b-xl">
        <button 
          @click="confirmUpgrade" 
          class="btn-pms-primary"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
          <span>Lưu</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { upgradeRoom } from '@/services/booking-service'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  targetRooms: Array,
  roomClasses: Array,
  roomForms: { type: Array, default: () => [] },
  roomRateCodes: Array
})

const emit = defineEmits(['update:show', 'upgraded'])

const authStore = useAuthStore()
const uiStore = useUiStore()

const topbarThemeBg = computed(() => {
  return authStore.settings?.topbar_color || 'var(--pms-custom-theme, #006bdb)'
})

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

const upgradeTargetClassId = ref(null)
const upgradeTargetFormId = ref(null)
const upgradeTargetRateCode = ref('')
const upgradeTargetPrice = ref(0)
const upgradeChangePrice = ref(false)

function getNormalizedCategory(rc, forms) {
  if (!rc || !rc.name) return ''
  let categoryName = rc.name
  if (forms && forms.length > 0) {
    forms.forEach(rf => {
      if (!rf || !rf.name) return
      const regex = new RegExp('\\b' + rf.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'gi')
      categoryName = categoryName.replace(regex, '')
    })
  }
  return categoryName.replace(/\s+/g, ' ').trim().toLowerCase()
}

const availableForms = computed(() => {
  const currentClass = props.roomClasses?.find(c => c.id === upgradeTargetClassId.value)
  if (!currentClass) return props.roomForms || []
  const category = getNormalizedCategory(currentClass, props.roomForms)
  const siblingClasses = props.roomClasses.filter(c => getNormalizedCategory(c, props.roomForms) === category)
  const formIds = siblingClasses.map(c => c.room_form_id).filter(id => id !== undefined && id !== null)
  const list = (props.roomForms || []).filter(f => formIds.includes(f.id))
  return list.length > 0 ? list : (props.roomForms || [])
})

function getRateCodePrice(rateCodeMa, classId) {
  if (!rateCodeMa || !classId) return null
  const matchedRc = props.roomRateCodes?.find(rc => rc.Ma === rateCodeMa || rc.code === rateCodeMa)
  if (!matchedRc) return null

  // 1. Tra cứu theo ratePlans & Period theo roomClassId
  if (matchedRc.ratePlans && matchedRc.ratePlans.length > 0) {
    for (const plan of matchedRc.ratePlans) {
      if (plan.Period) {
        let periodObj = plan.Period
        if (typeof periodObj === 'string') {
          try { periodObj = JSON.parse(periodObj) } catch (e) { periodObj = {} }
        }
        if (periodObj[classId]) {
          const price = Number(periodObj[classId])
          if (!isNaN(price) && price > 0) return price
        }
      }
    }
  }

  // 2. Tra cứu Value / Gia / price
  const valPrice = Number(matchedRc.Value || matchedRc.Gia || matchedRc.price || 0)
  if (!isNaN(valPrice) && valPrice > 0) return valPrice

  return null
}

function updateUpgradePrice() {
  const classId = Number(upgradeTargetClassId.value)
  const matchedClass = props.roomClasses?.find(c => c.id === classId)

  if (upgradeChangePrice.value && upgradeTargetRateCode.value) {
    const rcPrice = getRateCodePrice(upgradeTargetRateCode.value, classId)
    if (rcPrice !== null) {
      upgradeTargetPrice.value = rcPrice
      return
    }
  }

  if (matchedClass) {
    const stdPrice = matchedClass.room_price ?? matchedClass.price ?? matchedClass.standard_rate ?? 0
    upgradeTargetPrice.value = Number(stdPrice)
  }
}

function handleClassChange() {
  const matchedClass = props.roomClasses?.find(c => c.id === Number(upgradeTargetClassId.value))
  if (matchedClass) {
    upgradeTargetFormId.value = matchedClass.room_form_id || upgradeTargetFormId.value
    updateUpgradePrice()
  }
}

function handleFormChange() {
  const currentClass = props.roomClasses?.find(c => c.id === Number(upgradeTargetClassId.value))
  if (!currentClass) return

  const category = getNormalizedCategory(currentClass, props.roomForms)
  const targetClass = props.roomClasses?.find(c => {
    return getNormalizedCategory(c, props.roomForms) === category && c.room_form_id === Number(upgradeTargetFormId.value)
  })

  if (targetClass) {
    upgradeTargetClassId.value = targetClass.id
    updateUpgradePrice()
  }
}

function handleRateCodeChange() {
  if (!upgradeChangePrice.value) return
  updateUpgradePrice()
}

function clearRateCode() {
  upgradeTargetRateCode.value = ''
  updateUpgradePrice()
}

function handleToggleChangePrice() {
  if (!upgradeChangePrice.value) {
    upgradeTargetRateCode.value = ''
  }
  updateUpgradePrice()
}

function handleKeydown(e) {
  if (e.key === 'Escape' && props.show) {
    close()
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    window.addEventListener('keydown', handleKeydown)
    modalPos.value = { x: 0, y: 0 }
    const targetRoom = props.targetRooms?.[0]
    if (targetRoom) {
      const matchedClass = props.roomClasses?.find(c => 
        c.id === targetRoom.roomClassId || c.name === targetRoom.type || c.code === targetRoom.shape
      )
      if (matchedClass) {
        upgradeTargetClassId.value = matchedClass.id
        upgradeTargetFormId.value = matchedClass.room_form_id || null
        upgradeTargetPrice.value = Number(matchedClass.room_price ?? matchedClass.price ?? targetRoom.price ?? 0)
      } else {
        upgradeTargetClassId.value = props.roomClasses?.[0]?.id || null
        upgradeTargetFormId.value = props.roomClasses?.[0]?.room_form_id || null
        upgradeTargetPrice.value = Number(props.roomClasses?.[0]?.room_price ?? props.roomClasses?.[0]?.price ?? 0)
      }
    } else {
      upgradeTargetClassId.value = props.roomClasses?.[0]?.id || null
      upgradeTargetFormId.value = props.roomClasses?.[0]?.room_form_id || null
      upgradeTargetPrice.value = Number(props.roomClasses?.[0]?.room_price ?? props.roomClasses?.[0]?.price ?? 0)
    }
    upgradeTargetRateCode.value = ''
    upgradeChangePrice.value = false
  } else {
    window.removeEventListener('keydown', handleKeydown)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  if (isDraggingModal.value) stopDragModal()
})

function close() {
  emit('update:show', false)
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  let str = String(val).replace(/[^\d.-]/g, '');
  if (!str) return '';
  let parts = str.split('.');
  parts[0] = Number(parts[0]).toLocaleString('en-US');
  return parts.join('.');
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0;
  const cleanStr = String(val).replace(/,/g, '');
  return Number(cleanStr) || 0;
}

async function confirmUpgrade() {
  if (!props.bookingId) return

  const classId = upgradeTargetClassId.value
  if (!classId) {
    uiStore.showToast('Vui lòng chọn hạng phòng muốn nâng lên!', 'warning')
    return
  }

  const targetList = props.targetRooms || []
  if (targetList.length === 0) {
    uiStore.showToast('Không có phòng nào để nâng hạng!', 'info')
    return
  }

  uiStore.confirm({
    title: 'Xác nhận nâng hạng phòng',
    message: `Bạn có chắc chắn muốn nâng hạng cho ${targetList.length} phòng đã chọn?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy'
  }).then(async (confirmed) => {
    if (!confirmed) return
    close()
    uiStore.showToast('Đang tiến hành nâng hạng phòng...', 'info')
    let successCount = 0
    let failCount = 0
    let failMessages = []

    try {
      await Promise.all(targetList.map(async (r) => {
        if (!r.bookingRoomId) return
        try {
          const data = { room_class_id: classId }
          if (upgradeChangePrice.value) {
            data.rate = Number(upgradeTargetPrice.value)
            if (upgradeTargetRateCode.value) {
              data.rate_code = upgradeTargetRateCode.value
            }
          }
          const res = await upgradeRoom(props.bookingId, r.bookingRoomId, data)
          if (res.data?.success) {
            successCount++
            r.roomNumber = ''
            r.roomClassId = classId
            const matchedClass = props.roomClasses.find(c => c.id === classId)
            if (matchedClass) {
              r.type = matchedClass.name
              r.shape = matchedClass.room_form_name || matchedClass.code
            }
            if (upgradeChangePrice.value) {
              r.price = Number(upgradeTargetPrice.value)
              if (upgradeTargetRateCode.value) {
                r.rateCode = upgradeTargetRateCode.value
              }
            } else if (matchedClass) {
              r.price = Number(matchedClass.room_price ?? matchedClass.price ?? r.price)
            }
          } else {
            failCount++
            failMessages.push(res.data?.message || `Phòng ${r.roomNumber || r.id} thất bại.`)
          }
        } catch (err) {
          console.error(err)
          failCount++
          failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber || r.id} thất bại.`)
        }
      }))

      if (successCount > 0) {
        emit('upgraded', { successCount, failCount, failMessages })
      } else {
        uiStore.showToast(`Nâng hạng thất bại: ${failMessages.join(', ')}`, 'error')
      }
    } catch(err) {
      console.error(err)
      uiStore.showToast('Có lỗi xảy ra khi nâng hạng phòng!', 'error')
    }
  })
}
</script>

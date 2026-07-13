<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-black/50 z-[99999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
  >
    <div 
      class="bg-white rounded-xl shadow-2xl w-full max-w-[600px] overflow-hidden border border-slate-300 flex flex-col max-h-[90vh]"
    >
      <!-- HEADER -->
      <div class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-3 shrink-0 select-none">
        <div class="flex items-center space-x-2 font-black text-xs uppercase tracking-wider">
          <i class="fa-solid fa-arrow-up text-sky-400"></i>
          <span>Nâng hạng phòng</span>
        </div>
        <button class="hover:text-white bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none bg-transparent" @click="close">
          <i class="fa-solid fa-xmark text-red-400"></i>
        </button>
      </div>

      <!-- BODY -->
      <div class="p-5 space-y-4 flex-1 overflow-y-auto">
        <!-- PHÒNG ĐÃ CHỌN -->
        <div class="text-[10px] font-black text-slate-400 tracking-wider uppercase">PHÒNG ĐÃ CHỌN ({{ targetRooms.length }})</div>
        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold h-8">
                <th class="p-2.5">Phòng</th>
                <th class="p-2.5">Hạng hiện tại</th>
                <th class="p-2.5">Khách</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in targetRooms" :key="r.id" class="border-b border-slate-100 hover:bg-slate-50/30 h-9 font-semibold text-slate-700">
                <td class="p-2.5 font-bold text-sky-600">{{ r.roomNumber || 'Chưa gán' }}</td>
                <td class="p-2.5">{{ r.type || r.shape || '-' }}</td>
                <td class="p-2.5 text-slate-500">{{ r.guestName || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- CHỌN HẠNG MỚI (GRID LAYOUT) -->
        <div class="grid grid-cols-2 gap-4 text-xs font-bold text-slate-700">
          <!-- Loại phòng -->
          <div>
            <label class="block text-slate-600 mb-1.5 font-bold">Loại phòng</label>
            <select 
              v-model="upgradeTargetClassId" 
              class="w-full border border-yellow-300 bg-yellow-50/50 rounded-lg h-9 px-3 text-xs font-semibold text-slate-850 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:border-yellow-400"
            >
              <option :value="null">Chọn loại phòng</option>
              <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.name }}</option>
            </select>
          </div>

          <!-- Dạng phòng -->
          <div>
            <label class="block text-slate-600 mb-1.5 font-bold">Dạng phòng</label>
            <select 
              v-model="upgradeTargetClassId" 
              class="w-full border border-yellow-300 bg-yellow-50/50 rounded-lg h-9 px-3 text-xs font-semibold text-slate-850 focus:outline-none focus:ring-1 focus:ring-yellow-400 focus:border-yellow-400"
            >
              <option :value="null">Chọn dạng phòng</option>
              <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
            </select>
          </div>

          <!-- Mã giá phòng -->
          <div>
            <label class="block text-slate-600 mb-1.5 font-bold">Mã giá phòng</label>
            <select 
              v-model="upgradeTargetRateCode" 
              :disabled="!upgradeChangePrice"
              class="w-full border rounded-lg h-9 px-3 text-xs font-semibold focus:outline-none transition-colors border-slate-200"
              :class="!upgradeChangePrice ? 'bg-[#f1f1f1] text-[#a3a3a3] cursor-not-allowed' : 'bg-white text-slate-800 focus:ring-1 focus:ring-sky-500'"
            >
              <option value="">Select Value</option>
              <option v-for="rc in roomRateCodes" :key="rc.id" :value="rc.Ma">{{ rc.Ma }}</option>
            </select>
          </div>

          <!-- Giá phòng -->
          <div>
            <label class="block text-slate-600 mb-1.5 font-bold">Giá phòng</label>
            <input 
              type="number" 
              v-model="upgradeTargetPrice"
              :disabled="!upgradeChangePrice"
              class="w-full border rounded-lg h-9 px-3 text-xs font-semibold focus:outline-none transition-colors border-slate-200"
              :class="!upgradeChangePrice ? 'bg-[#f1f1f1] text-[#a3a3a3] cursor-not-allowed' : 'bg-white text-slate-800 focus:ring-1 focus:ring-sky-500'"
            />
          </div>

          <!-- Checkbox Thay đổi giá -->
          <div class="col-span-2 flex items-center gap-2 py-1 select-none">
            <input 
              id="upgradeChangePrice"
              type="checkbox" 
              v-model="upgradeChangePrice"
              class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5 cursor-pointer"
            />
            <label for="upgradeChangePrice" class="text-xs font-extrabold text-slate-700 cursor-pointer">Thay đổi giá</label>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="bg-slate-50 border-t border-slate-100 px-4 py-3 shrink-0 flex justify-end items-center space-x-2">
        <button 
          @click="close" 
          class="bg-[#72c0e5] hover:bg-[#5bb2dc] text-white border-none rounded-lg font-bold text-xs px-4 py-2 cursor-pointer shadow-sm flex items-center space-x-1.5 transition"
        >
          <i class="fa-solid fa-circle-xmark"></i>
          <span>Đóng</span>
        </button>
        <button 
          @click="confirmUpgrade" 
          class="bg-[#72c0e5] hover:bg-[#5bb2dc] text-white border-none rounded-lg font-bold text-xs px-4 py-2 cursor-pointer shadow-sm flex items-center space-x-1.5 transition"
        >
          <i class="fa-solid fa-floppy-disk"></i>
          <span>Lưu</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { upgradeRoom } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  show: Boolean,
  bookingId: Number,
  targetRooms: Array,
  roomClasses: Array,
  roomRateCodes: Array
})

const emit = defineEmits(['update:show', 'upgraded'])

const uiStore = useUiStore()

const upgradeTargetClassId = ref(null)
const upgradeTargetRateCode = ref('')
const upgradeTargetPrice = ref(0)
const upgradeChangePrice = ref(false)

watch(() => props.show, (newVal) => {
  if (newVal) {
    const targetRoom = props.targetRooms?.[0]
    if (targetRoom) {
      const matchedClass = props.roomClasses.find(c => c.name === targetRoom.type || c.code === targetRoom.shape)
      upgradeTargetClassId.value = matchedClass ? matchedClass.id : null
      upgradeTargetRateCode.value = ''
      upgradeTargetPrice.value = targetRoom.price || 0
      upgradeChangePrice.value = false
    } else {
      upgradeTargetClassId.value = null
      upgradeTargetRateCode.value = ''
      upgradeTargetPrice.value = 0
      upgradeChangePrice.value = false
    }
  }
})

function close() {
  emit('update:show', false)
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
        }
        const res = await upgradeRoom(props.bookingId, r.bookingRoomId, data)
        if (res.data?.success) {
          successCount++
          r.roomNumber = ''
          r.roomClassId = classId
          const matchedClass = props.roomClasses.find(c => c.id === classId)
          if (matchedClass) {
            r.type = matchedClass.name
            r.shape = matchedClass.code
          }
          if (upgradeChangePrice.value) {
            r.price = Number(upgradeTargetPrice.value)
            if (upgradeTargetRateCode.value) {
              r.rateCode = upgradeTargetRateCode.value
            }
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
}
</script>

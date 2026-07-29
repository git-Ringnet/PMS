<template>
  <div class="h-full flex flex-col bg-[#dde3ea] p-3 text-xs font-sans">
    <div class="modal-card flex flex-col h-full bg-white rounded-lg shadow-lg overflow-hidden border border-slate-200 relative">
      <LoadingOverlay :show="loadingProducts || loadingRooms" />
      
      <!-- INFO STRIP -->
      <div class="info-strip flex items-center gap-3 p-2.5 bg-slate-100 border-b border-slate-200 shrink-0 flex-wrap">
        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">&nbsp;</label>
          <div class="info-room flex items-center gap-2 bg-white border border-slate-300 rounded px-3 h-8 min-w-[240px] text-xs font-semibold shadow-xs">
            <select v-model="form.roomId" disabled class="border-none bg-transparent focus:outline-none w-full font-bold text-slate-800 cursor-not-allowed disabled:opacity-100">
              <option value="">-- Chọn phòng --</option>
              <option v-for="room in bookingRooms" :key="room.id" :value="room.id">
                {{ room.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">Khách</label>
          <select v-model="form.guestId" :disabled="roomGuestsForPosting.length === 0 || Boolean(props.initialGuestId)" class="h-8 min-w-[180px] px-2.5 border border-slate-300 rounded text-xs text-slate-800 bg-white focus:outline-none focus:border-[#1a6b8a] disabled:bg-slate-100">
            <option value="">-- Chọn khách --</option>
            <option v-for="guest in roomGuestsForPosting" :key="guest.id" :value="guest.id">{{ guest.name }}</option>
          </select>
        </div>

        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">Ngày</label>
          <input type="date" v-model="form.date" class="h-8 px-2.5 border border-slate-300 rounded text-xs text-slate-800 bg-white focus:outline-none focus:border-[#1a6b8a] w-[135px] cursor-pointer" />
        </div>

        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">Tăng giá</label>
          <div class="pct flex items-center" :class="{ 'opacity-40 pointer-events-none': form.isFree }">
            <input 
              type="number" 
              v-model.number="form.surcharge" 
              min="0" 
              placeholder="0" 
              @input="onGlobalChange('surcharge')"
              class="h-8 w-[72px] px-2 text-right border border-r-0 border-slate-300 rounded-l text-xs text-slate-800 bg-white focus:outline-none"
            />
            <span class="h-8 px-2 border border-l-0 border-slate-300 rounded-r bg-slate-100 text-xs text-slate-400 flex items-center">%</span>
          </div>
        </div>

        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">Chiết khấu</label>
          <div class="pct flex items-center" :class="{ 'opacity-40 pointer-events-none': form.isFree }">
            <input 
              type="number" 
              v-model.number="form.discount" 
              min="0" 
              placeholder="0" 
              @input="onGlobalChange('discount')"
              class="h-8 w-[72px] px-2 text-right border border-r-0 border-slate-300 rounded-l text-xs text-slate-800 bg-white focus:outline-none"
            />
            <span class="h-8 px-2 border border-l-0 border-slate-300 rounded-r bg-slate-100 text-xs text-slate-400 flex items-center">%</span>
          </div>
        </div>

        <div class="info-field flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">&nbsp;</label>
          <label class="mienPhi-wrap flex items-center gap-2 h-8 cursor-pointer select-none text-xs font-semibold text-slate-700 hover:text-slate-900" :class="{ 'text-emerald-600 font-bold': form.isFree }">
            <input type="checkbox" v-model="form.isFree" @change="onMienPhiChange" class="w-4 h-4 accent-emerald-600 cursor-pointer" />
            <span>Miễn phí</span>
          </label>
        </div>

        <div class="info-field flex-1 flex flex-col gap-1">
          <label class="text-[10.5px] font-semibold text-slate-700 tracking-wider">Ghi chú</label>
          <textarea v-model="form.note" placeholder="Ghi chú..." class="h-8 px-2.5 py-1 border border-slate-300 rounded text-xs text-slate-800 bg-white focus:outline-none focus:border-[#1a6b8a] resize-none w-full leading-tight"></textarea>
        </div>
      </div>

      <!-- MAIN BODY GRID (260px Left | 1fr Right) -->
      <div class="modal-body flex-1 grid grid-cols-1 md:grid-cols-[260px_1fr] overflow-hidden">
        
        <!-- LEFT: PRODUCTS LIST -->
        <div class="col-products border-r border-slate-200 flex flex-col overflow-hidden bg-white">
          <!-- Tab Bar -->
          <div class="tab-bar flex border-b-2 border-slate-200 bg-slate-100 shrink-0">
            <button 
              v-for="tKey in ['minibar', 'giatui', 'dengbu']" 
              :key="tKey"
              @click="switchTab(tKey)"
              class="tab flex-1 py-2 px-1 text-xs font-medium text-slate-400 text-center border-b-2 transition-all cursor-pointer bg-none border-transparent -mb-[2px]"
              :class="[currentTab === tKey ? 'text-slate-800 font-bold border-b-[#1a6b8a]' : 'hover:text-slate-600']"
            >
              {{ tabLabels[tKey] }}
            </button>
          </div>

          <!-- Product Search -->
          <div class="product-search p-2 border-b border-slate-200 shrink-0">
            <div class="search-wrap relative">
              <span class="search-icon absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none">🔍</span>
              <input 
                type="text" 
                v-model="productSearchQuery" 
                placeholder="Tìm sản phẩm..." 
                class="w-full h-8 pl-8 pr-2.5 text-xs border border-slate-300 rounded bg-slate-50 focus:bg-white focus:border-[#1a6b8a] outline-none transition-colors" 
              />
            </div>
          </div>

          <!-- Product List -->
          <div class="product-list flex-1 overflow-y-auto">
            <template v-if="filteredProductSubgroups.length > 0">
              <div v-for="sub in filteredProductSubgroups" :key="sub.name">
                <div class="subgroup-header px-3.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white bg-[#1a6b8a] border-b border-slate-200 flex items-center gap-1.5 opacity-90">
                  {{ sub.name }}
                </div>
                <template v-if="sub.items && sub.items.length > 0">
                  <div 
                    v-for="p in sub.items" 
                    :key="p.id"
                    @click="addToCart(p)"
                    class="product-item flex items-center gap-2.5 px-3.5 py-2 cursor-pointer border-b border-slate-100 hover:bg-blue-50 transition-colors select-none"
                  >
                    <div class="product-thumb w-8.5 h-8.5 rounded-md bg-slate-50 border border-slate-200 flex items-center justify-center text-base shrink-0 overflow-hidden">
                      <img v-if="p.image" :src="p.image" class="w-full h-full object-cover" />
                      <span v-else>{{ p.icon || '📦' }}</span>
                    </div>
                    <div class="product-info flex-1 min-w-0">
                      <div class="product-name text-xs text-slate-800 font-normal leading-tight truncate">{{ p.name }}</div>
                      <div class="product-price text-[11px] text-[#1a6b8a] font-semibold mt-0.5">{{ formatCurrency(p.price) }} đ</div>
                    </div>
                    <button 
                      @click.stop="addToCart(p)"
                      class="product-add w-6 h-6 rounded-full bg-[#1a6b8a] hover:bg-[#155a76] text-white border-none text-base cursor-pointer flex items-center justify-center shrink-0 transition-colors leading-none"
                      title="Thêm"
                    >
                      +
                    </button>
                  </div>
                </template>
                <div v-else class="px-3.5 py-2 text-[11px] text-slate-400 italic border-b border-slate-100 bg-slate-50/50">
                  Chưa có sản phẩm trong nhóm này
                </div>
              </div>
            </template>
            <div v-else class="tbl-empty flex flex-col items-center justify-center p-8 text-slate-400 text-xs gap-2 text-center">
              <span class="text-3xl opacity-30">🔍</span>
              <span>Không tìm thấy sản phẩm</span>
            </div>
          </div>
        </div>

        <!-- RIGHT: SELECTED SERVICES TABLE -->
        <div class="col-right flex flex-col overflow-hidden bg-white">
          <!-- Table Header -->
          <div class="tbl-head grid grid-cols-[36px_1fr_88px_52px_80px_80px_88px_36px] bg-slate-100 border-b-2 border-slate-200 px-3 h-10 items-center gap-1.5 shrink-0">
            <div class="th font-bold text-[11px] text-slate-700 text-center">STT</div>
            <div class="th font-bold text-[11px] text-slate-700 text-center">Sản phẩm</div>
            <div class="th font-bold text-[11px] text-slate-700 text-center">Giá</div>
            <div class="th font-bold text-[11px] text-slate-700 text-center">SL</div>
            
            <!-- Toggle Discount / Surcharge Mode -->
            <div class="th-toggle-wrap relative flex flex-col items-center">
              <div 
                @click="toggleDiscountMode"
                class="th-toggle flex flex-col items-center justify-center cursor-pointer select-none p-1 rounded hover:bg-slate-200 transition-colors"
                :class="{ 'text-amber-600': discountMode === 'pt', 'pointer-events-none opacity-40': form.isFree }"
              >
                <span class="th-main text-[11px] font-bold" :class="discountMode === 'pt' ? 'text-amber-600' : 'text-slate-700'">
                  {{ discountMode === 'pt' ? '% Phụ thu' : '% Giảm' }}
                </span>
                <span class="th-hint text-[9.5px] text-slate-400 whitespace-nowrap" :class="{ 'text-amber-600/70': discountMode === 'pt' }">
                  ↔ click: {{ discountMode === 'pt' ? 'Giảm giá' : 'Phụ thu' }}
                </span>
              </div>
            </div>

            <div class="th font-bold text-[11px] text-slate-700 text-center">
              {{ discountMode === 'pt' ? 'Tiền phụ thu' : 'Tiền giảm' }}
            </div>
            <div class="th font-bold text-[11px] text-slate-700 text-center">Thành tiền</div>
            <div class="th font-bold text-[11px] text-slate-700 text-center"></div>
          </div>

          <!-- Table Body -->
          <div class="tbl-body flex-1 overflow-y-auto">
            <template v-if="cart.length > 0">
              <template v-for="grpKey in ['minibar', 'giatui', 'dengbu']" :key="grpKey">
                <template v-if="groupedCart[grpKey] && groupedCart[grpKey].length > 0">
                  <!-- Group Header -->
                  <div class="group-header px-2 py-1 text-[10.5px] font-bold uppercase tracking-wider text-slate-400 bg-slate-100 border-b border-slate-200 flex items-center gap-1.5">
                    <span class="group-dot w-1.75 h-1.75 rounded-full" :style="{ background: GROUP_COLORS[grpKey] }"></span>
                    <span>{{ GROUP_LABELS[grpKey] }}</span>
                  </div>

                  <!-- Group Rows -->
                  <div 
                    v-for="(item, idx) in groupedCart[grpKey]" 
                    :key="item.product.id"
                    class="tbl-row grid grid-cols-[36px_1fr_88px_52px_80px_80px_88px_36px] px-3 gap-1.5 items-center min-h-[44px] border-b border-slate-200 hover:bg-slate-50 transition-colors text-xs"
                  >
                    <div class="cell-stt text-center text-slate-400">{{ getItemIndex(grpKey, idx) }}</div>
                    <div class="cell-name text-slate-800 font-normal truncate" :title="item.product.name">{{ item.product.name }}</div>
                    <div class="cell-price text-center text-slate-800">{{ formatCurrency(item.product.price) }}</div>
                    
                    <!-- Qty Input -->
                    <div>
                      <input 
                        type="number" 
                        v-model.number="item.qty" 
                        min="1" 
                        @input="refreshCart"
                        class="cell-input w-full h-7 border border-slate-300 rounded text-center text-xs text-slate-800 focus:border-[#1a6b8a] outline-none" 
                      />
                    </div>

                    <!-- Pct Input -->
                    <div class="cell-pct relative">
                      <input 
                        type="number" 
                        v-model.number="item.discPct" 
                        min="0" 
                        max="100" 
                        :disabled="form.isFree"
                        :placeholder="globalPct ? `${globalPct}` : '0'"
                        @input="refreshCart"
                        class="cell-input w-full h-7 border border-slate-300 rounded text-center text-xs text-slate-800 focus:border-[#1a6b8a] outline-none pr-5 disabled:bg-slate-100 disabled:opacity-50" 
                      />
                      <span class="pct-unit absolute right-1.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 pointer-events-none">%</span>
                    </div>

                    <!-- Discount / Surcharge Value -->
                    <div 
                      class="cell-discount-val text-center text-slate-400 text-xs"
                      :class="{ 
                        'text-rose-600 font-semibold': discountMode === 'gg' && calcRow(item).discAmt > 0,
                        'text-amber-600 font-semibold': discountMode === 'pt' && calcRow(item).discAmt > 0
                      }"
                    >
                      {{ calcRow(item).discAmt > 0 ? formatCurrency(calcRow(item).discAmt) : '—' }}
                    </div>

                    <!-- Line Total -->
                    <div class="cell-total text-center font-semibold text-slate-800 text-xs">
                      {{ formatCurrency(calcRow(item).net) }}
                    </div>

                    <!-- Delete Button -->
                    <div class="cell-del flex justify-center">
                      <button 
                        @click="removeFromCart(item.product.id)"
                        class="w-7 h-7 rounded text-rose-300 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors cursor-pointer border-none bg-transparent"
                        title="Xóa"
                      >
                        🗑
                      </button>
                    </div>
                  </div>
                </template>
              </template>

              <!-- Total Sum Row -->
              <div class="tbl-total-row flex justify-end items-center gap-2 p-2.5 px-3 border-t-2 border-slate-300 bg-slate-100">
                <span class="tbl-total-lbl text-xs font-semibold text-slate-600">Thành tiền</span>
                <span class="tbl-total-val text-sm font-bold text-[#1a6b8a] min-w-[90px] text-right">{{ formatCurrency(grandTotal) }}</span>
              </div>
            </template>

            <!-- Empty Cart State -->
            <div v-else class="tbl-empty flex flex-col items-center justify-center min-h-[220px] h-full text-slate-400 text-xs p-10 text-center gap-2">
              <div class="text-4xl opacity-30 leading-none mb-1">🛒</div>
              <div class="text-xs text-slate-500 font-medium leading-normal">Chọn sản phẩm từ danh sách bên trái để thêm</div>
            </div>
          </div>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="modal-footer border-t-2 border-slate-200 bg-slate-100 flex items-center justify-end p-2.5 shrink-0 gap-2">
        <div class="footer-actions flex gap-2">
          <button @click="undoCart" class="btn btn-cancel h-9 px-4 rounded border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold cursor-pointer transition-colors flex items-center gap-1.5">
            <span>↩ Undo</span>
          </button>
          <button @click="sendToRoom" :disabled="isSending" class="btn btn-save h-9 px-5 rounded bg-[#1a6b8a] hover:bg-[#155a76] text-white text-xs font-semibold cursor-pointer transition-colors border-none flex items-center gap-1.5 shadow-xs disabled:opacity-60">
            <span v-if="isSending" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>💾 Gửi về phòng</span>
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'
import { fetchBookings, fetchSystemDate } from '@/services/booking-service'
import http from '@/services/http'
// Import LoadingOverlay component của hệ thống
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const props = defineProps({
  initialRoomId: {
    type: [String, Number],
    default: ''
  },
  initialGuestId: {
    type: [String, Number],
    default: ''
  },
  isModal: {
    type: Boolean,
    default: false
  },
  department: {
    type: String,
    default: 'HK'
  }
})

const emit = defineEmits(['close', 'success'])

const uiStore = useUiStore()

const bookingRooms = ref([])
const loadingRooms = ref(true)

const loadSystemDate = async () => {
  try {
    const res = await fetchSystemDate()
    const sysDate = res.data?.data?.system_date || res.data?.system_date
    if (sysDate) {
      form.value.date = sysDate
    }
  } catch (err) {
    console.error('Lỗi khi tải ngày hệ thống:', err)
  }
}

const loadBookingRooms = async () => {
  loadingRooms.value = true
  try {
    const res = await fetchBookings({ status: '0,1' })
    const list = res.data?.data || res.data || []
    const options = []

    list.forEach(b => {
      const code = b.booking_code || b.code || ''
      const mainGuestName = (
        (b.guest_name && b.guest_name.trim()) ||
        (b.booking_name && b.booking_name.trim()) ||
        (b.contact_name && b.contact_name.trim()) ||
        (b.customer?.full_name && b.customer.full_name.trim()) ||
        (b.customer?.name && b.customer.name.trim()) ||
        (b.booker?.full_name && b.booker.full_name.trim()) ||
        (b.booker?.name && b.booker.name.trim()) ||
        (b.company?.name && b.company.name.trim()) ||
        'Khách lẻ'
      )

      if (b.booking_rooms && b.booking_rooms.length > 0) {
        b.booking_rooms.forEach(r => {
          const roomNo = r.room_number || r.room || (r.room && r.room.room_number) || ''
          
          const roomGuests = (r.guests || []).map(g => ({
            id: g.guest_id || g.guest?.id || g.id,
            name: g.guest?.full_name || g.full_name || (g.first_name ? `${g.first_name} ${g.last_name || ''}`.trim() : ''),
            isPrimary: Boolean(g.is_primary)
          })).filter(g => g.id && g.name)
          let roomGuest = roomGuests.find(g => g.isPrimary)?.name || roomGuests[0]?.name || ''
          if (!roomGuest && r.guest_name && r.guest_name.trim()) {
            roomGuest = r.guest_name.trim()
          }
          if (!roomGuest) {
            roomGuest = mainGuestName
          }
          
          let labelParts = []
          if (code) labelParts.push(code)
          if (roomNo) labelParts.push(roomNo)
          if (roomGuest) labelParts.push(roomGuest)

          options.push({
            id: r.id || b.id,
            roomId: r.room_id || r.id,
            bookingId: b.id,
            code: code,
            roomNumber: roomNo,
            guestName: roomGuest,
            guests: roomGuests,
            name: labelParts.join(' - ')
          })
        })
      } else {
        let labelParts = []
        if (code) labelParts.push(code)
        if (b.room_number) labelParts.push(b.room_number)
        if (mainGuestName) labelParts.push(mainGuestName)

        options.push({
          id: b.id,
          bookingId: b.id,
          code: code,
          roomNumber: b.room_number || '',
          guestName: mainGuestName,
          name: labelParts.join(' - ')
        })
      }
    })

    if (options.length > 0) {
      bookingRooms.value = options
      if (props.initialRoomId) {
        const matched = options.find(o => String(o.id) === String(props.initialRoomId) || String(o.roomId) === String(props.initialRoomId) || String(o.bookingId) === String(props.initialRoomId))
        if (matched) {
          form.value.roomId = matched.id
          if (props.initialGuestId && matched.guests.some(guest => String(guest.id) === String(props.initialGuestId))) {
            form.value.guestId = props.initialGuestId
          }
        }
      } else if (form.value.roomId && !options.some(o => o.id === form.value.roomId)) {
        form.value.roomId = ''
      }
    } else {
      bookingRooms.value = []
    }
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu booking cho dropdown:', err)
    bookingRooms.value = []
  } finally {
    loadingRooms.value = false
  }
}

watch(() => props.initialRoomId, (newVal) => {
  if (newVal && bookingRooms.value.length > 0) {
    const matched = bookingRooms.value.find(o => String(o.id) === String(newVal) || String(o.roomId) === String(newVal) || String(o.bookingId) === String(newVal))
    if (matched) {
      form.value.roomId = matched.id
      if (props.initialGuestId && matched.guests.some(guest => String(guest.id) === String(props.initialGuestId))) {
        form.value.guestId = props.initialGuestId
      }
    }
  }
})

watch(() => props.initialGuestId, (newVal) => {
  if (newVal && roomGuestsForPosting.value.some(guest => String(guest.id) === String(newVal))) {
    form.value.guestId = newVal
  }
})

const dbProductsData = ref({
  minibar: {},
  giatui: {},
  dengbu: {}
})

const loadingProducts = ref(true)

const loadDbProducts = async () => {
  loadingProducts.value = true
  try {
    const [resCats, resProds] = await Promise.all([
      http.get('/product-categories'),
      http.get('/products')
    ])

    const categories = Array.isArray(resCats.data) ? resCats.data : (resCats.data?.data || [])
    const products = Array.isArray(resProds.data) ? resProds.data : (resProds.data?.data || [])

    const tabMap = {
      'Minibar': 'minibar',
      'Giặt ủi': 'giatui',
      'Hàng đền bù': 'dengbu',
      'Amenity': 'minibar',
      'minibar': 'minibar',
      'giatui': 'giatui',
      'dengbu': 'dengbu'
    }

    const newDbData = {
      minibar: {},
      giatui: {},
      dengbu: {}
    }

    categories.forEach(cat => {
      const tabKey = tabMap[cat.outlet] || tabMap[cat.name] || 'minibar'
      if (!newDbData[tabKey]) {
        newDbData[tabKey] = {}
      }
      if (!newDbData[tabKey][cat.name]) {
        newDbData[tabKey][cat.name] = []
      }

      const catProducts = (cat.products && cat.products.length > 0)
        ? cat.products
        : products.filter(p => p.product_category_id === cat.id)

      catProducts.forEach(p => {
        if (p.is_active === 0 || p.is_active === false) return
        let basePrice = Number(p.price) || 0
        let svcPercent = Number(p.service_charge_percent) || 0
        let taxPercent = Number(p.tax_percent) || 0
        let spcTaxPercent = Number(p.special_tax_percent) || 0
        let finalPrice = basePrice + (basePrice * svcPercent / 100) + (basePrice * taxPercent / 100) + (basePrice * spcTaxPercent / 100)

        newDbData[tabKey][cat.name].push({
          id: p.id,
          name: p.name,
          price: finalPrice || basePrice,
          image: p.image ? `http://localhost:8000/storage/${p.image}` : null,
          icon: p.image ? null : '📦',
          product_code: p.product_code
          ,product_category_id: p.product_category_id
          ,product_group_id: p.product_group_id
        })

        productGroup[p.id] = tabKey
      })
    })

    dbProductsData.value = newDbData
  } catch (err) {
    console.error('Lỗi khi tải danh mục/sản phẩm PMS từ CSDL:', err)
  } finally {
    loadingProducts.value = false
  }
}

onMounted(() => {
  loadSystemDate()
  loadBookingRooms()
  loadDbProducts()
})

const tabLabels = { minibar: 'Minibar', giatui: 'Giặt ủi', dengbu: 'Hàng đền bù' }
const GROUP_LABELS = { minibar: 'Minibar', giatui: 'Giặt ủi', dengbu: 'Hàng đền bù' }
const GROUP_COLORS = { minibar: '#2563eb', giatui: '#16a34a', dengbu: '#d97706' }

const form = ref({
  roomId: '',
  guestId: '',
  date: new Date().toISOString().split('T')[0],
  surcharge: 0,
  discount: 0,
  isFree: false,
  note: ''
})

const currentTab = ref('minibar')
const productSearchQuery = ref('')
const discountMode = ref('gg') // 'gg' = Giảm giá | 'pt' = Phụ thu
const globalPct = ref(0)
const cart = ref([])
const isSending = ref(false)

const productGroup = {}

const selectedPostingRoom = computed(() => bookingRooms.value.find(room => String(room.id) === String(form.value.roomId)))
const roomGuestsForPosting = computed(() => selectedPostingRoom.value?.guests || [])

watch(roomGuestsForPosting, (guests) => {
  if (!guests.some(guest => String(guest.id) === String(form.value.guestId))) {
    form.value.guestId = guests.find(guest => guest.isPrimary)?.id || guests[0]?.id || ''
  }
}, { immediate: true })

const switchTab = (tabKey) => {
  currentTab.value = tabKey
  productSearchQuery.value = ''
}

const filteredProductSubgroups = computed(() => {
  const currentDbTab = dbProductsData.value[currentTab.value] || {}
  const q = productSearchQuery.value.trim().toLowerCase()
  const result = []

  Object.entries(currentDbTab).forEach(([subName, items]) => {
    const matched = (items || []).filter(p => !q || p.name.toLowerCase().includes(q) || (p.product_code && p.product_code.toLowerCase().includes(q)))
    result.push({ name: subName, items: matched })
  })

  return result
})

const onGlobalChange = (source) => {
  if (source === 'surcharge' && (form.value.surcharge || 0) > 0) {
    form.value.discount = 0
  } else if (source === 'discount' && (form.value.discount || 0) > 0) {
    form.value.surcharge = 0
  }

  const surcharge = Number(form.value.surcharge) || 0
  const discount = Number(form.value.discount) || 0
  const prevMode = discountMode.value

  if (surcharge > 0) {
    discountMode.value = 'pt'
  } else {
    discountMode.value = 'gg'
  }

  if (prevMode !== discountMode.value) {
    cart.value.forEach(item => { item.discPct = 0 })
  }

  globalPct.value = discountMode.value === 'gg' ? discount : surcharge
}

const onMienPhiChange = () => {
  if (form.value.isFree) {
    form.value.surcharge = 0
    form.value.discount = 0
    globalPct.value = 0
    discountMode.value = 'gg'
    cart.value.forEach(item => { item.discPct = 0 })
  }
}

const toggleDiscountMode = () => {
  if (form.value.isFree) return
  discountMode.value = discountMode.value === 'gg' ? 'pt' : 'gg'
  form.value.surcharge = 0
  form.value.discount = 0
  globalPct.value = 0
  cart.value.forEach(item => { item.discPct = 0 })
}

const addToCart = (product) => {
  const existing = cart.value.find(c => c.product.id === product.id)
  if (existing) {
    existing.qty++
  } else {
    cart.value.push({ product, qty: 1, discPct: 0 })
  }
}

const removeFromCart = (id) => {
  cart.value = cart.value.filter(c => c.product.id !== id)
}

const undoCart = () => {
  cart.value = []
}

const calcRow = (item) => {
  const base = item.product.price * (item.qty || 1)
  if (form.value.isFree) {
    return { base, discAmt: base, net: 0 }
  }
  const itemPct = Number(item.discPct) || 0
  const totalPct = Math.min(100, Math.max(0, globalPct.value + itemPct))
  const discAmt = Math.round((base * totalPct) / 100)
  const net = discountMode.value === 'gg' ? base - discAmt : base + discAmt
  return { base, discAmt, net }
}

const groupedCart = computed(() => {
  const map = { minibar: [], giatui: [], dengbu: [] }
  cart.value.forEach(item => {
    const grp = productGroup[item.product.id] || 'minibar'
    if (!map[grp]) map[grp] = []
    map[grp].push(item)
  })
  return map
})

const getItemIndex = (grpKey, idx) => {
  let count = 0
  const keys = ['minibar', 'giatui', 'dengbu']
  for (const k of keys) {
    if (k === grpKey) {
      return count + idx + 1
    }
    count += (groupedCart.value[k] || []).length
  }
  return idx + 1
}

const grandTotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + calcRow(item).net, 0)
})

const refreshCart = () => {
  // Triggers re-computation
}

const sendToRoom = async () => {
  if (!form.value.roomId) {
    uiStore.showToast('Vui lòng chọn phòng trước khi gửi.', 'warning')
    return
  }
  if (cart.value.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất 1 sản phẩm/dịch vụ.', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận gửi bill',
    message: `Bạn có chắc chắn muốn gửi hóa đơn trị giá ${formatCurrency(grandTotal.value)} VNĐ về phòng không?`
  })
  if (!confirmed) return

  isSending.value = true
  try {
    const groupedBillsMap = {}
    cart.value.forEach(item => {
      const prod = item.product || item
      const groupKey = (productGroup[prod.id] || currentTab.value || 'minibar').toLowerCase()
      if (!groupedBillsMap[groupKey]) {
        groupedBillsMap[groupKey] = []
      }
      const unitPrice = Number(prod.price) || 0
      const rowCalc = calcRow(item)
      const qtyVal = Number(item.qty) || 1
      const itemTax = Number(prod.tax) || Number(prod.tax_amount) || 0
      const itemSvcCharge = Number(prod.service_charge) || Number(prod.service_charge_amount) || 0
      groupedBillsMap[groupKey].push({
        id: prod.id,
        code: prod.product_code || prod.code || prod.id,
        name: prod.name || 'Sản phẩm buồng phòng',
        qty: qtyVal,
        price: unitPrice,
        discPct: item.discPct || 0,
        net_price: qtyVal ? (rowCalc.net / qtyVal) : unitPrice,
        tax: itemTax,
        service_charge: itemSvcCharge,
        unit: prod.unit || prod.unit_name || prod.dvt || 'Cái'
        ,original_rate: unitPrice
        ,discount_pct: discountMode.value === 'gg' ? Math.min(100, Math.max(0, (Number(globalPct.value) || 0) + (Number(item.discPct) || 0))) : 0
        ,discount_amount: discountMode.value === 'gg' ? rowCalc.discAmt : 0
        ,increase_pct: discountMode.value === 'pt' ? Math.min(100, Math.max(0, (Number(globalPct.value) || 0) + (Number(item.discPct) || 0))) : 0
        ,increase_amount: discountMode.value === 'pt' ? rowCalc.discAmt : 0
        ,total_amount: rowCalc.net
        ,product_group_id: prod.product_category_id || prod.product_group_id || null
      })
    })

    const billsPayload = Object.entries(groupedBillsMap).map(([grp, items]) => ({
      group: grp,
      items: items
    }))

    const res = await http.post('/booking-room-services/post-housekeeping-bill', {
      booking_room_id: form.value.roomId,
      guest_id: form.value.guestId || null,
      department: props.department || 'HK',
      service_date: form.value.date,
      is_free: form.value.isFree,
      note: form.value.note,
      bills: billsPayload
    })

    if (res.data?.success) {
      uiStore.showToast('Đã gửi hóa đơn dịch vụ về phòng thành công!', 'success')
      emit('success', {
        roomId: form.value.roomId,
        totalAmount: grandTotal.value,
        data: res.data.data
      })
      cart.value = []
      form.value.note = ''
      if (props.isModal) {
        emit('close')
      }
    } else {
      uiStore.showToast(res.data?.message || 'Có lỗi xảy ra khi lưu bill.', 'error')
    }
  } catch (err) {
    console.error('Lỗi khi post bill:', err)
    uiStore.showToast(err.response?.data?.message || 'Lỗi hệ thống khi gửi hóa đơn về phòng.', 'error')
  } finally {
    isSending.value = false
  }
}

const formatCurrency = (n) => {
  return Math.round(n || 0).toLocaleString('en-US')
}
</script>

<style scoped>
/* Scoped custom styling mirroring original design */
::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

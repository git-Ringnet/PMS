<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import QrcodeVue from 'qrcode.vue'

const uiStore = useUiStore()
const loading = ref(false)

const hotelForm = reactive({
  first_name: '',
  hotel_name: '',
  hotel_name1: '',
  address: '',
  address1: '',
  phone: '',
  fax: '',
  email: '',
  website: '',
  account: '',
  bank_code: '',
  bank: '',
  tax_code: '',
  account_name: '',
  invoice_address: '',
  breakfast_adult_rate: 0,
  breakfast_child_rate: 0,
  extra_bed_rate: 0,
  room_number: 0,
  division: '',
  currency: 'VND',
  prefix_booking_id: '',
  channel_manager: '',
  facebook: '',
  hotel_link: '',
  serial: '',
  invoice_number: '',
  invoice_number_length: null,
  form_no: '',
  logo: '',
  pos_serial: '',
  pos_invoice_number: '',
  pos_invoice_number_length: null,
  pos_invoice_form_no: '',
  pos_invoice_symbol: '',
  logo_url: '',
  qr_code_url: ''
})

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  return isDev ? `${backendUrl}/${path}` : `/${path}`
}

const logoInput = ref(null)

const onLogoSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('logo', file)

  loading.value = true
  try {
    const res = await http.post('/hotel-settings/logo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    if (res.data && res.data.data) {
      hotelForm.logo_url = res.data.data.logo_url
      uiStore.showToast('Tải lên logo thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi tải lên logo:', err)
    uiStore.showToast('Không thể tải lên logo', 'error')
  } finally {
    loading.value = false
    if (logoInput.value) logoInput.value.value = ''
  }
}

const removeLogo = async () => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa logo',
    message: 'Bạn có chắc chắn muốn xóa logo của khách sạn?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  try {
    const res = await http.delete('/hotel-settings/logo')
    if (res.data && res.data.data) {
      hotelForm.logo_url = res.data.data.logo_url
      uiStore.showToast('Xóa logo thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi xóa logo:', err)
    uiStore.showToast('Không thể xóa logo', 'error')
  } finally {
    loading.value = false
  }
}

const qrCodeValue = computed(() => {
  const config = {
    domain: window.location.host,
    isSecureDomain: window.location.protocol === 'https:',
    password: '123'
  }
  return JSON.stringify(config)
})

const fetchHotelSettings = async () => {
  loading.value = true
  try {
    const res = await http.get('/hotel-settings')
    if (res.data && res.data.data) {
      Object.assign(hotelForm, res.data.data)
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình khách sạn:', err)
  } finally {
    loading.value = false
  }
}

const saveHotelSettings = async () => {
  if (hotelForm.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(hotelForm.email)) {
    uiStore.showToast('Email không đúng định dạng', 'warning')
    return
  }
  loading.value = true
  try {
    await http.put('/hotel-settings', hotelForm)
    uiStore.showToast('Lưu thông tin khách sạn thành công!', 'success')
  } catch (err) {
    console.error('Lỗi khi lưu cấu hình khách sạn:', err)
    const errorMsg = err.response?.data?.message || 'Không thể lưu cấu hình khách sạn'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const formatInputNumber = (val) => {
  if (val === null || val === undefined || val === '') return ''
  const clean = String(val).replace(/\D/g, '')
  if (!clean) return ''
  return Number(clean).toLocaleString('en-US')
}

const parseInputNumber = (val) => {
  if (val === null || val === undefined || val === '') return 0
  const clean = String(val).replace(/\D/g, '')
  return clean ? parseInt(clean, 10) : 0
}

onMounted(() => {
  fetchHotelSettings()
})
</script>

<template>
  <div class="flex flex-col gap-4 py-2 relative">
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30 min-h-[400px]">
      <div class="loader">
        <div class="inner one"></div>
        <div class="inner two"></div>
        <div class="inner three"></div>
      </div>
    </div>

    <!-- Action Save Button for Hotel Info Form -->
    <div class="flex justify-start">
      <button @click="saveHotelSettings"
        class="px-4 py-1.5 bg-sky-100 hover:bg-sky-200 border border-sky-300 hover:border-sky-400 text-sky-600 hover:text-sky-700 font-bold rounded-lg text-sm flex items-center gap-1.5 shadow-xs cursor-pointer transition-colors">
        <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v5h8" />
        </svg>
        Lưu
      </button>
    </div>

    <!-- Grid Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      <!-- Col 1: Basic settings -->
      <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-col gap-4">
        <div class="grid grid-cols-12 items-center gap-2 text-sm font-bold text-slate-600">
          <span class="col-span-2">Mã KS</span>
          <input type="text" v-model="hotelForm.division"
            class="col-span-3 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
          <span class="col-span-3 text-right pr-2">Tên KS/KNM</span>
          <input type="text" v-model="hotelForm.hotel_name"
            class="col-span-4 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Địa chỉ</span>
          <textarea v-model="hotelForm.address" rows="2"
            class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm resize-none"></textarea>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Thuế</span>
          <input type="text" v-model="hotelForm.tax_code"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Số điện thoại</span>
          <input type="text" v-model="hotelForm.phone"
            class="col-span-2 border border-slate-200 bg-yellow-50 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Số fax</span>
          <input type="text" v-model="hotelForm.fax"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Email</span>
          <input type="email" v-model="hotelForm.email"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Facebook</span>
          <input type="text" v-model="hotelForm.facebook"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Kênh quản lý</span>
          <input type="text" v-model="hotelForm.channel_manager"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Tiền tệ</span>
          <select v-model="hotelForm.currency"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold bg-white text-sm">
            <option value="VND">🇻🇳 VND</option>
            <option value="USD">🇺🇸 USD</option>
          </select>
        </div>
      </div>

      <!-- Col 2: Bank details and prices -->
      <div class="lg:col-span-4 bg-white border border-slate-200 rounded-2xl p-6 shadow-xs flex flex-col gap-4">
        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Tên ngân hàng</span>
          <input type="text" v-model="hotelForm.bank"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Tên tài khoản</span>
          <input type="text" v-model="hotelForm.account_name"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Số tài khoản</span>
          <input type="text" v-model="hotelForm.account"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Giá ăn sáng người lớn</span>
          <input type="text" :value="formatInputNumber(hotelForm.breakfast_adult_rate)"
            @input="e => { hotelForm.breakfast_adult_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.breakfast_adult_rate); }"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Giá ăn sáng trẻ em</span>
          <input type="text" :value="formatInputNumber(hotelForm.breakfast_child_rate)"
            @input="e => { hotelForm.breakfast_child_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.breakfast_child_rate); }"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Giá Thêm Giường</span>
          <input type="text" :value="formatInputNumber(hotelForm.extra_bed_rate)"
            @input="e => { hotelForm.extra_bed_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.extra_bed_rate); }"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Số phòng</span>
          <input type="number" v-model="hotelForm.room_number"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 bg-yellow-50 focus:outline-sky-500 font-bold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Web Hotel</span>
          <input type="text" v-model="hotelForm.website"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-semibold text-sm" />
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-sm font-bold text-slate-600">
          <span>Tiền tố mã đăng ký</span>
          <input type="text" v-model="hotelForm.prefix_booking_id"
            class="col-span-2 border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 font-bold uppercase text-sm" />
        </div>
      </div>

      <!-- Col 3: Logo and QR -->
      <div class="lg:col-span-3 flex flex-col items-center">
        <!-- Logo Box (Card style) -->
        <div class="w-full bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col overflow-hidden">
          <div class="p-3 bg-slate-50 border-b border-slate-100 text-center font-bold text-slate-700 text-sm">
            Hình ảnh
          </div>
          <div class="p-6 flex flex-col items-center justify-center gap-4">
            <!-- Logo Image -->
            <div v-if="hotelForm.logo_url"
              class="w-24 h-24 rounded-full overflow-hidden shadow-inner border border-slate-200">
              <img :src="getImageUrl(hotelForm.logo_url)" alt="Logo" class="w-full h-full object-cover" />
            </div>
            <div v-else
              class="w-24 h-24 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 shadow-inner">
              <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <!-- Action buttons -->
            <div class="flex items-center gap-4 text-slate-400">
              <label
                class="p-1 text-slate-400 hover:text-sky-600 bg-transparent border-none cursor-pointer flex items-center justify-center">
                <input type="file" ref="logoInput" @change="onLogoSelected" class="hidden" accept="image/*" />
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
              </label>
              <a v-if="hotelForm.logo_url" :href="getImageUrl(hotelForm.logo_url)" target="_blank"
                class="p-1 text-slate-400 hover:text-slate-700 bg-transparent border-none cursor-pointer flex items-center justify-center">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </a>
              <button v-if="hotelForm.logo_url" @click="removeLogo"
                class="p-1 text-red-500 hover:text-red-700 bg-transparent border-none cursor-pointer">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- QR Box -->
        <div class="flex flex-col items-center p-2 mt-4 gap-2">
          <div
            class="w-32 h-32 rounded-xl overflow-hidden border border-slate-200 bg-white p-2 shadow-2xs flex items-center justify-center">
            <qrcode-vue :value="qrCodeValue" :size="112" level="M" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

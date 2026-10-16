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
  const cleanPath = path.startsWith('/') ? path.slice(1) : path
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  return isDev ? `${backendUrl}/${cleanPath}` : `/${cleanPath}`
}

const logoInput = ref(null)

const onLogoSelected = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('logo', file)

  loading.value = true
  try {
    const res = await http.post('/hotel-settings/logo', formData)
    const logoUrl = res.data?.data?.logo_url ?? res.data?.logo_url
    if (logoUrl !== undefined) {
      hotelForm.logo_url = logoUrl
      uiStore.showToast('Tải lên logo thành công!', 'success')
    }
  } catch (err) {
    console.error('Lỗi khi tải lên logo:', err)
    const errorMsg = err?.response?.data?.message || err?.response?.data?.errors?.logo?.[0] || 'Không thể tải lên logo'
    uiStore.showToast(errorMsg, 'error')
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
  <div class="flex flex-col gap-2 py-0 relative">
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
      <button @click="saveHotelSettings" class="btn-pms-primary">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
          <polyline points="17 21 17 13 7 13 7 21" />
          <polyline points="7 3 7 8 15 8" />
        </svg>
        <span>Lưu</span>
      </button>
    </div>

    <!-- Grid Content -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
      <!-- Col 1: Basic settings -->
      <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl p-3 shadow-xs flex flex-col gap-1.5">
        <div class="grid grid-cols-12 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span class="col-span-2">Mã Khách Sạn <span class="text-red-500">*</span></span>
          <div class="relative flex items-center col-span-3">
            <input type="text" v-model="hotelForm.division"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
            <button v-if="hotelForm.division" type="button" @click="hotelForm.division = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
          <span class="col-span-3 text-right pr-2">Tên Khách Sạn / KNM <span class="text-red-500">*</span></span>
          <div class="relative flex items-center col-span-4">
            <input type="text" v-model="hotelForm.hotel_name"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
            <button v-if="hotelForm.hotel_name" type="button" @click="hotelForm.hotel_name = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Địa Chỉ <span class="text-red-500">*</span></span>
          <div class="relative flex col-span-2">
            <textarea v-model="hotelForm.address" rows="2"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308] resize-none h-11"></textarea>
            <button v-if="hotelForm.address" type="button" @click="hotelForm.address = ''"
              class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Mã Số Thuế</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.tax_code"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.tax_code" type="button" @click="hotelForm.tax_code = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Số Điện Thoại <span class="text-red-500">*</span></span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.phone"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
            <button v-if="hotelForm.phone" type="button" @click="hotelForm.phone = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Số Fax</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.fax"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.fax" type="button" @click="hotelForm.fax = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Email</span>
          <div class="relative flex items-center col-span-2">
            <input type="email" v-model="hotelForm.email"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.email" type="button" @click="hotelForm.email = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Facebook</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.facebook"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.facebook" type="button" @click="hotelForm.facebook = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Kênh Quản Lý</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.channel_manager"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.channel_manager" type="button" @click="hotelForm.channel_manager = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Tiền Tệ</span>
          <select v-model="hotelForm.currency"
            class="col-span-2 border border-slate-200 rounded-lg px-2.5 py-1.5 font-normal bg-white text-xs text-[#000000D9] focus:outline-none focus:border-sky-500">
            <option value="VND">🇻🇳 VND</option>
            <option value="USD">🇺🇸 USD</option>
          </select>
        </div>
      </div>

      <!-- Col 2: Bank details and prices -->
      <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl p-3 shadow-xs flex flex-col gap-1.5">
        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Tên Ngân Hàng</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.bank"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.bank" type="button" @click="hotelForm.bank = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Tên Tài Khoản</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.account_name"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.account_name" type="button" @click="hotelForm.account_name = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Số Tài Khoản</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.account"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.account" type="button" @click="hotelForm.account = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Giá Ăn Sáng Người Lớn</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" :value="formatInputNumber(hotelForm.breakfast_adult_rate)"
              @input="e => { hotelForm.breakfast_adult_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.breakfast_adult_rate); }"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.breakfast_adult_rate" type="button" @click="hotelForm.breakfast_adult_rate = 0"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Giá Ăn Sáng Trẻ Em</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" :value="formatInputNumber(hotelForm.breakfast_child_rate)"
              @input="e => { hotelForm.breakfast_child_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.breakfast_child_rate); }"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.breakfast_child_rate" type="button" @click="hotelForm.breakfast_child_rate = 0"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Giá Thêm Giường</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" :value="formatInputNumber(hotelForm.extra_bed_rate)"
              @input="e => { hotelForm.extra_bed_rate = parseInputNumber(e.target.value); e.target.value = formatInputNumber(hotelForm.extra_bed_rate); }"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.extra_bed_rate" type="button" @click="hotelForm.extra_bed_rate = 0"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Số Phòng <span class="text-red-500">*</span></span>
          <div class="relative flex items-center col-span-2">
            <input type="number" v-model="hotelForm.room_number"
              class="w-full border border-[#F1DD8A] bg-[#FFF8DB] rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-[#eab308] focus:ring-1 focus:ring-[#eab308]" />
            <button v-if="hotelForm.room_number" type="button" @click="hotelForm.room_number = 0"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Web Khách Sạn</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.website"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.website" type="button" @click="hotelForm.website = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>

        <div class="grid grid-cols-3 items-center gap-2 text-xs font-semibold text-[#000000D9]">
          <span>Tiền Tố Mã Đăng Ký</span>
          <div class="relative flex items-center col-span-2">
            <input type="text" v-model="hotelForm.prefix_booking_id"
              class="w-full border border-slate-200 bg-white rounded-lg px-2.5 py-1.5 pr-6 text-xs font-normal uppercase text-[#000000D9] focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500" />
            <button v-if="hotelForm.prefix_booking_id" type="button" @click="hotelForm.prefix_booking_id = ''"
              class="absolute right-2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0 text-xs"
              title="Xóa">✕</button>
          </div>
        </div>
      </div>

      <!-- Col 3: Logo and QR -->
      <div class="lg:col-span-3 flex flex-col items-center">
        <!-- Logo Box (Card style) -->
        <div class="w-full bg-white rounded-xl border border-slate-200 shadow-xs flex flex-col overflow-hidden">
          <div class="py-1.5 px-3 bg-slate-50 border-b border-slate-200 text-center font-semibold text-[#000000D9] text-xs">
            Hình Ảnh & Logo
          </div>
          <div class="p-2.5 flex flex-col items-center justify-center gap-2">
            <!-- Logo Image -->
            <div v-if="hotelForm.logo_url"
              class="w-16 h-16 rounded-full overflow-hidden shadow-inner border border-slate-200">
              <img :src="getImageUrl(hotelForm.logo_url)" alt="Logo" class="w-full h-full object-cover" />
            </div>
            <div v-else
              class="w-16 h-16 rounded-full bg-sky-50 flex items-center justify-center text-sky-500 shadow-inner">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <!-- Action buttons -->
            <div class="flex items-center gap-2 text-slate-400">
              <label
                class="px-2 py-1 text-xs font-semibold text-slate-700 hover:text-sky-600 bg-slate-100 hover:bg-slate-200 rounded-md border border-slate-200 cursor-pointer flex items-center gap-1.5 transition-colors">
                <input type="file" ref="logoInput" @change="onLogoSelected" class="hidden" accept="image/*" />
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <span>Tải Ảnh</span>
              </label>
              <a v-if="hotelForm.logo_url" :href="getImageUrl(hotelForm.logo_url)" target="_blank"
                class="p-1 text-slate-500 hover:text-slate-800 bg-slate-100 rounded-md border border-slate-200 cursor-pointer flex items-center justify-center transition-colors"
                title="Xem Ảnh Gốc">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </a>
              <button v-if="hotelForm.logo_url" @click="removeLogo"
                class="p-1 text-rose-500 hover:text-rose-700 bg-rose-50 rounded-md border border-rose-200 cursor-pointer flex items-center justify-center transition-colors"
                title="Xóa Logo">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- QR Box (Enlarged) -->
        <div class="flex flex-col items-center p-1 mt-1 gap-1">
          <span class="text-xs font-semibold text-[#000000D9]">Mã QR Cấu Hình</span>
          <div
            class="w-32 h-32 rounded-xl overflow-hidden border border-slate-200 bg-white p-1.5 shadow-2xs flex items-center justify-center">
            <qrcode-vue :value="qrCodeValue" :size="112" level="M" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

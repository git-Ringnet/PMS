<script setup>
import { ref } from 'vue'

const form = ref({
  zone: 'Galliot',
  name: 'Galliot Hotel Nha Trang',
  address: '195 Nguyễn Thiện Thuật, Phường Nha Trang, Tỉnh Khánh Hòa',
  taxCode: '0313161911-001',
  phone: '+84 258 3528 555',
  fax: '',
  email: 'fo.galliot@navyhotelgroup.com',
  website: 'www.galliothotel.vn',
  currency: 'VND'
})
</script>

<template>
  <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start w-full">
    <!-- Left Form Card -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm flex flex-col gap-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Khu vực</label>
          <input type="text" v-model="form.zone" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Tên điểm bán hàng</label>
          <input type="text" v-model="form.name" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Địa chỉ</label>
        <input type="text" v-model="form.address" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Mã số thuế</label>
          <input type="text" v-model="form.taxCode" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Số điện thoại</label>
          <input type="text" v-model="form.phone" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Fax</label>
          <input type="text" v-model="form.fax" placeholder="Nhập số Fax..." class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Email</label>
          <input type="email" v-model="form.email" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Website</label>
          <input type="text" v-model="form.website" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 text-slate-700 transition font-medium" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Tiền tệ</label>
          <div class="relative">
            <select v-model="form.currency" class="w-full pl-9 pr-8 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 appearance-none text-slate-700 transition cursor-pointer font-medium">
              <option value="VND">🇻🇳 VND</option>
              <option value="USD">🇺🇸 USD</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
            <span class="absolute left-3 top-2.5 text-sm select-none pointer-events-none">
              {{ form.currency === 'VND' ? '🇻🇳' : '🇺🇸' }}
            </span>
          </div>
        </div>
      </div>
    </div>
    <!-- Right Logo Card -->
    <div class="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm flex flex-col items-center">
      <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wide self-start mb-4">Logo</h3>
      <div class="w-44 h-44 rounded-xl border border-slate-200 flex flex-col items-center justify-center bg-slate-50 relative overflow-hidden group">
        <div class="w-24 h-24 rounded-full bg-white shadow-sm flex items-center justify-center border border-slate-100">
          <svg class="w-16 h-16 text-emerald-500" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="35" stroke="currentColor" stroke-width="8" stroke-dasharray="160 50" stroke-linecap="round" />
            <circle cx="50" cy="50" r="15" fill="currentColor" opacity="0.8" />
            <rect x="42" y="47" width="22" height="6" fill="currentColor" rx="3" />
          </svg>
        </div>
        <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-4 text-slate-400">
          <button class="hover:text-sky-600 transition" title="Xem ảnh">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          <button class="hover:text-red-600 transition" title="Xóa logo">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
      <p class="text-[11px] text-slate-400 mt-4 text-center">Định dạng hỗ trợ: PNG, JPG. Dung lượng tối đa: 2MB.</p>
    </div>
  </div>
</template>

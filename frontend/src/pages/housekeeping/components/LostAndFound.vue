<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

// --- Mock Data ---
const mockData = ref([
  {
    id: 1,
    image: 'https://images.unsplash.com/photo-1606229365485-93a3b8ee0385?w=150&q=80',
    category: 'Macbook Air M1',
    foundTime: '14:30',
    foundDate: '2026-06-16',
    location: 'Phòng 302',
    finder: 'Nguyễn Văn A (Dọn phòng)',
    keeper: 'Lễ tân',
    handledDate: '2026-06-17',
    handledTime: '15:00',
    method: 'Trả khách',
    returner: 'Trần Thị B (Lễ tân)',
    receiver: 'John Doe',
    remarks: 'Khách quay lại lấy sau khi check-out'
  },
  {
    id: 2,
    image: '',
    category: 'Ví da Nam',
    foundTime: '09:15',
    foundDate: '2026-06-17',
    location: 'Hành lang tầng 4',
    finder: 'Lê C (Bảo vệ)',
    keeper: 'Kho buồng phòng',
    handledDate: '',
    handledTime: '',
    method: 'Lưu kho',
    returner: '',
    receiver: '',
    remarks: 'Đang chờ khách liên hệ'
  }
])

// --- Table State ---
const selectedRows = ref([])
const toggleAll = (e) => {
  if (e.target.checked) {
    selectedRows.value = mockData.value.map(item => item.id)
  } else {
    selectedRows.value = []
  }
}

// --- Modal State ---
const showModal = ref(false)
const isEditing = ref(false)
const previewImage = ref(null)

const formState = reactive({
  id: null,
  image: '',
  category: '',
  foundTime: '',
  foundDate: '',
  location: '',
  finder: '',
  keeper: '',
  handledDate: '',
  handledTime: '',
  method: '',
  returner: '',
  receiver: '',
  remarks: ''
})

const initialFormString = ref('')

const isDirty = computed(() => {
  return JSON.stringify(formState) !== initialFormString.value || previewImage.value !== null
})

const openAddModal = () => {
  isEditing.value = false
  previewImage.value = null
  const now = new Date()
  const currentDate = now.toISOString().split('T')[0]
  const currentTime = now.toTimeString().slice(0,5)
  
  Object.assign(formState, {
    id: null,
    image: '',
    category: '',
    foundTime: currentTime,
    foundDate: currentDate,
    location: '',
    finder: '',
    keeper: '',
    handledDate: '',
    handledTime: '',
    method: 'Lưu kho',
    returner: '',
    receiver: '',
    remarks: ''
  })
  initialFormString.value = JSON.stringify(formState)
  showModal.value = true
}

const openEditModal = (item) => {
  isEditing.value = true
  previewImage.value = null
  Object.assign(formState, JSON.parse(JSON.stringify(item)))
  initialFormString.value = JSON.stringify(formState)
  showModal.value = true
}

const closeModal = async () => {
  if (isDirty.value) {
    const confirmed = await uiStore.confirm({ message: 'Dữ liệu chưa được lưu. Bạn có chắc chắn muốn đóng không?' })
    if (!confirmed) return
  }
  showModal.value = false
}

const handleSave = () => {
  if (!formState.category) {
    uiStore.showToast('Vui lòng nhập tên danh mục/mặt hàng', 'warning')
    return
  }
  
  if (isEditing.value) {
    const idx = mockData.value.findIndex(x => x.id === formState.id)
    if (idx !== -1) {
      // simulate uploading image if previewImage exists
      if (previewImage.value) {
         formState.image = previewImage.value
      }
      mockData.value[idx] = JSON.parse(JSON.stringify(formState))
      uiStore.showToast('Cập nhật thành công', 'success')
    }
  } else {
    formState.id = Date.now()
    if (previewImage.value) {
       formState.image = previewImage.value
    }
    mockData.value.push(JSON.parse(JSON.stringify(formState)))
    uiStore.showToast('Thêm mới thành công', 'success')
  }
  
  // Update clean state after save
  initialFormString.value = JSON.stringify(formState)
  previewImage.value = null
  showModal.value = false
}

const handleDelete = async () => {
  if (selectedRows.value.length === 0) return
  const confirmed = await uiStore.confirm({ message: `Bạn có chắc muốn xóa ${selectedRows.value.length} mục đã chọn không?` })
  if (!confirmed) return
  
  mockData.value = mockData.value.filter(item => !selectedRows.value.includes(item.id))
  selectedRows.value = []
  uiStore.showToast('Xóa thành công', 'success')
}

// Image upload simulation
const onFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      previewImage.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

const removeImage = () => {
  previewImage.value = null
  formState.image = ''
}

</script>

<template>
  <div class="h-full flex flex-col bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
    
    <!-- TOOLBAR -->
    <div class="flex items-center justify-between p-4 border-b border-slate-200 bg-slate-50 shrink-0">
      <div class="flex items-center gap-3">
        <button @click="openAddModal" class="px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm font-bold shadow-sm transition-colors cursor-pointer border-none flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          Thêm mới
        </button>
        <button 
          @click="handleDelete"
          :disabled="selectedRows.length === 0"
          class="px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-colors cursor-pointer border-none flex items-center gap-2"
          :class="selectedRows.length > 0 ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          Xóa ({{ selectedRows.length }})
        </button>
      </div>
      <div class="flex items-center gap-3">
        <button class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 rounded-lg text-sm font-bold shadow-sm transition-colors cursor-pointer flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
          In danh sách
        </button>
      </div>
    </div>

    <!-- DATA TABLE -->
    <div class="flex-1 overflow-auto bg-white p-4 print:p-0">
      <table class="w-full text-sm text-left border-collapse border border-slate-200 min-w-[1200px]">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold uppercase sticky top-0 z-10 shadow-sm print:shadow-none">
            <th rowspan="2" class="p-2 border border-slate-200 w-10 text-center print:hidden">
              <input type="checkbox" @change="toggleAll" :checked="selectedRows.length === mockData.length && mockData.length > 0" class="rounded text-sky-500 w-4 h-4 cursor-pointer" />
            </th>
            <th colspan="3" class="p-2 border border-slate-200 text-center text-[11px]">Vật phẩm</th>
            <th colspan="4" class="p-2 border border-slate-200 text-center text-[11px]">Thông tin liên quan đến vật phẩm được tìm thấy</th>
            <th colspan="1" class="p-2 border border-slate-200 text-center text-[11px]">Người giữ/Vị trí cất giữ</th>
            <th colspan="5" class="p-2 border border-slate-200 text-center text-[11px] bg-emerald-50">Xử lý</th>
            <th rowspan="2" class="p-2 border border-slate-200 text-center text-[11px] w-48">Ghi Chú</th>
          </tr>
          <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 font-bold text-xs sticky top-[37px] z-10 shadow-sm print:shadow-none">
            <th class="p-2 border border-slate-200 w-10 text-center">STT</th>
            <th class="p-2 border border-slate-200 w-14 text-center">Img</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Tên danh mục</th>
            <th class="p-2 border border-slate-200 w-16 text-center">Giờ</th>
            <th class="p-2 border border-slate-200 w-24 text-center">Ngày</th>
            <th class="p-2 border border-slate-200 min-w-[100px]">Địa Điểm</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Người Tìm Thấy</th>
            <th class="p-2 border border-slate-200 min-w-[120px]">Người Nhận</th>
            <th class="p-2 border border-slate-200 w-24 text-center bg-emerald-50/50">Ngày xử lý</th>
            <th class="p-2 border border-slate-200 w-16 text-center bg-emerald-50/50">Giờ xử lý</th>
            <th class="p-2 border border-slate-200 min-w-[110px] text-center bg-emerald-50/50">Phương Thức</th>
            <th class="p-2 border border-slate-200 min-w-[120px] bg-emerald-50/50">Tên Người Trả</th>
            <th class="p-2 border border-slate-200 min-w-[120px] bg-emerald-50/50">Tên Người Nhận</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="mockData.length === 0">
            <td colspan="15" class="p-8 text-center text-slate-500 font-medium bg-slate-50">Không có dữ liệu đồ thất lạc.</td>
          </tr>
          <tr 
            v-for="(item, index) in mockData" 
            :key="item.id"
            @dblclick="openEditModal(item)"
            class="border-b border-slate-100 hover:bg-sky-50/50 transition-colors group cursor-pointer text-sm"
          >
            <td class="p-2 border border-slate-200 text-center print:hidden" @click.stop>
              <input type="checkbox" :value="item.id" v-model="selectedRows" class="rounded text-sky-500 w-4 h-4 cursor-pointer" />
            </td>
            <td class="p-2 border border-slate-200 text-center font-semibold text-slate-500">{{ index + 1 }}</td>
            <td class="p-1 border border-slate-200 text-center">
              <div v-if="item.image" class="w-10 h-10 mx-auto rounded overflow-hidden border border-slate-200">
                <img :src="item.image" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-10 h-10 mx-auto rounded bg-slate-100 flex items-center justify-center border border-slate-200 text-slate-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              </div>
            </td>
            <td class="p-2 border border-slate-200 font-bold text-slate-800">{{ item.category }}</td>
            <td class="p-2 border border-slate-200 text-center text-slate-600 font-semibold">{{ item.foundTime }}</td>
            <td class="p-2 border border-slate-200 text-center text-slate-600">{{ item.foundDate }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.location }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.finder }}</td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.keeper }}</td>
            <td class="p-2 border border-slate-200 text-center text-emerald-600">{{ item.handledDate }}</td>
            <td class="p-2 border border-slate-200 text-center text-emerald-600 font-semibold">{{ item.handledTime }}</td>
            <td class="p-2 border border-slate-200 text-center">
              <span v-if="item.method === 'Trả khách'" class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[11px] font-bold uppercase">{{ item.method }}</span>
              <span v-else-if="item.method === 'Lưu kho'" class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[11px] font-bold uppercase">{{ item.method }}</span>
              <span v-else-if="item.method" class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px] font-bold uppercase">{{ item.method }}</span>
            </td>
            <td class="p-2 border border-slate-200 text-slate-700">{{ item.returner }}</td>
            <td class="p-2 border border-slate-200 text-slate-700 font-semibold">{{ item.receiver }}</td>
            <td class="p-2 border border-slate-200 text-slate-500 text-xs" :title="item.remarks">
              <div class="line-clamp-2">{{ item.remarks }}</div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="mt-4 text-xs text-slate-500 italic print:hidden">Mẹo: Click đúp vào một dòng để xem hoặc cập nhật thông tin.</div>
    </div>

    <!-- ADD/EDIT MODAL -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6 print:hidden">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal"></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-full flex flex-col overflow-hidden animate-fade-in-up">
          
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 bg-sky-500 text-white shrink-0">
            <h3 class="font-bold text-lg m-0 flex items-center gap-2">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
              {{ isEditing ? 'Cập nhật Đồ Thất Lạc' : 'Thêm mới Đồ Thất Lạc' }}
            </h3>
            <button @click="closeModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors cursor-pointer bg-transparent border-none text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6 bg-slate-50 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              
              <!-- Left Column -->
              <div class="flex flex-col gap-6">
                <!-- Khối 1: Thông tin món đồ -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-sky-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                    Thông tin món đồ
                  </h4>
                  <div class="space-y-4">
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Mặt hàng / Tên danh mục <span class="text-red-500">*</span></label>
                      <input type="text" v-model="formState.category" placeholder="Ví dụ: Áo khoác, Điện thoại..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 font-semibold text-slate-800" />
                    </div>
                    
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Hình ảnh đính kèm</label>
                      <div class="flex items-start gap-4">
                        <div class="w-24 h-24 shrink-0 bg-slate-100 border-2 border-dashed border-slate-300 rounded-xl overflow-hidden flex items-center justify-center relative group">
                          <img v-if="previewImage || formState.image" :src="previewImage || formState.image" class="w-full h-full object-cover" />
                          <svg v-else class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                          
                          <div v-if="previewImage || formState.image" class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="removeImage" class="p-1 bg-red-500 text-white rounded-full hover:bg-red-600 border-none cursor-pointer">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                          </div>
                        </div>
                        <div class="flex-1">
                          <input type="file" id="imageUpload" accept="image/*" @change="onFileChange" class="hidden" />
                          <label for="imageUpload" class="inline-block px-4 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-sm font-bold shadow-sm hover:bg-slate-50 cursor-pointer transition-colors">
                            Chọn ảnh
                          </label>
                          <p class="mt-2 text-xs text-slate-500">Hỗ trợ JPG, PNG (Tối đa 5MB).</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Khối 2: Thông tin tìm thấy -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-sky-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    Thông tin Tìm thấy
                  </h4>
                  <div class="space-y-4">
                    <div class="flex gap-4">
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Giờ tìm thấy</label>
                        <input type="time" v-model="formState.foundTime" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                      </div>
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Ngày tìm thấy</label>
                        <input type="date" v-model="formState.foundDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Địa điểm</label>
                      <input type="text" v-model="formState.location" placeholder="Ví dụ: Phòng 101, Hồ bơi..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người tìm thấy</label>
                      <input type="text" v-model="formState.finder" placeholder="Tên nhân viên..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người nhận (Người giữ / Vị trí cất giữ)</label>
                      <input type="text" v-model="formState.keeper" placeholder="Ví dụ: Lễ tân, Kho..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column -->
              <div class="flex flex-col gap-6">
                <!-- Khối 3: Xử lý -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                  <h4 class="text-sm font-bold text-emerald-600 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Tiến trình Xử lý
                  </h4>
                  <div class="space-y-4">
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Phương thức xử lý</label>
                      <select v-model="formState.method" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500 font-semibold" :class="formState.method === 'Trả khách' ? 'text-emerald-700' : formState.method === 'Lưu kho' ? 'text-amber-700' : 'text-slate-800'">
                        <option value="">-- Chọn phương thức --</option>
                        <option value="Lưu kho">Lưu kho chờ xử lý</option>
                        <option value="Trả khách">Trả lại cho khách</option>
                        <option value="Hủy">Tiêu hủy / Bỏ đi</option>
                      </select>
                    </div>
                    <div class="flex gap-4">
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Ngày xử lý</label>
                        <input type="date" v-model="formState.handledDate" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                      </div>
                      <div class="w-1/2">
                        <label class="block text-xs font-bold text-slate-600 mb-1">Giờ xử lý</label>
                        <input type="time" v-model="formState.handledTime" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Người bàn giao (Người trả)</label>
                      <input type="text" v-model="formState.returner" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500" />
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-slate-600 mb-1">Tên người nhận (Khách hàng)</label>
                      <input type="text" v-model="formState.receiver" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500 font-bold" />
                    </div>
                  </div>
                </div>

                <!-- Khối 4: Ghi chú -->
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex-1 flex flex-col">
                  <h4 class="text-sm font-bold text-slate-700 uppercase mb-4 flex items-center gap-2 border-b border-slate-100 pb-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    Ghi chú thêm
                  </h4>
                  <div class="flex-1 min-h-[100px]">
                    <textarea v-model="formState.remarks" placeholder="Ghi chú các thông tin quan trọng..." class="w-full h-full min-h-[100px] px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-sky-500 focus:border-sky-500 resize-none"></textarea>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Footer Actions -->
          <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50 border-t border-slate-200 shrink-0">
            <button @click="closeModal" class="px-5 py-2.5 rounded-lg text-sm font-bold text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer shadow-sm">
              Đóng
            </button>
            <button @click="handleSave" class="px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-sky-500 hover:bg-sky-600 border-none transition-colors cursor-pointer shadow-sm flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
              Lưu Thông Tin
            </button>
          </div>
          
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@media print {
  @page { margin: 1cm; size: landscape; }
  body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
}

.animate-fade-in-up {
  animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: #94a3b8;
}
</style>

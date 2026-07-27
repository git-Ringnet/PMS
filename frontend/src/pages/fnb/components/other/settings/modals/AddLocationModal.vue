<script setup>
import { ref, onMounted } from 'vue'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  location: {
    type: Object,
    default: null
  },
  outletCode: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'save'])

const name = ref('')
const note = ref('')
const letter = ref('')
const color = ref('#38bdf8') // default sky-400 color
const isActive = ref(true)
const imageFile = ref(null)
const imagePreview = ref(null)

const getImageUrl = (path) => {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    return path
  }
  const isDev = import.meta.env.DEV
  const backendUrl = import.meta.env.VITE_PROXY_TARGET || 'http://localhost:8000'
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  let finalPath = cleanPath
  if (!cleanPath.startsWith('storage/')) {
    finalPath = 'storage/' + cleanPath
  }
  return isDev ? `${backendUrl}/${finalPath}` : `/${finalPath}`
}

onMounted(() => {
  if (props.location) {
    name.value = props.location.name
    note.value = props.location.note || ''
    letter.value = props.location.letter || ''
    color.value = props.location.color || '#38bdf8'
    isActive.value = props.location.is_active !== false
    if (props.location.image) {
      imagePreview.value = getImageUrl(props.location.image)
    }
  }
})

const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    imageFile.value = file
    const reader = new FileReader()
    reader.onload = (event) => {
      imagePreview.value = event.target.result
    }
    reader.readAsDataURL(file)
  }
}

const triggerFileInput = () => {
  document.getElementById('location-image-input').click()
}

const removeImage = () => {
  imageFile.value = null
  imagePreview.value = null
}

const handleSave = () => {
  if (!name.value) {
    uiStore.alert('Vui lòng nhập tên khu vực!')
    return
  }

  const formData = new FormData()
  formData.append('name', name.value)
  formData.append('note', note.value)
  formData.append('outlet_code', props.outletCode)
  formData.append('letter', letter.value)
  formData.append('color', color.value)
  formData.append('is_active', isActive.value ? '1' : '0')
  
  if (imageFile.value) {
    formData.append('image', imageFile.value)
  } else if (!imagePreview.value && props.location && props.location.image) {
    // If image was removed
    formData.append('remove_image', '1')
  }

  emit('save', formData)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      <!-- Modal Header -->
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">{{ location ? 'Chỉnh sửa khu vực' : 'Thêm khu vực' }}</h3>
        <div class="flex items-center gap-3">
          <!-- Help Symbol -->
          <button class="w-6 h-6 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm" title="Trợ giúp">
            <span class="text-xs font-bold">?</span>
          </button>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto max-h-[75vh] flex gap-5">
        <!-- Left Side: Image upload -->
        <div class="w-40 shrink-0 flex flex-col items-center">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 text-center w-full">Hình ảnh</label>
          <div 
            @click="triggerFileInput" 
            class="w-full aspect-square border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition overflow-hidden relative group"
          >
            <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
            <div v-else class="flex flex-col items-center text-slate-400">
              <svg class="w-8 h-8 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
              <span class="text-[10px] font-bold">Chọn file</span>
            </div>
          </div>
          
          <input 
            type="file" 
            id="location-image-input" 
            class="hidden" 
            accept="image/*" 
            @change="handleFileChange" 
          />

          <!-- Action buttons for image -->
          <div v-if="imagePreview" class="flex gap-4 mt-3">
            <button @click="triggerFileInput" class="text-slate-500 hover:text-sky-600 transition" title="Thay đổi">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
            <button @click="removeImage" class="text-rose-500 hover:text-rose-600 transition" title="Xóa">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>

        <!-- Right Side: Form inputs -->
        <div class="flex-1 space-y-4">
          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tên khu vực</label>
            <input 
              v-model="name"
              type="text" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 bg-yellow-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold"
              placeholder="Ví dụ: NHÀ HÀNG"
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Mô tả</label>
            <input 
              v-model="note"
              type="text" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-medium"
              placeholder="Nhập mô tả khu vực..."
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Mã tiền tố</label>
            <input 
              v-model="letter"
              type="text" 
              class="w-full px-3 py-2 border border-slate-200 rounded-lg text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition font-semibold uppercase"
              placeholder="Ví dụ: A (bàn sẽ có dạng A1, A2...)"
              maxlength="10"
            />
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Màu khu vực</label>
            <div class="flex items-center gap-2 border border-slate-200 rounded-lg p-1 px-2.5">
              <input 
                v-model="color"
                type="color" 
                class="w-6 h-6 rounded-md cursor-pointer border-0 p-0"
              />
              <span class="text-slate-500 font-mono text-[10px] uppercase font-bold">{{ color }}</span>
            </div>
          </div>

          <div class="flex items-center justify-between pt-2">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hoạt động</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="isActive" class="sr-only peer" />
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-400"></div>
            </label>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-5 py-3 border-t border-slate-100 flex justify-end gap-3 bg-slate-50 shrink-0">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-100 transition font-semibold active:scale-95 shadow-sm bg-white"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 px-4 py-2 bg-sky-400 hover:bg-sky-500 text-white rounded-lg transition font-semibold active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

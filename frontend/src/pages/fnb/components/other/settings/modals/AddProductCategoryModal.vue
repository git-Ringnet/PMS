<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  category: {
    type: Object,
    default: null
  },
  categoriesList: {
    type: Array,
    default: () => []
  },
  outletCode: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['close', 'save'])

const name = ref('')
const parentId = ref('')
const code = ref('')
const description = ref('')
const orderIndex = ref(0)
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

// Flat indent option list builder for hierarchical categories
const parentOptions = computed(() => {
  // Exclude current category and its children to prevent circular reference
  const excludeIds = new Set()
  if (props.category) {
    excludeIds.add(props.category.id)
    const findChildren = (pid) => {
      props.categoriesList.forEach(c => {
        if (c.parent_id === pid) {
          excludeIds.add(c.id)
          findChildren(c.id)
        }
      })
    }
    findChildren(props.category.id)
  }

  const buildTree = (list, pid = null, depth = 0) => {
    let result = []
    const items = list.filter(item => item.parent_id === pid && !excludeIds.has(item.id))
    items.forEach(item => {
      result.push({
        id: item.id,
        name: item.name,
        indentName: '  '.repeat(depth) + (depth > 0 ? '↳ ' : '') + item.name
      })
      result = result.concat(buildTree(list, item.id, depth + 1))
    })
    return result
  }

  return buildTree(props.categoriesList, null, 0)
})

onMounted(() => {
  if (props.category) {
    name.value = props.category.name
    parentId.value = props.category.parent_id || ''
    code.value = props.category.code || ''
    description.value = props.category.description || ''
    orderIndex.value = props.category.order_index || 0
    isActive.value = props.category.is_active !== false
    if (props.category.image) {
      imagePreview.value = getImageUrl(props.category.image)
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
  document.getElementById('category-image-input').click()
}

const removeImage = () => {
  imageFile.value = null
  imagePreview.value = null
}

const handleSave = () => {
  if (!name.value.trim()) {
    alert('Vui lòng nhập tên loại thực đơn!')
    return
  }

  const formData = new FormData()
  formData.append('name', name.value.trim())
  formData.append('outlet', props.outletCode)
  formData.append('parent_id', parentId.value || '')
  formData.append('code', code.value.trim() || '')
  formData.append('description', description.value.trim() || '')
  formData.append('order_index', String(orderIndex.value))
  formData.append('is_active', isActive.value ? '1' : '0')

  if (imageFile.value) {
    formData.append('image', imageFile.value)
  } else if (!imagePreview.value && props.category && props.category.image) {
    formData.append('remove_image', '1')
  }

  emit('save', formData)
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-60 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-xl overflow-hidden flex flex-col transition-all transform scale-100 font-sans text-xs">
      <!-- Modal Header -->
      <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50">
        <h3 class="text-sm font-bold text-slate-800">{{ category ? 'Chỉnh sửa loại thực đơn' : 'Thêm loại thực đơn' }}</h3>
        <div class="flex items-center gap-3">
          <!-- Help Symbol -->
          <button class="w-6 h-6 rounded-full flex items-center justify-center border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition shadow-sm animate-pulse" title="Trợ giúp">
            <span class="text-xs font-bold">?</span>
          </button>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto max-h-[75vh] flex gap-6">
        <!-- Left Side: Image upload -->
        <div class="w-40 shrink-0 flex flex-col items-center">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 text-center w-full">Hình ảnh</label>
          <div 
            @click="triggerFileInput" 
            class="w-full aspect-square border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition overflow-hidden relative group"
          >
            <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
            <div v-else class="flex flex-col items-center text-slate-450 text-slate-400">
              <svg class="w-8 h-8 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4"/></svg>
              <span class="text-[10px] font-bold">Chọn file</span>
            </div>
          </div>
          
          <input 
            type="file" 
            id="category-image-input" 
            class="hidden" 
            accept="image/*" 
            @change="handleFileChange" 
          />

          <!-- Action buttons for image -->
          <div v-if="imagePreview" class="flex gap-4 mt-3 justify-center">
            <button @click="triggerFileInput" class="text-slate-400 hover:text-sky-600 transition" title="Thay đổi">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
            <button @click="removeImage" class="text-rose-450 text-rose-500 hover:text-rose-600 transition" title="Xóa">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>

        <!-- Right Side: Form inputs -->
        <div class="flex-1 flex flex-col gap-4">
          <!-- Name -->
          <div class="flex flex-col gap-1.5">
            <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Tên</label>
            <input 
              type="text" 
              v-model="name" 
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-slate-55/40 bg-slate-50"
              placeholder="Nhập tên nhóm thực đơn"
            />
          </div>

          <!-- Parent Category -->
          <div class="flex flex-col gap-1.5">
            <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Nhóm cha</label>
            <select 
              v-model="parentId" 
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white"
            >
              <option value="">Chọn giá trị</option>
              <option 
                v-for="opt in parentOptions" 
                :key="opt.id" 
                :value="opt.id"
              >
                {{ opt.indentName }}
              </option>
            </select>
          </div>

          <!-- Code -->
          <div class="flex flex-col gap-1.5">
            <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mã món</label>
            <input 
              type="text" 
              v-model="code" 
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500"
              placeholder="Nhập mã loại thực đơn"
            />
          </div>

          <!-- Description -->
          <div class="flex flex-col gap-1.5">
            <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Mô tả</label>
            <textarea 
              v-model="description" 
              rows="3"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500"
              placeholder="Mô tả nhóm thực đơn"
            ></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <!-- Order Index -->
            <div class="flex flex-col gap-1.5">
              <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Thứ tự</label>
              <input 
                type="number" 
                v-model.number="orderIndex" 
                min="0"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500"
              />
            </div>

            <!-- Active Switch -->
            <div class="flex flex-col gap-1.5">
              <label class="font-extrabold text-slate-500 uppercase tracking-wide text-[10px]">Kích hoạt</label>
              <div class="flex items-center h-8">
                <button 
                  @click="isActive = !isActive" 
                  class="relative inline-flex h-5.5 w-10 items-center rounded-full transition-colors focus:outline-none shrink-0" 
                  :class="isActive ? 'bg-[#78C5E7]' : 'bg-slate-300'"
                >
                  <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm" :class="isActive ? 'translate-x-[18px]' : 'translate-x-1'"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0 bg-slate-50/50">
        <button 
          @click="$emit('close')" 
          class="flex items-center gap-1.5 bg-[#e2f3fc] hover:bg-[#d0ebfa] text-sky-700 px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          Hủy
        </button>
        <button 
          @click="handleSave" 
          class="flex items-center gap-1.5 bg-[#78C5E7] hover:bg-[#60b3d6] text-white px-4 py-2 rounded-lg text-xs font-bold transition active:scale-95 shadow-sm"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

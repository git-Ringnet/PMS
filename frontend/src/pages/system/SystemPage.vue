<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth-store'
import CompanyInfoTab from './components/CompanyInfoTab.vue'
import BranchManageTab from './components/BranchManageTab.vue'
import EmployeeTab from './components/EmployeeTab.vue'
import ActivityLogTab from './components/ActivityLogTab.vue'

const router = useRouter()
const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)

const activeTab = ref('company') // 'company', 'branch', 'employee', 'activity_log'
const isDropdownOpen = ref(false)

// Collapsible states for sidebar groups
const openGroups = ref({
  org: true,
  manage: true,
  accounting: true
})

const toggleGroup = (group) => {
  openGroups.value[group] = !openGroups.value[group]
}

const selectTab = (tab) => {
  activeTab.value = tab
}

const handleBack = () => {
  router.push('/')
}

const handleLogout = async () => {
  if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
    isDropdownOpen.value = false
    await authStore.logout()
    router.push('/login')
  }
}

// Close user dropdown on click outside
const dropdownRef = ref(null)
const closeDropdown = (e) => {
  if (isDropdownOpen.value && dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isDropdownOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', closeDropdown)
})
</script>

<template>
  <div class="flex flex-col h-screen overflow-hidden bg-slate-50 font-sans select-none text-slate-800">
    <!-- Top Header Bar (Matching Admin System Layout) -->
    <header class="flex items-center justify-between h-12 bg-[#e0f2fe] border-b border-slate-200 px-4 shrink-0 z-50">
      <!-- Left: Back Button & Logo -->
      <div class="flex items-center gap-3">
        <button 
          @click="handleBack"
          class="flex items-center gap-1.5 text-slate-600 hover:text-slate-900 font-bold bg-transparent border-none cursor-pointer text-xs transition-colors"
        >
          <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          Quay lại Portal
        </button>
        <div class="h-4 w-[1px] bg-slate-300"></div>
        <div class="flex items-center gap-1.5">
          <div class="w-6 h-6 bg-[#0ea5e9] flex items-center justify-center rounded-sm rotate-45 transform-gpu overflow-hidden shadow-sm">
            <div class="w-3.5 h-3.5 bg-white -rotate-45 transform-gpu"></div>
          </div>
          <span class="text-sm font-black text-slate-800 tracking-wider">PROVISTA SYSTEM</span>
        </div>
      </div>

      <!-- Right Side: User Profile & Flag -->
      <div class="flex items-center gap-3 shrink-0">
        <!-- User Profile Dropdown -->
        <div class="relative shrink-0 animate-fade-in" ref="dropdownRef">
          <div 
            @click="isDropdownOpen = !isDropdownOpen" 
            class="flex items-center gap-1.5 text-slate-700 bg-slate-100 hover:bg-slate-200/80 px-2.5 py-1 rounded-full text-[11.5px] font-bold cursor-pointer transition-colors whitespace-nowrap border border-slate-200/60"
          >
            <img 
              v-if="currentUser?.avatar" 
              :src="currentUser.avatar" 
              alt="Avatar" 
              class="w-3.5 h-3.5 rounded-full object-cover border border-slate-300 shrink-0"
            />
            <svg v-else class="w-3 h-3 text-slate-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
            </svg>
            <span class="leading-none">{{ currentUser?.name || 'Demo' }}</span>
            <svg class="w-2.5 h-2.5 text-slate-400 transition-transform duration-200 shrink-0" :class="isDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </div>

          <!-- Dropdown Menu -->
          <div 
            v-if="isDropdownOpen" 
            class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 z-[100]"
          >
            <div class="px-4 py-2 border-b border-slate-100 mb-1.5">
              <p class="text-xs font-bold text-slate-800 truncate">{{ currentUser?.name || 'Demo' }}</p>
              <p class="text-[10px] text-slate-500 truncate mt-0.5">{{ currentUser?.email || 'admin@provista.com' }}</p>
            </div>
            <button 
              @click="handleLogout"
              class="w-full text-left px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors border-none bg-transparent cursor-pointer flex items-center gap-1.5"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>Đăng xuất</span>
            </button>
          </div>
        </div>

        <!-- Vietnamese Flag -->
        <div class="w-4.5 h-3 bg-red-600 flex items-center justify-center rounded-xs shadow-xs border border-red-700/10 shrink-0">
          <svg class="w-1.5 h-1.5 text-yellow-400 fill-current" viewBox="0 0 24 24">
            <path d="M12 .587l3.668 7.431 8.2 1.192-5.934 5.787 1.4 8.168L12 18.896l-7.334 3.857 1.4-8.168L.132 9.21l8.2-1.192L12 .587z"/>
          </svg>
        </div>
      </div>
    </header>

    <!-- Main Workspace Area -->
    <div class="flex-1 flex overflow-hidden">
      <!-- Left Sidebar (Collapsible groups - Matches screenshot styling) -->
      <aside class="w-56 bg-white border-r border-slate-200 flex flex-col shrink-0 overflow-y-auto select-none select-none">
        <div class="flex flex-col py-2">
          <!-- 1. Nhóm: Thông tin tổ chức -->
          <div class="flex flex-col">
            <div 
              @click="toggleGroup('org')"
              class="flex items-center justify-between px-3 py-2 text-xs font-black text-slate-700 bg-sky-50/50 cursor-pointer hover:bg-sky-50 transition-colors"
            >
              <span class="tracking-wide">Thông tin tổ chức</span>
              <svg 
                class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" 
                :class="openGroups.org ? 'rotate-180' : ''" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
            
            <div v-show="openGroups.org" class="flex flex-col">
              <!-- Thông tin công ty -->
              <button 
                @click="selectTab('company')"
                class="w-full text-left px-4 py-2 border-none bg-transparent cursor-pointer transition-colors text-xs font-semibold"
                :class="activeTab === 'company' 
                  ? 'bg-sky-100 text-sky-700 font-bold border-l-2 border-sky-500' 
                  : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
              >
                - Thông tin công ty
              </button>
              
              <!-- Chi nhánh -->
              <button 
                @click="selectTab('branch')"
                class="w-full text-left px-4 py-2 border-none bg-transparent cursor-pointer transition-colors text-xs font-semibold"
                :class="activeTab === 'branch' 
                  ? 'bg-sky-100 text-sky-700 font-bold border-l-2 border-sky-500' 
                  : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
              >
                - Chi nhánh
              </button>

              <!-- Cơ cấu tổ chức -->
              <button 
                disabled
                class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold opacity-60"
              >
                - Cơ cấu tổ chức
              </button>
              
              <!-- Nhân viên -->
              <button 
                @click="selectTab('employee')"
                class="w-full text-left px-4 py-2 border-none bg-transparent cursor-pointer transition-colors text-xs font-semibold"
                :class="activeTab === 'employee' 
                  ? 'bg-sky-100 text-sky-700 font-bold border-l-2 border-sky-500' 
                  : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
              >
                - Nhân viên
              </button>

              <!-- Lịch sử thao tác -->
              <button 
                @click="selectTab('activity_log')"
                class="w-full text-left px-4 py-2 border-none bg-transparent cursor-pointer transition-colors text-xs font-semibold"
                :class="activeTab === 'activity_log' 
                  ? 'bg-sky-100 text-sky-700 font-bold border-l-2 border-sky-500' 
                  : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
              >
                - Lịch sử thao tác
              </button>

              <!-- Tài khoản ngân hàng -->
              <button 
                disabled
                class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold opacity-60"
              >
                - Tài khoản ngân hàng
              </button>
            </div>
          </div>

          <div class="h-[1px] bg-slate-100 my-2 mx-3"></div>

          <!-- 2. Nhóm: Thông tin Quản lý -->
          <div class="flex flex-col">
            <div 
              @click="toggleGroup('manage')"
              class="flex items-center justify-between px-3 py-2 text-xs font-black text-slate-700 bg-sky-50/50 cursor-pointer hover:bg-sky-50 transition-colors"
            >
              <span class="tracking-wide">Thông tin Quản lý</span>
              <svg 
                class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" 
                :class="openGroups.manage ? 'rotate-180' : ''" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
            
            <div v-show="openGroups.manage" class="flex flex-col opacity-60">
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Danh mục đối tượng</button>
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Danh mục vật tư</button>
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Danh mục hàng bán</button>
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Định nghĩa mua hàng</button>
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Thông Số</button>
            </div>
          </div>

          <div class="h-[1px] bg-slate-100 my-2 mx-3"></div>

          <!-- 3. Nhóm: Thông tin Kế Toán -->
          <div class="flex flex-col">
            <div 
              @click="toggleGroup('accounting')"
              class="flex items-center justify-between px-3 py-2 text-xs font-black text-slate-700 bg-sky-50/50 cursor-pointer hover:bg-sky-50 transition-colors"
            >
              <span class="tracking-wide">Thông tin Kế Toán</span>
              <svg 
                class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" 
                :class="openGroups.accounting ? 'rotate-180' : ''" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>
            
            <div v-show="openGroups.accounting" class="flex flex-col opacity-60">
              <button disabled class="w-full text-left px-4 py-2 border-none bg-transparent text-slate-400 cursor-not-allowed text-xs font-semibold">- Danh mục tài khoản</button>
            </div>
          </div>
        </div>
      </aside>

      <!-- Right: Main Content Area -->
      <main class="flex-1 bg-slate-50 overflow-hidden flex flex-col">
        <!-- Render Active Tab Component -->
        <transition name="fade" mode="out-in">
          <CompanyInfoTab v-if="activeTab === 'company'" />
          <BranchManageTab v-else-if="activeTab === 'branch'" />
          <EmployeeTab v-else-if="activeTab === 'employee'" />
          <ActivityLogTab v-else-if="activeTab === 'activity_log'" />
        </transition>
      </main>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>

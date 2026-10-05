<template>
  <div
    v-if="nightAuditStore.showModal"
    class="fixed inset-0 z-[9999] bg-white flex flex-col items-center justify-center select-none overflow-y-auto p-4 md:p-6"
    style="background: #ffffff url('/night-audit-bg.png') no-repeat center center; background-size: cover;"
  >
    <!-- Main Center Content Container -->
    <div class="relative z-10 flex flex-col items-center w-full max-w-[880px] my-auto">
      <!-- Calendar Illustration -->
      <img
        src="/night-audit-illustration.png"
        alt="Night Audit"
        class="w-[460px] md:w-[520px] max-w-[85vw] h-auto object-contain mb-6 pointer-events-none select-none"
      />

      <!-- Progress Bar Container (800px max, 34px height, track #f4f4f4, fully rounded pill) -->
      <div class="w-full max-w-[800px] bg-[#f4f4f4] rounded-full h-[34px] overflow-hidden p-0 shadow-none border-none">
        <div
          class="h-full rounded-full transition-all duration-300 ease-out"
          :class="[
            nightAuditStore.auditRunStatus === 'failed'
              ? 'bg-gradient-to-r from-red-500 to-rose-600'
              : ''
          ]"
          :style="nightAuditStore.auditRunStatus !== 'failed' ? {
            width: nightAuditStore.progressPercent + '%',
            background: 'linear-gradient(to right, #329ddf, #57cc8a, #8edf72)'
          } : { width: nightAuditStore.progressPercent + '%' }"
        ></div>
      </div>

      <!-- Step Text / Status Label -->
      <div class="mt-6 text-center">
        <!-- Running Step Text -->
        <div
          v-if="nightAuditStore.auditRunStatus === 'running'"
          class="text-[20px] md:text-[23px] font-semibold text-[#272428] tracking-normal font-sans"
        >
          {{ nightAuditStore.currentRunningStepText }}
        </div>

        <!-- Succeeded Finish Text -->
        <div
          v-else-if="nightAuditStore.auditRunStatus === 'succeeded'"
          class="space-y-2"
        >
          <div class="text-[19px] md:text-[22px] font-semibold text-[#272428] tracking-normal font-sans">
            {{ nightAuditStore.finishStepText }}
          </div>
          <div class="text-xs text-gray-500 font-medium">
            Đang hoàn tất và chuyển về trang đăng nhập...
          </div>
        </div>

        <!-- Failed Step Text -->
        <div
          v-else-if="nightAuditStore.auditRunStatus === 'failed'"
          class="text-[19px] md:text-[22px] font-bold text-red-600 flex items-center justify-center gap-2"
        >
          <XCircle class="w-6 h-6 text-red-600 shrink-0" />
          <span>{{ nightAuditStore.currentFailedStepText }}</span>
        </div>
      </div>

      <!-- DETAILED ERROR CARD ON FAILURE -->
      <div
        v-if="nightAuditStore.auditRunStatus === 'failed'"
        class="mt-5 w-full max-w-[820px] p-4 bg-red-50/90 border border-red-200 rounded-xl text-red-700 text-xs shadow-md backdrop-blur-xs"
      >
        <!-- Header & Countdown Badge -->
        <div class="flex items-center justify-between pb-2 border-b border-red-200/80 mb-3">
          <div class="font-bold text-red-800 flex items-center gap-1.5 text-sm">
            <AlertTriangle class="w-4 h-4 text-red-600 shrink-0" />
            <span>Phát hiện lỗi khi sang ngày:</span>
          </div>

          <div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-100 text-red-800 rounded-full text-[11px] font-semibold">
            <Clock class="w-3.5 h-3.5 animate-pulse text-red-600" />
            <span>Tự động quay lại sau {{ nightAuditStore.autoCloseCountdown }}s</span>
          </div>
        </div>

        <!-- Primary Error Message -->
        <div class="text-[12px] text-red-800 font-medium leading-relaxed bg-white/80 p-2.5 rounded-lg border border-red-200">
          {{ nightAuditStore.auditRunError }}
        </div>

        <!-- DETAILED PENDING CHECKINS / CHECKOUTS BREAKDOWN (PRE_CHECK) -->
        <div
          v-if="hasPendingLists"
          class="mt-3 space-y-3 bg-white/70 p-3 rounded-lg border border-red-200/70"
        >
          <!-- Pending Check-ins Table -->
          <div v-if="pendingCheckins.length > 0">
            <div class="font-bold text-red-700 flex items-center gap-1 mb-1.5 text-[11px]">
              <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
              Phòng đến chưa làm thủ tục nhận phòng (Check-in) [{{ pendingCheckins.length }} phòng]:
            </div>
            <div class="overflow-x-auto max-h-36 overflow-y-auto border border-gray-200 rounded">
              <table class="w-full text-[11px] text-left">
                <thead class="bg-red-100/70 text-red-900 font-semibold">
                  <tr>
                    <th class="px-2 py-1">Phòng</th>
                    <th class="px-2 py-1">Mã booking</th>
                    <th class="px-2 py-1">Khách hàng</th>
                    <th class="px-2 py-1 text-center">Ngày đến</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-gray-800">
                  <tr v-for="r in pendingCheckins" :key="r.id">
                    <td class="px-2 py-1 font-bold text-blue-600">{{ r.room_number }}</td>
                    <td class="px-2 py-1 font-medium">{{ r.booking_code || '-' }}</td>
                    <td class="px-2 py-1">{{ r.guest_name || 'Khách' }}</td>
                    <td class="px-2 py-1 text-center">{{ r.arrival_date }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pending Check-outs Table -->
          <div v-if="pendingCheckouts.length > 0">
            <div class="font-bold text-amber-800 flex items-center gap-1 mb-1.5 text-[11px]">
              <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
              Phòng đến hạn chưa làm thủ tục trả phòng (Check-out) [{{ pendingCheckouts.length }} phòng]:
            </div>
            <div class="overflow-x-auto max-h-36 overflow-y-auto border border-gray-200 rounded">
              <table class="w-full text-[11px] text-left">
                <thead class="bg-amber-100/70 text-amber-900 font-semibold">
                  <tr>
                    <th class="px-2 py-1">Phòng</th>
                    <th class="px-2 py-1">Mã booking</th>
                    <th class="px-2 py-1">Khách hàng</th>
                    <th class="px-2 py-1 text-center">Ngày đi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-gray-800">
                  <tr v-for="r in pendingCheckouts" :key="r.id">
                    <td class="px-2 py-1 font-bold text-amber-700">{{ r.room_number }}</td>
                    <td class="px-2 py-1 font-medium">{{ r.booking_code || '-' }}</td>
                    <td class="px-2 py-1">{{ r.guest_name || 'Khách' }}</td>
                    <td class="px-2 py-1 text-center">{{ r.departure_date }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Action Hint Box -->
          <div
            v-if="actionHint"
            class="text-[11px] text-gray-700 bg-amber-50/80 p-2 rounded border border-amber-200 flex items-start gap-1.5 leading-relaxed"
          >
            <Info class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
            <div>
              <span class="font-semibold text-amber-900">Hướng dẫn xử lý: </span>
              <span>{{ actionHint }}</span>
            </div>
          </div>
        </div>

        <!-- Rollback safe notice -->
        <div class="text-[11px] text-red-600 italic mt-2.5 flex items-center gap-1">
          <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
          <span>Hệ thống đã tự động Rollback 100% dữ liệu về trạng thái an toàn trước khi sang ngày.</span>
        </div>

        <!-- Action Buttons -->
        <div class="mt-3.5 pt-2.5 border-t border-red-200 flex items-center justify-between">
          <button
            @click="nightAuditStore.toggleShowDetails = !nightAuditStore.toggleShowDetails"
            class="text-gray-600 hover:text-gray-900 underline text-xs cursor-pointer flex items-center gap-1"
          >
            <span>{{ nightAuditStore.toggleShowDetails ? 'Ẩn bảng 18 bước' : 'Xem nhật ký 18 bước' }}</span>
          </button>

          <button
            @click="nightAuditStore.closeModal"
            class="px-4 py-1.5 bg-[#4a85df] hover:bg-[#3972c7] text-white rounded font-semibold text-xs transition-colors cursor-pointer flex items-center gap-1.5 shadow-xs"
          >
            <span>Đóng & Quay lại ({{ nightAuditStore.autoCloseCountdown }}s)</span>
          </button>
        </div>
      </div>

      <!-- TABLE OF 18 STEPS (TOGGLEABLE) -->
      <div
        v-if="nightAuditStore.auditRunStatus === 'failed' && nightAuditStore.toggleShowDetails"
        class="mt-3 w-full max-w-[820px] max-h-56 overflow-y-auto border border-gray-200 rounded-lg bg-white text-xs shadow-xs p-2 text-left"
      >
        <table class="w-full border-collapse text-[11px]">
          <thead>
            <tr class="border-b text-gray-500 font-semibold bg-gray-50">
              <th class="py-1 px-2 text-left">Bước</th>
              <th class="py-1 px-2 text-left">Tên bước</th>
              <th class="py-1 px-2 text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="step in nightAuditStore.auditSteps" :key="step.code" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="py-1 px-2 text-gray-400 font-mono">#{{ step.order }}</td>
              <td class="py-1 px-2 font-medium text-gray-800">{{ step.nameVi || step.name }}</td>
              <td class="py-1 px-2 text-center">
                <span v-if="step.status === 'succeeded'" class="text-emerald-600 font-bold">Thành công</span>
                <span v-else-if="step.status === 'failed'" class="text-red-600 font-bold">Thất bại</span>
                <span v-else class="text-gray-400">Chờ</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useNightAuditStore } from '@/stores/night-audit-store'
import { XCircle, AlertTriangle, Clock, Info, CheckCircle2 } from '@lucide/vue'

const nightAuditStore = useNightAuditStore()

const pendingCheckins = computed(() => {
  return nightAuditStore.auditErrorDetails?.pending_checkins || []
})

const pendingCheckouts = computed(() => {
  return nightAuditStore.auditErrorDetails?.pending_checkouts || []
})

const hasPendingLists = computed(() => {
  return pendingCheckins.value.length > 0 || pendingCheckouts.value.length > 0
})

const actionHint = computed(() => {
  return nightAuditStore.auditErrorDetails?.hint || ''
})
</script>

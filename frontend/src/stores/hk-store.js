import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http from '@/services/http'

/**
 * Lấy ký hiệu tổng hợp của phòng (hiển thị trong list + worksheet)
 * Kết hợp booking code + hk code
 */
export function getRoomDisplayCode(roomStatusCode, bookingStatus, customSymbols = null) {
  if (!customSymbols) return ''
  const hkSym = customSymbols.hk?.[roomStatusCode]
  const bkSym = customSymbols.booking?.[bookingStatus]
  const codes = []
  if (bkSym?.code) codes.push(bkSym.code)
  if (hkSym?.code && hkSym.code !== bkSym?.code) codes.push(hkSym.code)
  return codes.join(', ')
}

// Màu sắc nhóm phân công
export const GROUP_COLORS = [
  '#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981',
  '#ef4444', '#ec4899', '#06b6d4', '#84cc16',
  '#f97316', '#6366f1', '#14b8a6', '#eab308',
]


// ============================================================
// STORE
// ============================================================
export const useHkStore = defineStore('hk', () => {
  // ---- State ----
  const staff = ref([])          // Danh sách NV (is_hidden = false)
  const staffAll = ref([])       // Kể cả NV ẩn (dùng cho modal quản lý)
  const shifts = ref([])         // Ca làm việc từ shifts table
  const assignment = ref(null)   // Assignment hiện tại { id, work_date, shift_id, groups[] }
  const loading = ref(false)
  const saving = ref(false)

  // Config HK từ DB (ký hiệu phòng + cột mẫu in)
  const hkSymbols = ref(null)        // null = dùng HK_SYMBOLS fallback
  const worksheetColsDB = ref(null)  // null = dùng WORKSHEET_COLS fallback
  const supervisorColsDB = ref(null) // null = dùng SUPERVISOR_COLS fallback

  // ---- Getters ----
  const groups = computed(() => assignment.value?.groups ?? [])

  /** NV chưa được phân công trong ca hiện tại */
  const availableStaff = computed(() => {
    const usedIds = new Set(
      groups.value.flatMap(g => g.staff_list.map(s => s.staff_id))
    )
    return staff.value.filter(s => !usedIds.has(s.id))
  })

  /** Tra cứu nhanh: roomId -> groupId */
  const roomGroupMap = computed(() => {
    const map = {}
    groups.value.forEach(g => {
      g.rooms.forEach(r => { map[r.room_id] = g.id })
    })
    return map
  })

  // ---- Actions ----

  /**
   * Load cấu hình ký hiệu phòng + cột mẫu in từ DB
   * Gọi 1 lần khi app khởi động hoặc vào màn hình HK
   */
  async function loadHkConfig() {
    try {
      const res = await http.get('/hk-config')
      const { symbols, printCols } = res.data

      // Build hkSymbols object giống HK_SYMBOLS để tương thích
      if (symbols?.length) {
        const built = { hk: {}, booking: {}, extra: {} }
        symbols.forEach(s => {
          if (built[s.group]) {
            built[s.group][s.status_key] = { code: s.code, label: s.label, color: s.color }
          }
        })
        hkSymbols.value = built
      }

      if (printCols?.length) {
        worksheetColsDB.value = printCols
          .filter(c => c.template === 'worksheet' && !c.is_fixed)
          .sort((a, b) => a.sort_order - b.sort_order)
          .map(c => ({ label: c.label, width: c.width || '' }))

        supervisorColsDB.value = printCols
          .filter(c => c.template === 'supervisor')
          .sort((a, b) => a.sort_order - b.sort_order)
          .map(c => ({ label: c.label, width: c.width || '', is_fixed: c.is_fixed }))
      }
    } catch (e) {
      console.warn('[hk-store] loadHkConfig fallback to hardcode', e)
    }
  }

  // Computed: trả về config từ DB nếu có, fallback về cấu trúc rỗng khi đang load
  const activeSymbols = computed(() => hkSymbols.value ?? { hk: {}, booking: {}, extra: {} })
  const activeWorksheetCols = computed(() => worksheetColsDB.value ?? [])
  const activeSupervisorCols = computed(() => supervisorColsDB.value ?? [])

  async function loadShifts() {
    try {
      const res = await http.get('/shifts')
      shifts.value = res.data.data || []
    } catch (e) {
      console.error('[hk-store] loadShifts', e)
    }
  }

  async function loadStaff() {
    try {
      const [active, all] = await Promise.all([
        http.get('/hk/staff'),
        http.get('/hk/staff?show_hidden=1'),
      ])
      staff.value = active.data.data || []
      staffAll.value = all.data.data || []
    } catch (e) {
      console.error('[hk-store] loadStaff', e)
    }
  }

  async function loadAssignment(date, shiftId) {
    loading.value = true
    try {
      const res = await http.get('/hk/assignments', { params: { date, shift_id: shiftId } })
      assignment.value = res.data.data || null
    } catch (e) {
      console.error('[hk-store] loadAssignment', e)
      assignment.value = null
    } finally {
      loading.value = false
    }
  }

  /** Đảm bảo có assignment header trước khi tạo group */
  async function ensureAssignment(date, shiftId) {
    // Kiểm tra cả work_date VÀ shift_id để tránh reuse sai assignment khi đổi ngày
    if (
      assignment.value?.shift_id == shiftId &&
      assignment.value?.work_date === date
    ) return assignment.value
    const res = await http.post('/hk/assignments', { work_date: date, shift_id: shiftId })
    assignment.value = res.data.data
    return assignment.value
  }

  /**
   * Phân công: tạo nhóm mới với NV + phòng + snapshot tình trạng
   * roomSnapshots: { [roomId]: { room_status_snapshot, booking_status_snapshot } }
   */
  async function assignRooms({ date, shiftId, staffIds, roomIds, roomSnapshots = {} }) {
    saving.value = true
    try {
      const a = await ensureAssignment(date, shiftId)
      const res = await http.post(`/hk/assignments/${a.id}/groups`, {
        staff_ids:      staffIds,
        room_ids:       roomIds,
        room_snapshots: roomSnapshots,
        color:          GROUP_COLORS[groups.value.length % GROUP_COLORS.length],
      })
      // Reload để đảm bảo đồng bộ (nhóm cũ có thể mất phòng)
      await loadAssignment(date, shiftId)
      return res.data.data
    } finally {
      saving.value = false
    }
  }

  /** Kéo thả: chuyển phòng sang nhóm khác */
  async function moveRoomToGroup({ date, shiftId, groupId, roomId, roomSnapshot = {} }) {
    saving.value = true
    try {
      await http.post(`/hk/assignments/groups/${groupId}/rooms`, {
        room_ids: [roomId],
        room_snapshots: { [roomId]: roomSnapshot },
      })
      await loadAssignment(date, shiftId)
    } finally {
      saving.value = false
    }
  }

  /** Xóa phòng khỏi nhóm */
  async function removeRoomFromGroup({ date, shiftId, groupId, roomId }) {
    saving.value = true
    try {
      await http.delete(`/hk/assignments/groups/${groupId}/rooms/${roomId}`)
      await loadAssignment(date, shiftId)
    } finally {
      saving.value = false
    }
  }

  /** Xóa nhóm */
  async function removeGroup({ date, shiftId, groupId }) {
    saving.value = true
    try {
      await http.delete(`/hk/assignments/groups/${groupId}`)
      await loadAssignment(date, shiftId)
    } finally {
      saving.value = false
    }
  }

  /** Cập nhật danh sách NV của nhóm */
  async function updateGroupStaff({ date, shiftId, groupId, staffIds }) {
    saving.value = true
    try {
      await http.put(`/hk/assignments/groups/${groupId}`, { staff_ids: staffIds })
      await loadAssignment(date, shiftId)
    } finally {
      saving.value = false
    }
  }

  // ---- Staff management ----
  async function addStaff(name) {
    const res = await http.post('/hk/staff', { name })
    await loadStaff()
    return res.data.data
  }

  async function toggleHideStaff(staffId, isHidden) {
    await http.put(`/hk/staff/${staffId}`, { is_hidden: isHidden })
    await loadStaff()
  }

  async function deleteStaff(staffId) {
    await http.delete(`/hk/staff/${staffId}`)
    await loadStaff()
  }

  return {
    // state
    staff, staffAll, shifts, assignment, loading, saving,
    hkSymbols, worksheetColsDB, supervisorColsDB,
    // getters / computed
    groups, availableStaff, roomGroupMap,
    activeSymbols, activeWorksheetCols, activeSupervisorCols,
    // actions
    loadHkConfig, loadShifts, loadStaff, loadAssignment,
    assignRooms, moveRoomToGroup, removeRoomFromGroup,
    removeGroup, updateGroupStaff,
    addStaff, toggleHideStaff, deleteStaff,
  }
})

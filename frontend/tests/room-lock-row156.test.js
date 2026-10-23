import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { ref, computed } from 'vue'

const source = readFileSync(new URL('../src/pages/reservation/LockRoomPage.vue', import.meta.url), 'utf8')

function editor({ batch = false, lockType = 'OOO', start = '2026-09-24', originalStart = '2026-09-25' } = {}) {
  const calls = [], messages = [], events = []
  const form = { start_date: start, start_time: '00:00', end_date: '2026-09-27', end_time: '23:59', reason: 'Bảo trì', maintenance_percent: 35 }
  const bulkForm = ref(form), editingLockId = ref(156), systemDate = ref('2026-09-24')
  const activeSource = source.slice(source.indexOf('const isEditingActiveLock = computed('), source.indexOf('function stepMaintenancePercent('))
  const active = new Function('computed', 'editingLockId', 'bulkForm', 'systemDate', 'getTodayString', `${activeSource}; return isEditingActiveLock`)(computed, editingLockId, bulkForm, systemDate, () => '2026-10-23')
  const bindings = {
    modalSelectedRoomNumbers: ref(['101']), bulkForm, editingLockId, systemDate,
    isEditingActiveLock: active, getTodayString: () => '2026-10-23', bulkLockType: ref(lockType),
    isBulkModalOpen: ref(true), selectedRowKeys: ref(['156']), activeHistoryRoom: ref(null),
    editedLocks: ref({ 156: { ...form, lock_id: 156, room_number: '101', original_start_date: originalStart } }),
    savingBatch: ref(false), isBatchEditing: ref(true),
    uiStore: { showToast: (...args) => messages.push(args), confirm: async () => false },
    http: {
      put: async (url, payload) => { calls.push({ url, payload }); return { data: { success: true } } },
      post: async (url, payload) => { calls.push({ url, payload }); return { data: { success: true } } },
    },
    fetchRooms: () => events.push('reload'), showHistory: () => {}, bc: { postMessage: v => events.push(v) },
  }
  const startAt = source.indexOf(batch ? 'const submitBatchSave = ' : 'const submitBulkLock = ')
  assert.ok(startAt >= 0)
  const endAt = source.indexOf(batch ? '// Toggle active row menu' : '// ==================== BATCH / INLINE EDITING', startAt)
  assert.ok(endAt > startAt)
  const body = source.slice(startAt, endAt)
  const save = new Function(...Object.keys(bindings), `${body}; return ${batch ? 'submitBatchSave' : 'submitBulkLock'}`)(...Object.values(bindings))
  return { save, calls, messages, events }
}

for (const batch of [false, true]) {
  test(`156: ${batch ? 'batch' : 'single'} edit sends system-date start and refreshes room consumers`, async () => {
    for (const lockType of ['OOO', 'OOS']) {
      const e = editor({ batch, lockType })
      await e.save()
      assert.equal(e.calls.length, 1)
      const payload = batch ? e.calls[0].payload.locks[0] : e.calls[0].payload
      assert.equal(payload.start_date, '2026-09-24 00:00:00')
      assert.equal(payload.maintenance_percent, 35)
      if (!batch) assert.equal(payload.lock_type, lockType)
      assert.deepEqual(e.events, ['reload', 'rooms-updated'])
    }
  })
}

test('156: batch editor rejects moving a future lock before the system date', async () => {
  const e = editor({ batch: true, start: '2026-09-23' })
  await e.save()
  assert.equal(e.calls.length, 0)
  assert.match(e.messages[0][0], /Ngày bắt đầu không được nhỏ hơn ngày hệ thống/)
})

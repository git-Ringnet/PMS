import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
const start = source.indexOf('          for (const r of targetList)', source.indexOf("actionName === 'GIAO PHÒNG'"))
const end = source.indexOf('          await loadBookings()', start)
assert.ok(start >= 0 && end > start)

async function checkIn({ accept = true, reject = false } = {}) {
  const calls = [], prompts = []
  const targetList = [{ bookingRoomId: 'G211', roomNumber: '211' }]
  const bindings = {
    targetList, tab: { dbId: 211 },
    checkInRoom: async (...args) => {
      calls.push(args)
      if (reject) throw { response: { data: { message: 'Phòng 211 chưa dọn. Không thể nhận phòng.' } } }
      return { data: calls.length === 1 ? { success: true, needs_confirmation: true, message: 'Phòng chờ kiểm tra' } : { success: true } }
    },
    uiStore: { confirm: async options => { prompts.push(options); return accept } },
    console: { error() {} },
  }
  const run = new Function(...Object.keys(bindings), `return (async () => {
    let successCount = 0, failCount = 0; const failMap = new Map();
    ${source.slice(start, end)}
    return { successCount, failCount, failMap };
  })()`)
  return { result: await run(...Object.values(bindings)), calls, prompts, targetList }
}

test('211: accepting pending-inspection confirmation resubmits confirmed=true and counts one success', async () => {
  const e = await checkIn()
  assert.equal(e.prompts.length, 1)
  assert.equal(e.calls.length, 2)
  assert.deepEqual(e.calls[1][2], { confirmed: true })
  assert.equal(e.result.successCount, 1)
  assert.equal(e.result.failCount, 0)
})

test('211: skipping pending-inspection room makes no second API call and counts no success', async () => {
  const e = await checkIn({ accept: false })
  assert.equal(e.calls.length, 1)
  assert.equal(e.result.successCount, 0)
  assert.equal(e.result.failCount, 1)
  assert.match([...e.result.failMap.keys()][0], /bỏ qua/)
  assert.equal(e.targetList[0].roomStatus, undefined)
})

test('211: API rejection for dirty room is reported without success or confirmation', async () => {
  const e = await checkIn({ reject: true })
  assert.equal(e.calls.length, 1)
  assert.equal(e.prompts.length, 0)
  assert.equal(e.result.successCount, 0)
  assert.equal(e.result.failCount, 1)
  assert.match([...e.result.failMap.keys()][0], /chưa dọn/)
})

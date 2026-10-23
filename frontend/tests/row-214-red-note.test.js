import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { ref } from 'vue'

const source = readFileSync(new URL('../src/components/BookingDetailModal.vue', import.meta.url), 'utf8')
const start = source.indexOf('async function onExtraBedSaved(data)')
const end = source.indexOf('async function loadRoomSpecialRequests()', start)
assert.ok(start >= 0 && end > start)

function editor({ failDate, failHeader = false } = {}) {
  const calls = [], messages = [], events = []
  const bindings = {
    pricingInfo: ref({}), props: { room: {} }, roomServices: ref([]),
    bookingRoomId: ref('G214'), bookingId: ref(214),
    formatNumber: String, formatDateForInput: value => String(value).slice(0, 10),
    uiStore: { showToast: (...args) => messages.push(args) },
    http: {
      put: async (url, data) => {
        calls.push({ url, data })
        if (failHeader) throw new Error('Header rejected')
      },
      post: async (url, data) => {
        calls.push({ url, data })
        if (data.service_date === failDate) throw new Error('Night rejected')
      },
    },
    loadRoomServices: async () => events.push('reload'), notifyChanges: () => events.push('notify'),
    console: { error() {} },
  }
  const save = new Function(...Object.keys(bindings), `${source.slice(start, end)}; return onExtraBedSaved`)(...Object.values(bindings))
  return { save, calls, messages, events }
}

const data = () => ({ quantity: 1, rate: 300000, dailyRates: [
  { dateStr: '2026-08-09', quantity: 1, rate: 300000, isRoom: true },
  { dateStr: '2026-08-10', quantity: 1, rate: 500000, isRoom: true },
  { dateStr: '2026-08-11', quantity: 0, rate: 0, isRoom: false },
] })

test('214: actual modal handler saves distinct nightly rates and zero quantity, then reloads', async () => {
  const e = editor()
  await e.save(data())
  assert.equal(e.calls.length, 4)
  assert.deepEqual(e.calls.slice(1).map(c => [c.data.service_date, c.data.quantity, c.data.rate]), [
    ['2026-08-09', 1, 300000], ['2026-08-10', 1, 500000], ['2026-08-11', 0, 0],
  ])
  assert.deepEqual(e.events, ['reload', 'notify'])
  assert.equal(e.messages.at(-1)[1], 'success')
})

test('214: failed night names its date and never reports overall success', async () => {
  const e = editor({ failDate: '2026-08-10' })
  await e.save(data())
  assert.equal(e.calls.length, 4)
  assert.match(e.messages.at(-1)[0], /2026-08-10.*Night rejected/)
  assert.equal(e.messages.at(-1)[1], 'error')
  assert.ok(!e.messages.some(m => m[1] === 'success'))
})

test('214: posted or past nights are skipped while editable zero quantity is persisted', async () => {
  const e = editor(), input = data()
  input.dailyRates[0].isPast = true
  input.dailyRates[1].isLocked = true
  await e.save(input)
  assert.equal(e.calls.length, 2)
  assert.equal(e.calls[1].data.service_date, '2026-08-11')
  assert.equal(e.calls[1].data.quantity, 0)
})

test('214: failed room header prevents nightly writes and success notification', async () => {
  const e = editor({ failHeader: true })
  await e.save(data())
  assert.equal(e.calls.length, 1)
  assert.match(e.messages.at(-1)[0], /Header rejected/)
  assert.ok(!e.messages.some(m => m[1] === 'success'))
})

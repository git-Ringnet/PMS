import test from 'node:test'
import assert from 'node:assert/strict'

import { OVER_CANCELLED_MESSAGE, saveWithOverConfirm } from '../src/utils/booking-over-confirm.js'

const overResponse = () => Object.assign(new Error('409'), {
  response: { status: 409, data: { needs_over_confirm: true, message: 'Loại phòng đang bị over bạn có muốn tiếp tục' } },
})

test('row 446: saving without over returns the first response and never asks', async () => {
  const calls = []
  let asked = false
  const res = await saveWithOverConfirm(
    async extra => { calls.push(extra); return { data: { success: true } } },
    async () => { asked = true; return true },
  )
  assert.deepEqual(res, { data: { success: true } })
  assert.deepEqual(calls, [{}])
  assert.equal(asked, false)
})

test('row 446: answering "Có" resends the save with force_over', async () => {
  const calls = []
  let shownMessage = ''
  const res = await saveWithOverConfirm(
    async extra => {
      calls.push(extra)
      if (!extra.force_over) throw overResponse()
      return { data: { success: true } }
    },
    async message => { shownMessage = message; return true },
  )
  assert.equal(shownMessage, 'Loại phòng đang bị over bạn có muốn tiếp tục')
  assert.deepEqual(calls, [{}, { force_over: true }])
  assert.deepEqual(res, { data: { success: true } })
})

test('row 446: answering "Không" does not resend and reports a cancellation', async () => {
  const calls = []
  await assert.rejects(
    saveWithOverConfirm(
      async extra => { calls.push(extra); throw overResponse() },
      async () => false,
    ),
    err => err.overCancelled === true && err.response.data.message === OVER_CANCELLED_MESSAGE,
  )
  assert.deepEqual(calls, [{}])
})

test('row 446: a blocked over (AllowOverRoomTypeRoomKind = 0) is passed through without asking', async () => {
  let asked = false
  const blocked = Object.assign(new Error('422'), {
    response: { status: 422, data: { blocked_by_over: true, message: 'Loại phòng đang bị over không thể cập nhật thông tin' } },
  })
  await assert.rejects(
    saveWithOverConfirm(async () => { throw blocked }, async () => { asked = true; return true }),
    err => err === blocked,
  )
  assert.equal(asked, false)
})

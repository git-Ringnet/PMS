import assert from 'node:assert/strict'
import { test } from 'node:test'
import { isServerAuditResponse, resolveAuditOutcome } from './night-audit-outcome.js'

test('sang ngày: timeout/mất mạng/proxy 504 chưa phải kết quả của server', () => {
  assert.equal(isServerAuditResponse({ code: 'ECONNABORTED', message: 'timeout of 15000ms exceeded' }), false)
  assert.equal(isServerAuditResponse({ message: 'Network Error' }), false)
  assert.equal(isServerAuditResponse({ response: { status: 504, data: '<html>Gateway Timeout</html>' } }), false)
})

test('sang ngày: lỗi do API trả về (success=false) là kết quả thật', () => {
  const err = { response: { status: 500, data: { success: false, failed_step: 'POST_BILLS', rollback_done: true } } }
  assert.equal(isServerAuditResponse(err), true)
  assert.equal(isServerAuditResponse({ response: { status: 409, data: { success: false, message: 'Đang chạy' } } }), true)
})

test('sang ngày: server còn chạy thì tiếp tục chờ', () => {
  assert.equal(resolveAuditOutcome({ is_running: true, system_date: '2026-09-03' }, '2026-09-03'), 'running')
  assert.equal(resolveAuditOutcome(null, '2026-09-03'), 'running')
})

test('sang ngày: ngày hệ thống đã đổi là thành công (trường hợp khách gặp)', () => {
  const status = { is_running: false, system_date: '2026-09-04', latest_run: { status: 'succeeded' } }
  assert.equal(resolveAuditOutcome(status, '2026-09-03'), 'succeeded')
})

test('sang ngày: ngày hệ thống không đổi là thất bại', () => {
  const status = { is_running: false, system_date: '2026-09-03', latest_run: { status: 'failed' } }
  assert.equal(resolveAuditOutcome(status, '2026-09-03'), 'failed')
})

test('sang ngày: không lấy được ngày lúc bấm thì dựa vào lần chạy gần nhất', () => {
  assert.equal(resolveAuditOutcome({ is_running: false, latest_run: { status: 'succeeded' } }, null), 'succeeded')
  assert.equal(resolveAuditOutcome({ is_running: false, latest_run: { status: 'failed' } }, null), 'failed')
})

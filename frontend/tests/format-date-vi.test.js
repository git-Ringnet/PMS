import test from 'node:test'
import assert from 'node:assert/strict'
import { formatDateVi } from '../src/utils/format-date-vi.js'

test('row 487: ISO có giờ không còn bị cắt thô', () => {
  const out = formatDateVi('2026-09-02T17:00:00.000000Z')
  assert.match(out, /^\d{2}\/09\/2026$/)
  assert.ok(!out.includes('T'))
})

test('row 487: các định dạng ngày thường giữ nguyên', () => {
  assert.equal(formatDateVi('2026-09-03'), '03/09/2026')
  assert.equal(formatDateVi('03/09/2026'), '03/09/2026')
  assert.equal(formatDateVi(''), '')
})

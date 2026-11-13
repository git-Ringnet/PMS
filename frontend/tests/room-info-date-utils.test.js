import test from 'node:test'
import assert from 'node:assert/strict'
import {
  formatShortDate,
  maskDateInput,
  parseDmyInput,
  isLeapYear,
  getMaxDaysInMonth,
  normalizeIsoDateOnly,
  formatDateDisplayYY,
} from '../src/pages/reservation/components/room-info-date-utils.js'

test('adds separators while entering an eight-digit date', () => {
  assert.equal(maskDateInput('18091996'), '18/09/1996')
})

test('converts an eight-digit date to the API format', () => {
  assert.equal(parseDmyInput('18/09/1996'), '1996-09-18')
})

test('rejects the six-digit ddmmyy format (requires full 8 digits)', () => {
  assert.equal(parseDmyInput('180926'), null)
})

test('rejects an impossible calendar date', () => {
  assert.equal(parseDmyInput('31022026'), null)
})

test('shows saved dates in dd/mm/yyyy format', () => {
  assert.equal(formatShortDate('1996-09-18'), '18/09/1996')
})

test('row 110: does not shift year digits when editing month in an existing date', () => {
  // Existing date 14/05/1994 -> user deletes 5 in month
  assert.equal(maskDateInput('14/0/1994', true), '14/0/1994')
})

test('row 110: does not shift month digits when editing day in an existing date', () => {
  // Existing date 14/05/1994 -> user deletes 4 in day
  assert.equal(maskDateInput('1/05/1994', true), '1/05/1994')
})

test('row 110: prevents 5-digit year when typing extra digits at the end', () => {
  // Existing date 04/01/1993 -> user types extra 9 at the end
  assert.equal(maskDateInput('04/01/19939', false), '04/01/1993')
})

test('row 110: allows editing year without affecting day or month', () => {
  assert.equal(maskDateInput('14/05/199', true), '14/05/199')
  assert.equal(maskDateInput('14/05/1995', false), '14/05/1995')
})

test('row 110: handles deleting slashes gracefully without corrupting segments', () => {
  assert.equal(maskDateInput('1405/1994', true), '14/05/1994')
  assert.equal(maskDateInput('14/051994', true), '14/05/1994')
})

test('row 110 enhancement: typing 1 in month does not prematurely auto-pad to 01', () => {
  // User had 14/05/1994, deletes month and types 1: must remain 14/1/1994 to allow typing 10, 11, 12
  assert.equal(maskDateInput('14/1/1994', false), '14/1/1994')
  assert.equal(maskDateInput('14/10/1994', false), '14/10/1994')
  assert.equal(maskDateInput('14/11/1994', false), '14/11/1994')
  assert.equal(maskDateInput('14/12/1994', false), '14/12/1994')
})

test('row 110 enhancement: blur/enter formats 14/1/1994 to 14/01/1994 via parse and format', () => {
  const parsed = parseDmyInput('14/1/1994')
  assert.equal(parsed, '1994-01-14')
  assert.equal(formatShortDate(parsed), '14/01/1994')
})

test('row 110 enhancement: clamps day according to month limit (30 days for April)', () => {
  assert.equal(maskDateInput('31/04/2026', false), '30/04/2026')
})

test('row 110 enhancement: clamps day for February in leap year vs non-leap year', () => {
  // 2024 is a leap year (max 29)
  assert.equal(isLeapYear(2024), true)
  assert.equal(getMaxDaysInMonth(2, 2024), 29)
  assert.equal(maskDateInput('31/02/2024', false), '29/02/2024')
  assert.equal(maskDateInput('29/02/2024', false), '29/02/2024')

  // 2025 is not a leap year (max 28)
  assert.equal(isLeapYear(2025), false)
  assert.equal(getMaxDaysInMonth(2, 2025), 28)
  assert.equal(maskDateInput('30/02/2025', false), '28/02/2025')
  assert.equal(maskDateInput('29/02/2025', false), '28/02/2025')
})

test('row 110: supports two-digit year when fourDigitYear is false', () => {
  assert.equal(maskDateInput('180926', false, false), '18/09/26')
  assert.equal(maskDateInput('14/05/94', true, false), '14/05/94')
  assert.equal(maskDateInput('14/05/949', false, false), '14/05/94')
})

test('day editing: typing a single digit (0, 1, 2, 3) does not jump to 0X immediately', () => {
  // Existing date 14/05/1994 -> user selects 14 and types 1: must remain 1/05/1994 to allow typing 10..19
  assert.equal(maskDateInput('1/05/1994', false), '1/05/1994')
  assert.equal(maskDateInput('18/05/1994', false), '18/05/1994')

  // User selects 14 and types 2: must remain 2/05/1994 to allow typing 20..29
  assert.equal(maskDateInput('2/05/1994', false), '2/05/1994')
  assert.equal(maskDateInput('25/05/1994', false), '25/05/1994')

  // User selects 14 and types 3: must remain 3/05/1994 to allow typing 30..31
  assert.equal(maskDateInput('3/05/1994', false), '3/05/1994')
  assert.equal(maskDateInput('31/05/1994', false), '31/05/1994')

  // User selects 14 and types 0: must remain 0/05/1994 to allow typing 01..09
  assert.equal(maskDateInput('0/05/1994', false), '0/05/1994')
  assert.equal(maskDateInput('08/05/1994', false), '08/05/1994')
})

test('day editing: explicitly typing 5/ or 1/ from scratch auto-pads to 05/ or 01/', () => {
  assert.equal(maskDateInput('5/', false), '05/')
  assert.equal(maskDateInput('1/', false), '01/')
})

test('day editing: blur or enter converts single digit day e.g. 1/05/1994 to 01/05/1994', () => {
  const parsed1 = parseDmyInput('1/05/1994')
  assert.equal(parsed1, '1994-05-01')
  assert.equal(formatShortDate(parsed1), '01/05/1994')

  const parsed5 = parseDmyInput('5/05/1994')
  assert.equal(parsed5, '1994-05-05')
  assert.equal(formatShortDate(parsed5), '05/05/1994')
})

test('Row 33: formatDateDisplayYY correctly formats ISO timestamps and standard date strings', () => {
  // ISO timestamp from legacy/API
  assert.equal(formatDateDisplayYY('1998-02-20T00:00:00.000000Z'), '20/02/1998')
  assert.equal(formatDateDisplayYY('1998-02-20'), '20/02/1998')
  assert.equal(formatDateDisplayYY('20/02/1998'), '20/02/1998')
  assert.equal(formatDateDisplayYY('20-02-1998'), '20/02/1998')
  assert.equal(formatDateDisplayYY('20021998'), '20/02/1998')
  // Never returns corrupted string like 19T17:00:00.000000Z/02/1998
  const res = formatDateDisplayYY('1998-02-19T17:00:00.000000Z')
  assert.match(res, /^\d{2}\/\d{2}\/\d{4}$/)
  assert.ok(!res.includes('T'))
  assert.ok(!res.includes('Z'))
})

test('Row 33: normalizeIsoDateOnly converts any valid date string to YYYY-MM-DD', () => {
  assert.equal(normalizeIsoDateOnly('1998-02-20'), '1998-02-20')
  assert.equal(normalizeIsoDateOnly('20/02/1998'), '1998-02-20')
  assert.equal(normalizeIsoDateOnly('20-02-1998'), '1998-02-20')
  assert.equal(normalizeIsoDateOnly('20021998'), '1998-02-20')
  assert.equal(normalizeIsoDateOnly(''), '')
  assert.equal(normalizeIsoDateOnly(null), '')
})

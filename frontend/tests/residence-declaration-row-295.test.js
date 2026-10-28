import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 295 (note 27/10): bộ lọc Người Nước Ngoài / Người Việt Nam và cột Số giấy tờ / Hộ chiếu.
const source = readFileSync(new URL('../src/pages/reservation/ResidenceDeclarationPage.vue', import.meta.url), 'utf8')

function extractFunction(name) {
  const start = source.indexOf(`function ${name}(`)
  const end = source.indexOf('\n}', start)
  assert.ok(start >= 0 && end > start, `Không tìm thấy hàm ${name}`)
  return new Function(`${source.slice(start, end + 2)}; return ${name}`)()
}

const getDocumentNumber = extractFunction('getDocumentNumber')
const getSeparatePassportNumber = extractFunction('getSeparatePassportNumber')

test('row 295: passport number of a foreign guest stays in Số giấy tờ, not Hộ chiếu', () => {
  // Loại giấy tờ Passport: backend lưu cùng số vào id_number và passport_number.
  assert.equal(getDocumentNumber('C1234567', 'C1234567'), 'C1234567')
  assert.equal(getSeparatePassportNumber('C1234567', 'C1234567'), '')
})

test('row 295: separate document and passport numbers show in their own columns', () => {
  assert.equal(getDocumentNumber('079123456789', 'B7654321'), '079123456789')
  assert.equal(getSeparatePassportNumber('079123456789', 'B7654321'), 'B7654321')
  assert.equal(getDocumentNumber('', 'B7654321'), 'B7654321')
  assert.equal(getSeparatePassportNumber('', 'B7654321'), '')
})

test('row 295: table cell for Hộ chiếu uses the separated value', () => {
  assert.match(source, /col-hochieu[^\n]*\{\{ r\.hoChieuHienThi \}\}/)
})

test('row 295: filter has Người Nước Ngoài and Người Việt Nam, no VAT options', () => {
  assert.match(source, /v-model="filters\.foreigner" \/> Người Nước Ngoài/)
  assert.match(source, /v-model="filters\.vietnamese" \/> Người Việt Nam/)
  assert.doesNotMatch(source, /filters\.(vat|noVat)/)
  assert.match(source, /filters\.value\.foreigner !== filters\.value\.vietnamese/)
})

test('row 295: CSV export uses the same Số giấy tờ / Hộ chiếu values as the table', () => {
  const csv = source.slice(source.indexOf('function exportToCsv('), source.indexOf('function exportToXml('))
  assert.match(csv, /r\.soGiayToHienThi \|\| '',\s*r\.hoChieuHienThi \|\| ''/)
})

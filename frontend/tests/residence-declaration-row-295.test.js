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

const compareNatural = extractFunction('compareNatural')

test('row 295 (note 27/10): room numbers sort in natural order, ascending and descending', () => {
  const rooms = ['1012', '101', '102', '201', '1101']
  assert.deepEqual([...rooms].sort(compareNatural), ['101', '102', '201', '1012', '1101'])
  assert.deepEqual([...rooms].sort((a, b) => compareNatural(a, b) * -1), ['1101', '1012', '201', '102', '101'])
  assert.deepEqual(['GAL10', 'GAL9', 'GAL1'].sort(compareNatural), ['GAL1', 'GAL9', 'GAL10'])
})

test('row 295 (note 27/10): same-day rooms checked out on the viewed day are listed', () => {
  assert.match(source, /status === 2 && arrTime !== null && arrTime === depTime/)
  assert.match(source, /return arrTime === targetTime/)
})

// ---------------------------------------------------------------- Dòng 298, 299 (note 27/10)
const resolveIdTypeName = extractFunction('resolveIdTypeName')
const formatResidenceLabel = extractFunction('formatResidenceLabel')
const idTypes = {
  CCCD: { code: 'CCCD', name: 'Căn cước công dân' },
  'CĂN CƯỚC CÔNG DÂN': { code: 'CCCD', name: 'Căn cước công dân' },
  GPLX: { code: 'GPLX', name: 'Giấy phép lái xe' },
}

test('row 298: adult and child export the same document type name (Căn cước công dân, not CCCD)', () => {
  assert.equal(resolveIdTypeName('CCCD', false, idTypes), 'Căn cước công dân') // người lớn
  assert.equal(resolveIdTypeName('CCCD', false, idTypes), 'Căn cước công dân') // trẻ em cùng hàm
  assert.equal(resolveIdTypeName('cccd', false, idTypes), 'Căn cước công dân')
})

test('row 298: passport, other document types and missing data', () => {
  assert.equal(resolveIdTypeName('CCCD', true, idTypes), 'Hộ chiếu')
  assert.equal(resolveIdTypeName('GPLX', false, idTypes), 'Giấy phép lái xe')
  assert.equal(resolveIdTypeName('', false, idTypes), 'Căn cước công dân')
  assert.equal(resolveIdTypeName('KHONG_CO', false, idTypes), 'Căn cước công dân')
  assert.equal(resolveIdTypeName('CCCD', false, undefined), 'Căn cước công dân')
})

test('row 298: Khánh Hòa Excel uses the shared document type helper', () => {
  const excel = source.slice(source.indexOf('function exportToExcel('), source.indexOf('function formatIsoDate('))
  assert.match(excel, /getIdTypeDisplayName\(r\)/)
  assert.doesNotMatch(excel, /r\.loaiGiayTo/)
  assert.doesNotMatch(excel, /GHI CHÚ CHUYỂN PHÒNG|transferNote/)
})

test('row 299: Nơi cư trú hiện nay is "code - name_new_form"', () => {
  assert.equal(formatResidenceLabel({ code: '1', name_new_form: 'Thường trú' }), '1 - Thường trú')
  assert.equal(formatResidenceLabel({ code: '2', name_new_form: 'Tạm trú' }), '2 - Tạm trú')
  assert.equal(formatResidenceLabel({ code: '3', name_new_form: 'Khác' }), '3 - Khác')
  assert.equal(formatResidenceLabel({ name_new_form: 'Khác' }), 'Khác')
  assert.equal(formatResidenceLabel(null), '')
  assert.equal(formatResidenceLabel({ code: '1' }), '')
})

test('row 299: Vietnamese template uses the code - name_new_form label', () => {
  const vn = source.slice(source.indexOf('function exportToExcelVn('), source.indexOf('function exportToExcelVn(') + 6000)
  assert.match(vn, /formatResidenceLabel\(getResidenceType\(r\.residenceType\)\)/)
  assert.match(vn, /DANH SÁCH THÔNG BÁO LƯU TRÚ CHO CÔNG DÂN VIỆT NAM/)
})

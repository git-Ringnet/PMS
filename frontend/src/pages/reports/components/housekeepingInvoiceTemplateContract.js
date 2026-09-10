/**
 * Contract checks for the LA/BR/MB housekeeping invoice reference layouts.
 *
 * This module is intentionally pure and is not imported by the shared report
 * page or TemplateEditorModal. It gives the integration owner a cheap check
 * that a reference provider still matches the editor's block vocabulary.
 */

export const HOUSEKEEPING_INVOICE_PARAMETERS = Object.freeze([
  'p_from_date',
  'p_to_date',
  'p_shift',
  'p_department',
  'p_user',
  'p_view_type',
  'p_order_by',
  'p_order_type',
  'p_show_details',
])

export const HOUSEKEEPING_INVOICE_VIEW_TYPES = Object.freeze(['all', 'post', 'correct', 'free'])
export const HOUSEKEEPING_INVOICE_ORDER_TYPES = Object.freeze(['ASC', 'DESC'])

const INVOICE_FIELDS = Object.freeze([
  'row.STT',
  'row.BookingId',
  'row.Room',
  'row.Guest',
  'row.DescriptionServive',
  'row.Product',
  'row.TotalAmount',
  'row.DiscountAmount',
  'row.NetAmount',
  'row.PaymentID',
  'row.BillNote',
  'row.Username',
  'row.Ca',
])

const NUMBER_FIELDS = new Set(['row.TotalAmount', 'row.DiscountAmount', 'row.NetAmount'])

const error = (path, message) => `${path}: ${message}`

/**
 * Return all contract violations. An empty array means the blocks are safe to
 * normalize and compile with the current TemplateEditorModal vocabulary.
 */
export function validateHousekeepingInvoiceBlocks(blocks) {
  const errors = []
  const detail = blocks?.detail
  if (!Array.isArray(detail)) return [error('detail', 'must be an array')]

  const tables = detail.filter((block) => block?.type === 'table')
  const invoice = tables.find((block) => block.dataSource === 'rows')
  const product = tables.find((block) => block.dataSource === 'product_summary')

  if (!invoice) errors.push(error('detail', 'missing dynamic rows table'))
  if (!product) errors.push(error('detail', 'missing dynamic product_summary table'))

  if (invoice) {
    if (invoice.tableType !== 'dynamic') errors.push(error('detail.rows.tableType', 'must be dynamic'))
    if (!Array.isArray(invoice.columns) || invoice.columns.length !== INVOICE_FIELDS.length) {
      errors.push(error('detail.rows.columns', `must contain ${INVOICE_FIELDS.length} columns`))
    } else {
      invoice.columns.forEach((column, index) => {
        if (column.value !== INVOICE_FIELDS[index]) {
          errors.push(error(`detail.rows.columns[${index}].value`, `expected ${INVOICE_FIELDS[index]}`))
        }
        const expectedFormat = NUMBER_FIELDS.has(INVOICE_FIELDS[index]) ? 'number' : ''
        if ((column.format || '') !== expectedFormat) {
          errors.push(error(`detail.rows.columns[${index}].format`, `${INVOICE_FIELDS[index]} expected ${expectedFormat || 'empty'}`))
        }
      })
    }
    const groups = Array.isArray(invoice.groups) ? invoice.groups : []
    if (!groups.some((group) => group?.field === 'DateGroup')) {
      errors.push(error('detail.rows.groups', 'must include DateGroup'))
    }
    const totalRows = Array.isArray(invoice.customRows) ? invoice.customRows : []
    const totalRow = totalRows.find((row) => row?.id === 'laundry_invoice_totals' || row?.className === 'invoice-total-row')
    if (!totalRow) errors.push(error('detail.rows.customRows', 'must include the report total row'))
    else {
      const bindings = (totalRow.cells || []).map((cell) => cell.binding).filter(Boolean)
      for (const field of ['totals.TotalAmount', 'totals.DiscountAmount', 'totals.NetAmount']) {
        if (!bindings.includes(field)) errors.push(error('detail.rows.customRows', `missing ${field}`))
      }
    }
  }

  if (product) {
    if (product.tableType !== 'dynamic') errors.push(error('detail.product_summary.tableType', 'must be dynamic'))
    const values = (product.columns || []).map((column) => column.value)
    if (values.join('|') !== 'item.Product|item.Quantity|item.TotalAmount') {
      errors.push(error('detail.product_summary.columns', 'must bind Product, Quantity, TotalAmount'))
    }
  }
  return errors
}

export function validateHousekeepingInvoiceParameters(parameters) {
  const errors = []
  for (const key of HOUSEKEEPING_INVOICE_PARAMETERS) {
    if (!Object.prototype.hasOwnProperty.call(parameters || {}, key)) errors.push(error(`parameters.${key}`, 'is required'))
  }
  if (parameters && parameters.p_view_type !== undefined && !HOUSEKEEPING_INVOICE_VIEW_TYPES.includes(String(parameters.p_view_type).toLowerCase())) {
    errors.push(error('parameters.p_view_type', 'must be all, post, correct, or free'))
  }
  if (parameters && parameters.p_order_type !== undefined && !HOUSEKEEPING_INVOICE_ORDER_TYPES.includes(String(parameters.p_order_type).toUpperCase())) {
    errors.push(error('parameters.p_order_type', 'must be ASC or DESC'))
  }
  return errors
}

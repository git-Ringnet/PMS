import test from 'node:test'
import assert from 'node:assert/strict'

import {
  contentForTextStyle,
  mergeConfiguredStyles,
  normalizeElementTextStyle,
  scopedBlockTextStyleCss,
  styleObjectToCss,
} from '../src/utils/report-designer-styles.js'

test('empty cell values do not override configured row styles', () => {
  const row = normalizeElementTextStyle({ fontWeight: 'normal', fontSize: '10px' })
  const cell = normalizeElementTextStyle({ color: '#123456' })

  assert.deepEqual(mergeConfiguredStyles(row, cell), {
    fontSize: '10px',
    fontWeight: 'normal',
    color: '#123456',
  })
})

test('cell styles override row styles only when explicitly configured', () => {
  assert.deepEqual(
    mergeConfiguredStyles({ textAlign: 'center', fontWeight: 'bold' }, { textAlign: 'right' }),
    { textAlign: 'right', fontWeight: 'bold' },
  )
})

test('compiled explicit styles can override legacy important CSS', () => {
  assert.equal(
    styleObjectToCss({ fontSize: '9px', fontWeight: 'normal' }, true),
    'font-size: 9px !important; font-weight: normal !important;',
  )
})

test('cell schema preserves extended layout and border properties', () => {
  const style = normalizeElementTextStyle({
    fontFamily: 'Tahoma',
    verticalAlign: 'middle',
    paddingLeft: '6px',
    borderTopStyle: 'solid',
    borderTopWidth: '1px',
    borderTopColor: '#111111',
  })

  assert.equal(style.fontFamily, 'Tahoma')
  assert.equal(style.verticalAlign, 'middle')
  assert.equal(style.paddingLeft, '6px')
  assert.equal(
    styleObjectToCss(style),
    'font-family: Tahoma; vertical-align: middle; padding-left: 6px; border-top-style: solid; border-top-width: 1px; border-top-color: #111111;',
  )
})

test('normal font weight removes legacy whole-cell bold tags', () => {
  assert.equal(contentForTextStyle('<b>HTTT</b>', { fontWeight: 'normal' }), 'HTTT')
})

test('text block overrides target the wrapper and all rendered text descendants', () => {
  assert.equal(
    scopedBlockTextStyleCss(
      '.pms-template-block-footer',
      { fontSize: '11px', fontWeight: 'normal', color: '#123456', textAlign: 'left' },
      { fontWeight: true, textAlign: true },
    ),
    '.pms-template-block-footer, .pms-template-block-footer * { text-align: left !important; font-weight: normal !important; }',
  )
})

test('text block styles do not override legacy content until user configures them', () => {
  assert.equal(
    scopedBlockTextStyleCss('.pms-template-block-title', { fontWeight: 'normal' }, {}),
    '',
  )
})

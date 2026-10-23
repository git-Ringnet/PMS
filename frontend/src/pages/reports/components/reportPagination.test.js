import assert from 'node:assert/strict'
import { test } from 'node:test'
import { clampPage, nextZoom, pageCountFor, paperMetrics, printStyles } from './reportPagination.js'

test('paper metrics follow template size and orientation', () => {
  const portrait = paperMetrics({ page_size: 'A4', page_orientation: 'portrait' })
  const landscape = paperMetrics({ page_size: 'A4', page_orientation: 'landscape' })
  assert.equal(portrait.widthMm, 210)
  assert.equal(landscape.widthMm, 297)
  assert.equal(paperMetrics(null).heightMm, 297)
})

test('page count includes the gap between pages', () => {
  const paper = { height: 1000 }
  assert.equal(pageCountFor(1000, paper, 16), 1)
  assert.equal(pageCountFor(2016, paper, 16), 2)
  assert.equal(pageCountFor(2017, paper, 16), 3)
  assert.equal(pageCountFor(0, paper, 0), 1)
})

test('page and zoom navigation stay in range', () => {
  assert.equal(clampPage('0', 5), 1)
  assert.equal(clampPage(9, 5), 5)
  assert.equal(nextZoom(125, 1), 150)
  assert.equal(nextZoom(110, -1), 100)
  assert.equal(nextZoom(200, 1), 200)
  assert.equal(nextZoom(50, -1), 50)
})

test('single-page print clips to the requested page', () => {
  const css = printStyles({ widthMm: 210, heightMm: 297, height: 1000 }, { top: 10, right: 5, bottom: 10, left: 5 }, 2)
  assert.match(css, /size: 210mm 297mm/)
  assert.match(css, /margin-top: -2000px/)
  assert.doesNotMatch(printStyles({ widthMm: 210, heightMm: 297, height: 1000 }, { top: 0, right: 0, bottom: 0, left: 0 }), /margin-top: -/)
})

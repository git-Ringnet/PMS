import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse } from '@vue/compiler-sfc'
import { ref, computed, watch } from 'vue'

const source = readFileSync(new URL('../src/pages/housekeeping/components/CreateMenuTab.vue', import.meta.url), 'utf8')
const script = parse(source).descriptor.scriptSetup.content.replace(/^import .*$/gm, '')

function menu(responses = {}) {
  const hooks = []
  const errors = []
  const response = { data: [] }
  const bindings = {
    ref, computed, watch,
    onMounted: fn => hooks.push(fn), onUnmounted: () => {},
    storageImageUrl: value => value || null,
    useUiStore: () => ({ showToast: (...args) => errors.push(args) }),
    useAuthStore: () => ({}),
    http: { get: async url => ({ data: responses[url] ?? [] }) },
    fetchHousekeepingOutlets: async () => ({ data: [{ code: 'MB', name: 'Minibar', is_active: true }] }),
  }
  return new Function(...Object.keys(bindings), `${script}\nreturn { newProduct, displayFinalPrice, displayBasePrice, onTaxPercentInput, adjustTaxPercent, clearTaxPercent, groups, activeTab, filterState, filteredGroups, fetchGroupsAndProducts }`)(...Object.values(bindings))
}

test('195: changing each tax percentage preserves the final selling price', () => {
  const m = menu()
  m.displayFinalPrice.value = '100,000'
  for (const key of ['service_charge_percent', 'special_tax_percent', 'tax_percent']) {
    m.newProduct.value[key] = 5
    m.onTaxPercentInput()
    assert.equal(m.newProduct.value.price, 100000)
  }
  const base = m.newProduct.value.original_amount
  m.adjustTaxPercent(3)
  assert.equal(m.newProduct.value.price, 100000)
  assert.ok(m.newProduct.value.original_amount < base)
  m.clearTaxPercent()
  assert.equal(m.newProduct.value.price, 100000)
})

test('195: base entry calculates a final price and zero remains zero', () => {
  const m = menu()
  m.newProduct.value.service_charge_percent = 5
  m.newProduct.value.tax_percent = 8
  m.displayBasePrice.value = '100,000'
  assert.equal(m.newProduct.value.price, 113400)
  m.displayFinalPrice.value = '0'
  m.onTaxPercentInput()
  assert.equal(m.newProduct.value.price, 0)
  assert.equal(m.newProduct.value.original_amount, 0)
})

test('195: tax breakdown follows service, special tax, VAT and reconciles with the final price', () => {
  const m = menu()
  Object.assign(m.newProduct.value, { service_charge_percent: 5, special_tax_percent: 5, tax_percent: 8 })
  m.displayFinalPrice.value = '100,000'
  assert.equal(m.newProduct.value.original_amount, 83984)
  assert.equal(m.newProduct.value.service_charge_amount, 4199)
  assert.equal(m.newProduct.value.special_tax_amount, 4409)
  for (const vat of [8, 10, 0]) {
    m.newProduct.value.tax_percent = vat
    m.onTaxPercentInput()
    const p = m.newProduct.value
    assert.equal(p.price, 100000)
    assert.equal(p.original_amount + p.service_charge_amount + p.special_tax_amount + p.tax_amount, p.price)
  }
})

test('197: missing legacy product names do not crash menu sorting or search', () => {
  const m = menu()
  m.activeTab.value = 'MB'
  m.groups.value = [{ id: 1, outlet: 'MB', products: [
    { id: 1, name: 'Coca', tab: 'MB', priceNum: 100000 },
    { id: 2, name: null, tab: 'MB', priceNum: 0 },
  ] }]
  assert.equal(m.filteredGroups.value[0].products.length, 2)
  m.filterState.value.search = 'coca'
  assert.equal(m.filteredGroups.value[0].products.length, 1)
})

test('196: menu prices include taxes only once, even without a stored base price', async () => {
  const m = menu({
    '/product-categories': [{ id: 1, name: 'Minibar', outlet: 'MB' }],
    '/products': [null, 0, 88183].map((original_amount, index) => ({
      id: index + 1, name: 'Coca', product_category_id: 1,
      price: '100000.00', original_amount, service_charge_percent: 5, tax_percent: 8,
    })),
  })
  await m.fetchGroupsAndProducts()
  assert.deepEqual(m.groups.value[0].products.map(p => p.priceNum), [100000, 100000, 100000])
})

test('196: add service uses the same final price for flat and nested product responses', async () => {
  const sfc = readFileSync(new URL('../src/pages/housekeeping/components/PostBillHousekeepingTab.vue', import.meta.url), 'utf8')
  const start = sfc.indexOf('const loadDbProducts = async () => {')
  const loader = sfc.slice(start, sfc.indexOf('\nonMounted(', start))
  for (const nested of [false, true]) {
    const products = [null, 88183].map((original_amount, i) => ({
      id: i + 1, name: 'Coca', price: 100000, original_amount,
      open_key: true, is_active: true, product_category_id: 1,
      service_charge_percent: 5, tax_percent: 8, special_tax_percent: 2,
    }))
    const categories = [{ id: 1, name: 'Minibar', outlet: 'MB', ...(nested ? { products } : {}) }]
    const dbProductsData = ref({})
    const housekeepingOutlets = ref([])
    const bindings = {
      loadingProducts: ref(false), housekeepingOutlets, dbProductsData,
      currentTab: ref(''), tabKeys: computed(() => housekeepingOutlets.value.map(o => o.code)),
      productGroup: {}, storageImageUrl: v => v || null,
      fetchHousekeepingOutlets: async () => ({ data: [{ code: 'MB', name: 'Minibar', is_active: true, show_in_add_service: true, service_code: 'MB' }] }),
      http: { get: async url => ({ data: url === '/products' ? products : categories }) },
    }
    await new Function(...Object.keys(bindings), `${loader}\nreturn loadDbProducts()`)(...Object.values(bindings))
    assert.deepEqual(dbProductsData.value.MB.Minibar.map(p => p.price), [100000, 100000])
  }
})

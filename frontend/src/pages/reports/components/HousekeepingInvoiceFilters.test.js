import assert from 'node:assert/strict'
import { before, test } from 'node:test'
import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { compileScript, parse } from '@vue/compiler-sfc'
import { h, nextTick, reactive } from 'vue'
import { createRenderer } from '@vue/runtime-core'

const frontendRoot = fileURLToPath(new URL('../../../../', import.meta.url))
const componentUrl = '/src/pages/reports/components/HousekeepingInvoiceFilters.vue'

const datePickerStub = `
import { h, ref } from 'vue'

export default {
  props: { startDate: String, endDate: String, systemDate: String },
  emits: ['update:startDate', 'update:endDate', 'change'],
  setup(props, { emit }) {
    const open = ref(false)
    const apply = () => {
      emit('update:startDate', props.startDate)
      emit('update:endDate', props.endDate)
      emit('change', { start: props.startDate, end: props.endDate })
      open.value = false
    }
    return () => h('div', { class: 'relative' }, [
      h('button', {
        type: 'button',
        class: 'flex w-full items-center justify-between',
        onClick: () => { open.value = !open.value },
      }, props.startDate && props.endDate && props.startDate === props.systemDate && props.endDate === props.systemDate ? 'Hôm nay' : String(props.startDate) + ' ~ ' + String(props.endDate)),
      open.value ? h('button', { type: 'button', onClick: apply }, 'Áp dụng') : null,
    ])
  },
}
`

const host = {
  createElement: (type) => ({ type, props: {}, children: [], parent: null }),
  createText: (text) => ({ type: '#text', text: String(text), props: {}, children: [], parent: null }),
  createComment: (text) => ({ type: '#comment', text: String(text), props: {}, children: [], parent: null }),
  setText: (node, text) => { node.text = String(text) },
  setElementText: (node, text) => {
    node.children = [{ type: '#text', text: String(text), props: {}, children: [], parent: node }]
  },
  parentNode: (node) => node.parent,
  nextSibling: (node) => {
    if (!node.parent) return null
    const index = node.parent.children.indexOf(node)
    return node.parent.children[index + 1] || null
  },
  insert: (node, parent, anchor = null) => {
    node.parent = parent
    if (anchor) {
      parent.children.splice(parent.children.indexOf(anchor), 0, node)
    } else {
      parent.children.push(node)
    }
  },
  remove: (node) => {
    if (node.parent) node.parent.children = node.parent.children.filter((child) => child !== node)
    node.parent = null
  },
  patchProp: (node, key, _previous, value) => { node.props[key] = value },
  forcePatchProp: () => false,
  querySelector: () => null,
  setScopeId: () => {},
  cloneNode: (node) => ({ ...node, props: { ...node.props }, children: [...node.children] }),
  insertStaticContent: (content, parent, anchor) => {
    const node = host.createText(content)
    host.insert(node, parent, anchor)
    return [node, node]
  },
}

const renderer = createRenderer(host)
let HousekeepingInvoiceFilters
let componentSource

before(async () => {
  componentSource = await readFile(resolve(frontendRoot, `.${componentUrl}`), 'utf8')
  const { descriptor } = parse(componentSource)
  const compiled = compileScript(descriptor, { id: 'hk-invoice-filter-test', inlineTemplate: true })
  const vueUrl = pathToFileURL(resolve(frontendRoot, 'node_modules/vue/index.mjs')).href
  const pickerUrl = `data:text/javascript,${encodeURIComponent(datePickerStub.replaceAll("from 'vue'", `from '${vueUrl}'`))}`
  const executableCode = compiled.content
    .replace(/from ['"]vue['"]/g, `from '${vueUrl}'`)
    .replace(/import ReportDateRangePicker from ['"][^'"]+['"];?/, `import ReportDateRangePicker from ${JSON.stringify(pickerUrl)};`)
  const module = await import(`data:text/javascript,${encodeURIComponent(executableCode)}`)
  HousekeepingInvoiceFilters = module.default
})

test('uses the same control and action styles as the generic report filters', () => {
  assert.match(componentSource, /rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs/)
  assert.match(componentSource, /w-full items-center justify-center gap-2 rounded-lg border-none bg-sky-600/)
  assert.match(componentSource, /h-5 w-9 shrink-0 items-center rounded-full/)
})

const walk = (node, result = []) => {
  if (!node || node.type === '#text' || node.type === '#comment') return result
  result.push(node)
  for (const child of node.children || []) walk(child, result)
  return result
}

const textContent = (node) => {
  if (!node) return ''
  if (node.type === '#text') return node.text
  return (node.children || []).map(textContent).join('')
}

const findElement = (root, predicate) => walk(root).find(predicate)

let appId = 0
const mountFilters = (modelValue, lists = {}) => {
  const updates = []
  const submissions = []
  const props = reactive({
    modelValue,
    systemDate: '2026-09-09',
    shifts: lists.shifts || [],
    departments: lists.departments || [],
    users: lists.users || [],
    sortOptions: lists.sortOptions || [],
    loading: Boolean(lists.loading),
    disabled: Boolean(lists.disabled),
  })
  const Root = {
    setup: () => () => h(HousekeepingInvoiceFilters, {
      ...props,
      'onUpdate:modelValue': (value) => updates.push(value),
      onSubmit: (value) => submissions.push(value),
    }),
  }
  const container = host.createElement('root')
  const app = renderer.createApp(Root)
  app.config.idPrefix = `test-app-${appId++}`
  app.mount(container)
  return { app, container, updates, submissions, props }
}

test('renders the shared filter controls without emitting or requesting on mount', () => {
  const original = {
    p_from_date: '2026-09-09',
    p_to_date: '2026-09-10',
    p_shift: '',
    p_department: '',
    p_user: '',
    p_order_by: 'Amount',
    p_show_details: 0,
  }
  const { app, container, updates, submissions } = mountFilters(original, {
    shifts: [{ value: '1', label: 'Ca 1' }],
    departments: [{ id: 'HK', name: 'Housekeeping' }],
    users: [{ username: 'admin', name: 'Admin' }],
    sortOptions: [{ value: 'Amount', label: 'Mã' }],
  })

  assert.equal(updates.length, 0)
  assert.equal(submissions.length, 0)
  assert.match(textContent(container), /Chọn ngày/)
  assert.match(textContent(container), /Ca làm việc/)
  assert.match(textContent(container), /Chọn bộ phận/)
  assert.match(textContent(container), /Chọn người dùng/)
  assert.match(textContent(container), /All/)
  assert.match(textContent(container), /Post/)
  assert.match(textContent(container), /Correct/)
  assert.match(textContent(container), /Free/)
  const orderType = findElement(container, (node) => node.type === 'select' && node.props.id?.endsWith('-order-type'))
  assert.equal(orderType.props.value, 'ASC')

  const emptyModelMount = mountFilters({})
  assert.match(textContent(emptyModelMount.container), /Hôm nay/)
  assert.match(textContent(emptyModelMount.container), /Mã/)
  assert.equal(emptyModelMount.updates.length, 0)
  assert.equal(emptyModelMount.submissions.length, 0)

  const firstIds = walk(container).map((node) => node.props.id).filter(Boolean)
  const secondIds = walk(emptyModelMount.container).map((node) => node.props.id).filter(Boolean)
  assert.equal(firstIds.length > 0, true)
  assert.equal(secondIds.length > 0, true)
  assert.equal(firstIds.some((id) => secondIds.includes(id)), false)

  app.unmount()
  emptyModelMount.app.unmount()
})

test('emits a complete cloned model for controls, date range, and explicit submit', async () => {
  const original = {
    p_from_date: '2026-09-09',
    p_to_date: '2026-09-10',
    p_shift: '',
    p_department: '',
    p_user: '',
    p_view_type: 'Post',
    p_order_by: 'Amount',
    p_order_type: 'ASC',
    p_show_details: false,
  }
  const { app, container, updates, submissions } = mountFilters(original, {
    shifts: [{ value: '1', label: 'Ca 1' }],
    departments: [{ value: 'HK', label: 'Housekeeping' }],
    users: [{ value: 'admin', label: 'Admin' }],
    sortOptions: [{ value: 'Amount', label: 'Mã' }],
  })

  const selectBySuffix = (suffix, value) => {
    const node = findElement(container, (item) => item.type === 'select' && item.props.id?.endsWith(`-${suffix}`))
    assert.equal(typeof node.props.onChange, 'function')
    node.props.onChange({ target: { value } })
  }

  selectBySuffix('shift', '1')
  selectBySuffix('order-type', 'DESC')

  const correct = findElement(container, (node) => node.type === 'input' && node.props.value === 'correct')
  assert.equal(typeof correct.props.onChange, 'function')
  correct.props.onChange({ target: { checked: true, value: 'correct' } })

  const details = findElement(container, (node) => node.props.id?.endsWith('-show-details'))
  details.props.onChange({ target: { checked: true } })

  // ReportDateRangePicker emits start and end separately when applying a range.
  // Both parent updates must retain the other date and every unrelated filter key.
  const datePickerButton = findElement(container, (node) => node.type === 'button' && node.props.class?.includes('flex w-full items-center'))
  assert.equal(typeof datePickerButton.props.onClick, 'function')
  datePickerButton.props.onClick()
  await nextTick()
  const applyDateButton = findElement(container, (node) => node.type === 'button' && textContent(node).includes('Áp dụng'))
  assert.equal(typeof applyDateButton.props.onClick, 'function')
  applyDateButton.props.onClick()
  await nextTick()

  const form = findElement(container, (node) => node.type === 'form')
  form.props.onSubmit({ preventDefault: () => {} })

  assert.equal(updates.length >= 6, true)
  for (const update of updates) {
    assert.equal(update.p_to_date, '2026-09-10')
    assert.equal(update.p_order_by, 'Amount')
  }
  assert.equal(updates[0].p_view_type, 'post')
  assert.equal(updates.at(-1).p_view_type, 'correct')
  assert.deepEqual(submissions.at(-1), {
    ...original,
    p_shift: '1',
    p_order_type: 'DESC',
    p_view_type: 'correct',
    p_show_details: true,
  })
  assert.deepEqual(original, {
    p_from_date: '2026-09-09',
    p_to_date: '2026-09-10',
    p_shift: '',
    p_department: '',
    p_user: '',
    p_view_type: 'Post',
    p_order_by: 'Amount',
    p_order_type: 'ASC',
    p_show_details: false,
  })

  app.unmount()
})

test('does not emit updates or submit while locked', () => {
  const { app, container, updates, submissions } = mountFilters({}, { loading: true })
  const shift = findElement(container, (node) => node.type === 'select' && node.props.id?.endsWith('-shift'))
  const details = findElement(container, (node) => node.type === 'input' && node.props.id?.endsWith('-show-details'))
  const form = findElement(container, (node) => node.type === 'form')

  shift.props.onChange({ target: { value: '1' } })
  details.props.onChange({ target: { checked: true } })
  form.props.onSubmit({ preventDefault: () => {} })

  assert.equal(updates.length, 0)
  assert.equal(submissions.length, 0)
  assert.match(textContent(container), /Đang tải/)
  app.unmount()
})

test('parent changes reset the draft and empty dropdown values remain defined', async () => {
  const { app, container, props, submissions } = mountFilters({})
  const form = findElement(container, (node) => node.type === 'form')
  form.props.onSubmit({ preventDefault() {} })
  assert.deepEqual(submissions[0], {
    p_from_date: '2026-09-09', p_to_date: '2026-09-09', p_shift: '',
    p_department: '', p_user: '', p_view_type: 'post', p_order_by: 'Ma',
    p_order_type: 'ASC', p_show_details: false,
  })
  props.modelValue = { p_from_date: '2026-09-01', p_to_date: '2026-09-03', p_view_type: 'FREE', p_shift: '2' }
  await nextTick()
  form.props.onSubmit({ preventDefault() {} })
  assert.equal(submissions.at(-1).p_view_type, 'free')
  assert.equal(submissions.at(-1).p_from_date, '2026-09-01')
  assert.equal(submissions.at(-1).p_shift, '2')
  props.disabled = true
  await nextTick()
  form.props.onSubmit({ preventDefault() {} })
  assert.equal(submissions.length, 2)
  app.unmount()
})

test('two filter panels within one app have independent control IDs and radio groups', () => {
  const container = host.createElement('root')
  const app = renderer.createApp({
    setup: () => () => h('div', [h(HousekeepingInvoiceFilters), h(HousekeepingInvoiceFilters)]),
  })
  app.mount(container)
  const ids = walk(container).map((node) => node.props.id).filter(Boolean)
  assert.equal(new Set(ids).size, ids.length)
  const radios = walk(container).filter((node) => node.type === 'input' && node.props.type === 'radio')
  assert.equal(radios.length, 8)
  assert.equal(new Set(radios.map((node) => node.props.name)).size, 2)
  app.unmount()
})

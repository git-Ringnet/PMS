const REQUIRED_CLASS = 'input-required'

const CONTROL_SELECTORS = [
  'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"])',
  'select',
  'textarea',
  '[role="combobox"]',
]

const CONTROL_SELECTOR = CONTROL_SELECTORS.join(', ')
const REQUIRED_CONTROL_SELECTOR = CONTROL_SELECTORS
  .flatMap(selector => [`${selector}[required]`, `${selector}[aria-required="true"]`])
  .join(', ')

const highlightedControls = new Set()

function controlsForLabel(label) {
  const controls = new Set(label.querySelectorAll(CONTROL_SELECTOR))

  if (label.htmlFor) {
    const linkedControl = document.getElementById(label.htmlFor)
    if (linkedControl?.matches(CONTROL_SELECTOR)) controls.add(linkedControl)
  }

  if (!controls.size) {
    const sibling = label.nextElementSibling
    if (sibling?.matches(CONTROL_SELECTOR)) {
      controls.add(sibling)
    } else {
      sibling?.querySelectorAll(CONTROL_SELECTOR).forEach(control => controls.add(control))
    }
  }

  if (!controls.size) {
    const parentSibling = label.parentElement?.nextElementSibling
    if (parentSibling?.matches(CONTROL_SELECTOR)) {
      controls.add(parentSibling)
    } else {
      parentSibling?.querySelectorAll(CONTROL_SELECTOR).forEach(control => controls.add(control))
    }
  }

  return controls
}

function isRequiredLabel(label) {
  const text = label.textContent?.trim() || ''
  if (label.tagName === 'LABEL') return text.includes('*')
  if (label.tagName !== 'SPAN' || !text.endsWith('*')) return false

  // Ignore decorative asterisks inside section headings and real labels;
  // field captions in older screens are sometimes rendered as standalone spans.
  return !label.closest('label, h1, h2, h3, h4, h5, h6, th, td')
}

export function refreshRequiredFieldHighlights(root = document) {
  const requiredControls = new Set(
    root.querySelectorAll(REQUIRED_CONTROL_SELECTOR),
  )

  root.querySelectorAll('label, span').forEach(label => {
    if (!isRequiredLabel(label)) return
    controlsForLabel(label).forEach(control => requiredControls.add(control))
  })

  highlightedControls.forEach(control => {
    if (!control.isConnected || !requiredControls.has(control)) {
      control.classList.remove(REQUIRED_CLASS)
      highlightedControls.delete(control)
    }
  })

  requiredControls.forEach(control => {
    control.classList.add(REQUIRED_CLASS)
    highlightedControls.add(control)
  })
}

export function installRequiredFieldHighlighter(root = document) {
  let scheduled = false
  const scheduleRefresh = () => {
    if (scheduled) return
    scheduled = true
    requestAnimationFrame(() => {
      scheduled = false
      refreshRequiredFieldHighlights(root)
    })
  }

  scheduleRefresh()

  const observer = new MutationObserver(scheduleRefresh)
  observer.observe(root.body || root.documentElement, {
    childList: true,
    subtree: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['required', 'aria-required', 'for'],
  })

  return () => observer.disconnect()
}

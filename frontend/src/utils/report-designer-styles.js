export const defaultElementTextStyle = Object.freeze({
  fontFamily: '',
  textAlign: '',
  verticalAlign: '',
  fontSize: '',
  fontWeight: '',
  fontStyle: '',
  textDecoration: '',
  color: '',
  backgroundColor: '',
  lineHeight: '',
  letterSpacing: '',
  whiteSpace: '',
  overflowWrap: '',
  wordBreak: '',
  paddingTop: '',
  paddingRight: '',
  paddingBottom: '',
  paddingLeft: '',
  borderTopStyle: '',
  borderTopWidth: '',
  borderTopColor: '',
  borderRightStyle: '',
  borderRightWidth: '',
  borderRightColor: '',
  borderBottomStyle: '',
  borderBottomWidth: '',
  borderBottomColor: '',
  borderLeftStyle: '',
  borderLeftWidth: '',
  borderLeftColor: '',
  height: '',
  minHeight: '',
})

export const normalizeElementTextStyle = style => ({
  ...defaultElementTextStyle,
  ...(style || {}),
})

export const mergeConfiguredStyles = (...styles) => styles.reduce((result, style) => {
  Object.entries(style || {}).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') result[key] = value
  })
  return result
}, {})

export const styleObjectToCss = (style, important = false) => Object.entries(style || {})
  .filter(([, value]) => value !== undefined && value !== null && value !== '')
  .map(([key, value]) => `${key.replace(/([A-Z])/g, '-$1').toLowerCase()}: ${value}${important ? ' !important' : ''};`)
  .join(' ')

export const contentForTextStyle = (content, style) => {
  const value = String(content || '')
  return style?.fontWeight === 'normal'
    ? value.replace(/<\/?(?:b|strong)\b[^>]*>/gi, '')
    : value
}

const blockTextStyleKeys = ['textAlign', 'fontSize', 'fontWeight', 'color']

export const scopedBlockTextStyleCss = (selector, style, overrides) => {
  const configuredStyle = blockTextStyleKeys.reduce((result, key) => {
    if (overrides?.[key] && style?.[key] !== undefined && style?.[key] !== null && style?.[key] !== '') {
      result[key] = style[key]
    }
    return result
  }, {})

  const css = styleObjectToCss(configuredStyle, true)
  return css ? `${selector}, ${selector} * { ${css} }` : ''
}

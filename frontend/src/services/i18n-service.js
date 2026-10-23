import http from './http'

/** Ngôn ngữ & Bản dịch (Cấu hình hệ thống › Ngôn ngữ) */
export const fetchTranslationAreas = () => http.get('/i18n/admin/areas')
export const fetchTranslationEntries = (params) => http.get('/i18n/admin/entries', { params })
export const saveTranslationEntries = (items) => http.put('/i18n/admin/entries', { items })
export const createTranslationEntry = (payload) => http.post('/i18n/admin/entries', payload)
export const deleteTranslationEntry = (id) => http.delete(`/i18n/admin/entries/${id}`)
export const clearTranslationLocations = (id) => http.delete(`/i18n/admin/entries/${id}/locations`)
export const syncTranslationDefaults = (items) => http.post('/i18n/admin/sync-defaults', { items }, { timeout: 60000 })
export const exportTranslations = (params) => http.get('/i18n/admin/export', { params, responseType: 'blob', timeout: 60000 })
export const importTranslations = (file) => {
  const form = new FormData()
  form.append('file', file)
  return http.post('/i18n/admin/import', form, { timeout: 120000 })
}

export const fetchAllLanguages = () => http.get('/i18n/admin/languages')
export const createLanguage = (payload) => http.post('/i18n/admin/languages', payload)
export const updateLanguage = (id, payload) => http.put(`/i18n/admin/languages/${id}`, payload)
export const deleteLanguage = (id) => http.delete(`/i18n/admin/languages/${id}`)

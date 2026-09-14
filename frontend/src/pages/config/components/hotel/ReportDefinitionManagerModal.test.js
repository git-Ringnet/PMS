import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import test from 'node:test'

const frontendRoot = fileURLToPath(new URL('../../../../../', import.meta.url))
const componentPath = resolve(frontendRoot, 'src/pages/config/components/hotel/ReportDefinitionManagerModal.vue')

test('exposes every report metadata control and dynamic lookup accepted by the API', async () => {
  const source = await readFile(componentPath, 'utf8')

  for (const value of [
    'radio', 'areas', 'companies', 'bookings', 'rooms', 'room-classes',
    'registration-statuses', 'users', 'hotel-services', 'report-shifts', 'service-departments',
  ]) {
    assert.match(source, new RegExp(`value="${value}"`))
  }
})

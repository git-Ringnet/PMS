import test from 'node:test'
import assert from 'node:assert/strict'

import {
  calculateRoomPlanBookingAmounts,
  calculateRoomPlanRoomAmounts,
} from '../src/utils/room-plan-amounts.js'

const booking = {
  arrival_date: '2026-08-20',
  departure_date: '2026-08-24',
  num_of_days: 4,
  master_service_bills: [],
}

test('row 149 uses valid historical setup bills and ignores edited/manual bills', () => {
  const room = {
    id: 1,
    arrival_date: '2026-08-20',
    departure_date: '2026-08-24',
    services: [{ service_bill_id: 10 }],
    service_bills: [
      { Ma: 10, ServiceId: 'FO', Amount: 100, Date: '2026-08-21', Edit: 0 },
      { Ma: 11, ServiceId: 'FO', Amount: 900, Date: '2026-08-21', Edit: 1 },
      { Ma: 12, ServiceId: 'FO', Amount: 700, Date: '2026-08-21', Edit: 0 },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking, '2026-08-23'), {
    roomCharge: 0,
    serviceCharge: 100,
  })
})

test('row 149 calculates current/future setup services and room charge', () => {
  const room = {
    id: 1,
    arrival_date: '2026-08-23',
    departure_date: '2026-08-25',
    rate: 500,
    extra_bed_qty: 1,
    extra_bed_rate: 50,
    services: [
      { service_code: 'RM', service_date: '2026-08-23', quantity: 1, rate: 600, is_posted: 0 },
      { service_code: 'EB', service_date: '2026-08-24', quantity: 1, rate: 50, is_posted: 0 },
      { service_code: 'SPA', service_date: '2026-08-24', quantity: 2, rate: 25, is_posted: 0 },
      { service_code: 'BAR', service_date: '2026-08-24', quantity: 1, rate: 99, is_posted: 1 },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking, '2026-08-23'), {
    roomCharge: 600,
    serviceCharge: 100,
  })
})

test('row 149 includes projected extra-charge child breakfast only', () => {
  const room = {
    id: 1,
    arrival_date: '2026-08-23',
    departure_date: '2026-08-25',
    rate: 500,
    services: [],
    children: [{
      breakfast_details: [
        { service_date: '2026-08-24', is_extra_charge: 1, breakfast: 1, amount: 40 },
        { service_date: '2026-08-22', is_extra_charge: 1, breakfast: 1, amount: 90 },
        { service_date: '2026-08-24', is_extra_charge: 0, breakfast: 1, amount: 100 },
      ],
    }],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking, '2026-08-23'), {
    roomCharge: 1000,
    serviceCharge: 40,
  })
})

test('row 149 aggregates active rooms and excludes cancelled rooms', () => {
  const result = calculateRoomPlanBookingAmounts({
    ...booking,
    booking_rooms: [
      { id: 1, status: 0, arrival_date: '2026-08-23', departure_date: '2026-08-24', rate: 100, services: [] },
      { id: 2, status: 1, arrival_date: '2026-08-23', departure_date: '2026-08-24', rate: 200, services: [] },
      { id: 3, status: 3, arrival_date: '2026-08-23', departure_date: '2026-08-24', rate: 999, services: [] },
    ],
  }, '2026-08-23')

  assert.deepEqual(result, { roomCharge: 300, serviceCharge: 0 })
})

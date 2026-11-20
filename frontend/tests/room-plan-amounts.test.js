import test from 'node:test'
import assert from 'node:assert/strict'

import {
  calculateRoomPlanBookingAmounts,
  calculateRoomPlanPaymentAmounts,
  calculateRoomPlanRoomAmounts,
  resolveRoomPlanBookingNote,
} from '../src/utils/room-plan-amounts.js'

const booking = {
  arrival_date: '2026-08-20',
  departure_date: '2026-08-24',
}

test('task 222 only sums setup services of the same room, never Checkout bills', () => {
  const room = {
    id: 1,
    arrival_date: '2026-08-20',
    departure_date: '2026-08-22',
    rate: 500,
    services: [
      { service_code: 'RM', service_date: '2026-08-20', total_amount: 600, is_posted: 1, service_bill_id: 10 },
      { service_code: 'RM', service_date: '2026-08-21', total_amount: 700, is_posted: 0 },
      { service_code: 'SPA', service_date: '2026-08-21', total_amount: 100, is_posted: 0 },
    ],
    service_bills: [
      { Ma: 10, ServiceId: 'RM', Amount: 999999, Date: '2026-08-20', Edit: 0 },
      { Ma: 11, ServiceId: 'MB', Amount: 888888, Date: '2026-08-21', Edit: 0 },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking), {
    roomCharge: 1300,
    serviceCharge: 100,
  })
})

test('task 222 uses the configured amount of every room-night and only falls back for missing nights', () => {
  const room = {
    id: 1,
    arrival_date: '2026-08-23',
    departure_date: '2026-08-26',
    rate: 500,
    services: [
      { service_code: 'RM', service_date: '2026-08-23', quantity: 1, rate: 600, is_posted: 0 },
      { service_code: 'RM', service_date: '2026-08-24', total_amount: 750, is_posted: 1 },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking), {
    roomCharge: 1850,
    serviceCharge: 0,
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

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking), {
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

test('uses registration setup for all stay dates regardless of current system date', () => {
  const testBooking = {
    arrival_date: '2026-08-09',
    departure_date: '2026-08-13',
  }
  const room = {
    id: 101,
    arrival_date: '2026-08-09',
    departure_date: '2026-08-13',
    rate: 650000,
    services: [
      { service_code: 'RM', service_date: '2026-08-09', quantity: 1, rate: 650000, is_posted: 0 },
      { service_code: 'RM', service_date: '2026-08-10', quantity: 1, rate: 650000, is_posted: 0 },
      { service_code: 'RM', service_date: '2026-08-11', quantity: 1, rate: 650000, is_posted: 0 },
      { service_code: 'RM', service_date: '2026-08-12', quantity: 1, rate: 650000, is_posted: 0 },
    ],
  }

  const roomAmounts = calculateRoomPlanRoomAmounts(room, testBooking)
  assert.deepEqual(roomAmounts, {
    roomCharge: 2600000,
    serviceCharge: 0,
  })
})

test('uses the Day-use registration setup and ignores room or master bills', () => {
  const dayUseBooking = {
    arrival_date: '2026-08-30',
    departure_date: '2026-08-30',
    is_day_use: true,
    master_service_bills: [{
      Ma: 1,
      ServiceId: 'RM',
      Amount: 650000,
      Date: '2026-08-30',
      Edit: 0,
      RentalRoomId1: 'G0000004',
    }],
  }
  const room = {
    id: 'G0000004',
    arrival_date: '2026-08-30',
    departure_date: '2026-08-30',
    is_day_use: true,
    rate: 650000,
    services: [{ service_code: 'RM', service_date: '2026-08-30', total_amount: 650000, is_posted: 1 }],
    service_bills: [{
      Ma: 1,
      ServiceId: 'RM',
      Amount: 650000,
      Date: '2026-08-30',
      Edit: 0,
      RentalRoomId1: 'G0000004',
    }],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, dayUseBooking), {
    roomCharge: 650000,
    serviceCharge: 0,
  })
})

test('row 442 keeps a zero-quantity daily extra bed at zero instead of charging its rate', () => {
  const room = {
    id: 'G0000012',
    arrival_date: '2026-08-30',
    departure_date: '2026-08-31',
    rate: 650000,
    extra_bed_qty: 1,
    extra_bed_rate: 300000,
    services: [
      {
        service_code: 'EB',
        service_date: '2026-08-30',
        quantity: 0,
        rate: 300000,
        total_amount: 0,
        is_posted: 0,
      },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking), {
    roomCharge: 650000,
    serviceCharge: 0,
  })
})

test('row 442 sums only daily extra-bed quantities greater than zero', () => {
  const room = {
    id: 'G0000012',
    arrival_date: '2026-08-30',
    departure_date: '2026-09-01',
    rate: 650000,
    extra_bed_qty: 1,
    extra_bed_rate: 300000,
    services: [
      { service_code: 'EB', service_date: '2026-08-30', quantity: 1, rate: 300000, is_posted: 0 },
      { service_code: 'EB', service_date: '2026-08-31', quantity: 0, rate: 300000, is_posted: 0 },
    ],
  }

  assert.deepEqual(calculateRoomPlanRoomAmounts(room, booking), {
    roomCharge: 1300000,
    serviceCharge: 300000,
  })
})

test('row 95 deduplicates payments and separates unused deposits from paid amounts', () => {
  const amounts = calculateRoomPlanPaymentAmounts({
    payments: [
      { id: 1, amount: 100, pack2: 'DPR', status: 1, edit_flag: 0 },
      { id: 1, amount: 100, pack2: 'DPR', status: 1, edit_flag: 0 },
      { id: 2, amount: 200, pack2: 'DPR', status: 2, payment_id: 'PT2', edit_flag: 0 },
      { id: 3, amount: 300, pack4: 'AP', status: 2, payment_id: 'PT3', edit_flag: 0 },
      { id: 4, amount: 400, pack2: 'DPR', status: 1, edit_flag: 0, description: '[TRANSFER IN] BK A' },
      { id: 5, amount: 500, pack2: 'DPR', status: 3, edit_flag: 0 },
    ],
  })

  assert.deepEqual(amounts, { depositAmount: 100, paidAmount: 500 })
})

test('task 228 transfer-in deposit with description "(BK_a=>BK_b)" is still excluded from room plan totals', () => {
  const amounts = calculateRoomPlanPaymentAmounts({
    payments: [
      { id: 1, amount: 100, pack2: 'DPR', status: 1, edit_flag: 0, description: 'Deposit (Cash)' },
      { id: 2, amount: 400, pack2: 'DPR', status: 1, edit_flag: 0, description: 'Deposit (Cash) (BK_29=>BK_24)' },
      { id: 3, amount: 600, pack2: 'DPR', status: 1, edit_flag: 0, description: 'Deposit (Cash) (BK_29=>R_105) (R_105=>BK_24)' },
      { id: 4, amount: 50, pack2: 'DPR', status: 1, edit_flag: 0, description: 'Deposit (Cash) (khách đưa trước)' },
    ],
  })

  assert.deepEqual(amounts, { depositAmount: 150, paidAmount: 0 })
})

test('row 95 prefers booking note and safely resolves special-request arrays', () => {
  assert.equal(resolveRoomPlanBookingNote({ note: 'Gọi trước khi đến' }, { note: 'Ghi chú phòng' }), 'Gọi trước khi đến')
  assert.equal(resolveRoomPlanBookingNote({ special_requests: [{ special_request: { name: 'Không hút thuốc' } }] }, { note: 'Ghi chú phòng' }), 'Không hút thuốc')
  assert.equal(resolveRoomPlanBookingNote({}, { note: 'Ghi chú phòng' }), 'Ghi chú phòng')
})

test('task 222 totals each active or moved room from its own registration setup only', () => {
  const result = calculateRoomPlanBookingAmounts({
    arrival_date: '2026-08-20',
    departure_date: '2026-08-22',
    booking_rooms: [
      {
        id: 'OVERNIGHT',
        status: 0,
        arrival_date: '2026-08-20',
        departure_date: '2026-08-22',
        rate: 100,
        services: [{ service_code: 'SPA', service_date: '2026-08-21', quantity: 1, rate: 30, is_posted: 0 }],
      },
      {
        id: 'DAY-USE',
        status: 1,
        is_day_use: true,
        arrival_date: '2026-08-21',
        departure_date: '2026-08-21',
        rate: 650,
        services: [{ service_code: 'RM', service_date: '2026-08-21', total_amount: 650 }],
      },
      {
        id: 'CANCELLED',
        status: 3,
        arrival_date: '2026-08-20',
        departure_date: '2026-08-22',
        rate: 999,
        services: [],
      },
    ],
  })

  assert.deepEqual(result, { roomCharge: 850, serviceCharge: 30 })
})


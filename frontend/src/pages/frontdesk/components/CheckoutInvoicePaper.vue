<script setup>
import { computed } from 'vue'

// Tờ "Hóa đơn thanh toán" của màn hình Trả phòng (theo mẫu khách sạn tham chiếu).
// CSS viết thường (không Tailwind) và nằm ngay trong tờ in để khi sao chép sang iframe in vẫn giữ nguyên.
const props = defineProps({
  data: { type: Object, default: null },
  showPrice: { type: Boolean, default: true },
})

const hotel = computed(() => props.data?.hotel || {})
const header = computed(() => props.data?.header || {})
const summary = computed(() => props.data?.summary || {})
const services = computed(() => props.data?.services || [])
const payments = computed(() => props.data?.payments || [])

const logoUrl = computed(() => {
  const path = String(hotel.value.logo_url || '')
  if (!path || /^(https?:|data:|blob:)/.test(path)) return path
  return `/${path.replace(/^\/+/, '')}`
})

const money = (value) => (Number(value) || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })
const quantity = (value) => (Number(value) || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })

const printedDate = computed(() => {
  const [year, month, day] = String(props.data?.printed_date || '').split('-')
  return { day: Number(day) || '', month: Number(month) || '', year: year || '' }
})

const css = `
.ckinv{font-family:'Times New Roman',Times,serif;color:#000;font-size:13px;padding:14mm 14mm 12mm;box-sizing:border-box;width:210mm;background:#fff}
.ckinv *{box-sizing:border-box}
.ckinv-hotel{display:flex;gap:16px;align-items:flex-start;min-height:80px}
.ckinv-logo{width:96px;height:80px;object-fit:contain}
.ckinv-logo-space{width:96px}
.ckinv-hotel-info div{line-height:1.6}
.ckinv-title{text-align:center;font-size:24px;font-weight:700;margin:18px 0 22px}
.ckinv-info{width:100%;border-collapse:collapse;margin-bottom:14px}
.ckinv-info td{padding:3px 4px;vertical-align:top}
.ckinv-section{font-weight:700;font-size:14px;margin:6px 0 8px}
.ckinv-table{width:100%;border-collapse:collapse}
.ckinv-table th,.ckinv-table td{border:1px solid #000;padding:3px 5px;vertical-align:middle}
.ckinv-table th{font-weight:700;text-align:center}
.ckinv-center{text-align:center}
.ckinv-right{text-align:right;white-space:nowrap}
.ckinv-totals{display:flex;justify-content:space-between;margin:14px 0 8px}
.ckinv-words{padding-top:24px;font-size:12px;line-height:2.2}
.ckinv-sum{width:42%;border-collapse:collapse;font-size:12px}
.ckinv-sum td{padding:3px 2px}
.ckinv-sum .ckinv-strong td{font-weight:700;font-size:13px}
.ckinv-paid{display:flex;justify-content:flex-end;margin-top:12px}
.ckinv-sign{display:flex;justify-content:space-between;margin-top:18px;text-align:center}
.ckinv-sign > div{width:45%}
.ckinv-cashier{margin-top:80px}
@media print{@page{size:A4;margin:0}.ckinv{width:100%}}
`
</script>

<template>
  <div class="ckinv">
    <component :is="'style'">{{ css }}</component>

    <div class="ckinv-hotel">
      <img v-if="logoUrl" :src="logoUrl" alt="" class="ckinv-logo" />
      <div v-else class="ckinv-logo-space"></div>
      <div class="ckinv-hotel-info">
        <div data-no-translate>{{ hotel.name }}</div>
        <div>Địa chỉ: {{ hotel.address }}</div>
        <div>Email: {{ hotel.email }}</div>
        <div>SĐT: {{ hotel.phone }}</div>
      </div>
    </div>

    <div class="ckinv-title">HÓA ĐƠN THANH TOÁN</div>

    <table class="ckinv-info">
      <tbody>
        <tr>
          <td style="width:14%">Tên khách:</td>
          <td style="width:22%" data-no-translate>{{ header.guest_name }}</td>
          <td style="width:12%">Ngày đến:</td>
          <td style="width:18%">{{ header.arrival_date }}</td>
          <td style="width:12%">Folio:</td>
          <td style="width:22%" class="ckinv-right" data-no-translate>{{ header.booking_code }}<template v-if="header.folio"> - {{ header.folio }}</template></td>
        </tr>
        <tr>
          <td>Địa chỉ:</td>
          <td data-no-translate>{{ header.address }}</td>
          <td>Ngày đi:</td>
          <td>{{ header.departure_date }}</td>
          <td>Phòng:</td>
          <td class="ckinv-right" data-no-translate>{{ header.room_number }}</td>
        </tr>
        <tr>
          <td>TA/Công ty:</td>
          <td colspan="5" data-no-translate>{{ header.company_name }}</td>
        </tr>
      </tbody>
    </table>

    <div class="ckinv-section">DỊCH VỤ</div>
    <table class="ckinv-table">
      <thead>
        <tr>
          <th style="width:17%">Ngày</th>
          <th>Mô tả</th>
          <th style="width:15%">Phòng</th>
          <th style="width:18%">Số tiền</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="services.length === 0">
          <td>&nbsp;</td><td></td><td></td><td></td>
        </tr>
        <tr v-for="line in services" :key="line.id">
          <td class="ckinv-center">{{ line.date }}</td>
          <td data-no-translate>
            <div>{{ line.description }}</div>
            <div v-for="(item, index) in line.items" :key="index">- {{ item.name }} ({{ quantity(item.quantity) }})</div>
          </td>
          <td class="ckinv-center">{{ line.room_number }}</td>
          <td class="ckinv-right">{{ showPrice ? money(line.amount) : '' }}</td>
        </tr>
      </tbody>
    </table>

    <div class="ckinv-totals">
      <div class="ckinv-words">
        <div>Bằng chữ:</div>
        <div>{{ showPrice ? summary.amount_in_words : '' }}</div>
      </div>
      <table class="ckinv-sum">
        <tbody>
          <tr><td>Tổng tiền trước thuế phí:</td><td class="ckinv-right">{{ showPrice ? money(summary.net) : '' }}</td></tr>
          <tr><td>Phí:</td><td class="ckinv-right">{{ showPrice ? money(summary.service_charge) : '' }}</td></tr>
          <tr><td>Thuế:</td><td class="ckinv-right">{{ showPrice ? money(summary.tax) : '' }}</td></tr>
          <tr class="ckinv-strong"><td>TỔNG TIỀN:</td><td class="ckinv-right">{{ showPrice ? money(summary.total) : '' }}</td></tr>
        </tbody>
      </table>
    </div>

    <div class="ckinv-section">THANH TOÁN</div>
    <table class="ckinv-table">
      <thead>
        <tr>
          <th style="width:17%">Ngày</th>
          <th>Mô tả</th>
          <th style="width:18%">Số tiền</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="payments.length === 0">
          <td>&nbsp;</td><td></td><td></td>
        </tr>
        <tr v-for="payment in payments" :key="payment.id">
          <td class="ckinv-center">{{ payment.date }}</td>
          <td data-no-translate>{{ payment.description }}</td>
          <td class="ckinv-right">{{ showPrice ? money(payment.amount) : '' }}</td>
        </tr>
      </tbody>
    </table>

    <div class="ckinv-paid">
      <table class="ckinv-sum">
        <tbody>
          <tr class="ckinv-strong"><td>THANH TOÁN:</td><td class="ckinv-right">{{ showPrice ? money(summary.paid) : '' }}</td></tr>
          <tr class="ckinv-strong"><td>SỐ DƯ:</td><td class="ckinv-right">{{ showPrice ? money(summary.balance) : '' }}</td></tr>
        </tbody>
      </table>
    </div>

    <div class="ckinv-sign">
      <div>
        <div>&nbsp;</div>
        <div>Chữ ký của Khách</div>
      </div>
      <div>
        <div>{{ `...., ngày ${printedDate.day} tháng ${printedDate.month} năm ${printedDate.year}` }}</div>
        <div>Chữ ký của Thu ngân</div>
        <div class="ckinv-cashier" data-no-translate>{{ data?.cashier }}</div>
      </div>
    </div>
  </div>
</template>

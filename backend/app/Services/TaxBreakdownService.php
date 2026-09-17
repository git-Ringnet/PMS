<?php

namespace App\Services;

class TaxBreakdownService
{
    /**
     * Bóc tách cơ cấu thuế phí từ giá bán sau thuế (Gross Amount).
     *
     * Công thức chuẩn (vw_018 & Luật thuế Việt Nam):
     * s = ServiceCharge / 100
     * e = SpecialTax / 100
     * t = Tax / 100
     *
     * Net = Amount / ((1 + s) * (1 + e) * (1 + t))
     * SC = Net * s
     * ST = (Net + SC) * e = Net * (1 + s) * e
     * VAT = Amount - (Net + SC + ST)
     *
     * Đảm bảo đẳng thức bất biến: Net + SC + ST + VAT == Amount (không lệch 1 đồng do làm tròn).
     *
     * @param float $amount Tổng số tiền sau thuế phí (Gross)
     * @param float $serviceChargePercent % Phí phục vụ (ví dụ 5 cho 5%)
     * @param float $specialTaxPercent % Thuế TTĐB (ví dụ 10 cho 10%)
     * @param float $taxPercent % Thuế GTGT/VAT (ví dụ 8 hoặc 10)
     * @param float $quantity Số lượng (mặc định 1)
     * @param int $precision Số chữ số thập phân làm tròn (mặc định 2)
     * @return array{
     *     net_total: float,
     *     original_rate: float,
     *     service_charge_amount: float,
     *     special_tax_amount: float,
     *     tax_amount: float,
     *     amount: float
     * }
     */
    public static function breakdown(
        float $amount,
        float $serviceChargePercent = 0.0,
        float $specialTaxPercent = 0.0,
        float $taxPercent = 0.0,
        float $quantity = 1.0,
        int $precision = 2
    ): array {
        $scRate = max(0.0, (float) $serviceChargePercent) / 100.0;
        $stRate = max(0.0, (float) $specialTaxPercent) / 100.0;
        $taxRate = max(0.0, (float) $taxPercent) / 100.0;

        $denom = (1.0 + $scRate) * (1.0 + $stRate) * (1.0 + $taxRate);
        if ($denom <= 0.0) {
            $denom = 1.0;
        }

        // 1. Giá gốc thuần trước thuế phí (Net Total)
        $netTotal = round($amount / $denom, $precision);

        // 2. Phí phục vụ (SC) tính trên Net
        $serviceChargeAmount = $scRate > 0.0 ? round($netTotal * $scRate, $precision) : 0.0;

        // 3. Thuế TTĐB (ST) tính trên (Net + SC)
        $specialTaxAmount = $stRate > 0.0 ? round(($netTotal + $serviceChargeAmount) * $stRate, $precision) : 0.0;

        // 4. Thuế GTGT (VAT) tính trên (Net + SC + ST), tự động bù trừ số dư làm tròn để tổng = Amount
        if ($taxRate > 0.0) {
            $taxAmount = round($amount - ($netTotal + $serviceChargeAmount + $specialTaxAmount), $precision);
        } else {
            $taxAmount = 0.0;
            // Nếu không có VAT, bù trừ chênh lệch làm tròn vào ST, SC hoặc Net
            if ($stRate > 0.0) {
                $specialTaxAmount = round($amount - ($netTotal + $serviceChargeAmount), $precision);
            } elseif ($scRate > 0.0) {
                $serviceChargeAmount = round($amount - $netTotal, $precision);
            } else {
                $netTotal = round($amount, $precision);
            }
        }

        // Đơn giá cho 1 đơn vị theo số lượng (Quantity)
        $qty = abs($quantity) > 0 ? (float) $quantity : 1.0;
        $originalRate = round($netTotal / $qty, $precision);

        return [
            'net_total'             => $netTotal,
            'original_rate'         => $originalRate,
            'service_charge_amount' => $serviceChargeAmount,
            'special_tax_amount'    => $specialTaxAmount,
            'tax_amount'            => $taxAmount,
            'amount'                => round($amount, $precision),
        ];
    }
}

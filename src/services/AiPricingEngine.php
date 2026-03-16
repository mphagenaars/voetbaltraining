<?php
declare(strict_types=1);

class AiPricingEngine {
    public function calculate(array $pricingRule, int $inputTokens, int $outputTokens): array {
        $inputPricePerMtoken = (float)($pricingRule['input_price_per_mtoken'] ?? 0.0);
        $outputPricePerMtoken = (float)($pricingRule['output_price_per_mtoken'] ?? 0.0);
        $requestFlatPrice = (float)($pricingRule['request_flat_price'] ?? 0.0);
        $minRequestPrice = (float)($pricingRule['min_request_price'] ?? 0.0);

        $rawBillable = $requestFlatPrice
            + (($inputTokens / 1_000_000) * $inputPricePerMtoken)
            + (($outputTokens / 1_000_000) * $outputPricePerMtoken);

        $billable = max($rawBillable, $minRequestPrice);

        $rawBillable = round($rawBillable, 6);
        $billable = round($billable, 6);

        return [
            'raw_billable_eur' => $rawBillable,
            'billable_cost_eur' => $billable,
            'pricing_snapshot' => [
                'currency' => (string)($pricingRule['currency'] ?? 'EUR'),
                'input_price_per_mtoken' => $inputPricePerMtoken,
                'output_price_per_mtoken' => $outputPricePerMtoken,
                'request_flat_price' => $requestFlatPrice,
                'min_request_price' => $minRequestPrice,
            ],
        ];
    }
}

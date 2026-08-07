<?php

declare(strict_types=1);

namespace Ksfraser\Retirement;

/**
 * Tax Location Optimizer
 *
 * Optimizes asset location across different account types (registered, non-registered, tax-deferred)
 * to minimize overall tax burden and maximize after-tax returns.
 *
 * Single Responsibility: Optimize tax-efficient asset location strategies.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class TaxLocationOptimizer
{
    /**
     * Asset classes and their tax efficiency characteristics
     */
    private const ASSET_TAX_CHARACTERISTICS = [
        DiversificationCalculator::ASSET_CLASS_EQUITY => [
            'tax_efficiency' => 'medium',
            'registered_preference' => 'high', // Tax-deferred growth
            'non_registered_preference' => 'medium',
            'tax_deferred_preference' => 'high'
        ],
        DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => [
            'tax_efficiency' => 'low',
            'registered_preference' => 'low', // Taxable interest
            'non_registered_preference' => 'high',
            'tax_deferred_preference' => 'low'
        ],
        DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => [
            'tax_efficiency' => 'high',
            'registered_preference' => 'high', // Tax-deferred
            'non_registered_preference' => 'medium',
            'tax_deferred_preference' => 'high'
        ],
        DiversificationCalculator::ASSET_CLASS_CASH => [
            'tax_efficiency' => 'low',
            'registered_preference' => 'low', // Taxable interest
            'non_registered_preference' => 'high',
            'tax_deferred_preference' => 'low'
        ]
    ];

    /**
     * Tax bracket efficiency multipliers
     */
    private const TAX_BRACKET_MULTIPLIERS = [
        AssetEfficiencyEngine::TAX_BRACKET_LOW => 1.0,
        AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 1.2,
        AssetEfficiencyEngine::TAX_BRACKET_HIGH => 1.5
    ];

    /**
     * Optimize tax location for portfolio assets
     *
     * @param array $portfolio Current portfolio assets
     * @param array $taxSituation Tax situation data
     * @return array Tax location optimization results
     */
    public function optimizeTaxLocation(array $portfolio, array $taxSituation): array
    {
        $currentAllocation = $this->analyzeCurrentAllocation($portfolio);
        $optimalAllocation = $this->calculateOptimalAllocation($portfolio, $taxSituation);
        $reallocationRecommendations = $this->generateReallocationRecommendations(
            $currentAllocation,
            $optimalAllocation,
            $taxSituation
        );
        $taxSavings = $this->calculateTaxSavings($currentAllocation, $optimalAllocation, $taxSituation);
        $efficiencyScore = $this->calculateEfficiencyScore($optimalAllocation, $taxSituation);

        return [
            'current_allocation' => $currentAllocation,
            'optimal_allocation' => $optimalAllocation,
            'reallocation_recommendations' => $reallocationRecommendations,
            'estimated_savings' => $taxSavings,
            'efficiency_score' => $efficiencyScore,
            'tax_bracket_impact' => $this->assessTaxBracketImpact($taxSituation),
            'recommendations' => $this->generateTaxLocationRecommendations(
                $reallocationRecommendations,
                $taxSavings,
                $efficiencyScore,
                $taxSituation
            )
        ];
    }

    /**
     * Analyze current allocation across account types
     *
     * @param array $portfolio Portfolio assets
     * @return array Current allocation analysis
     */
    private function analyzeCurrentAllocation(array $portfolio): array
    {
        $allocation = [
            AssetEfficiencyEngine::ACCOUNT_REGISTERED => ['value' => 0.0, 'assets' => []],
            AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => ['value' => 0.0, 'assets' => []],
            AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => ['value' => 0.0, 'assets' => []]
        ];

        $totalValue = array_sum(array_column($portfolio, 'value'));

        foreach ($portfolio as $asset) {
            $accountType = $asset['account_type'] ?? AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED;
            $value = $asset['value'] ?? 0.0;

            if (isset($allocation[$accountType])) {
                $allocation[$accountType]['value'] += $value;
                $allocation[$accountType]['assets'][] = $asset;
            }
        }

        // Calculate percentages
        foreach ($allocation as $accountType => &$data) {
            $data['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
            $data['tax_efficiency'] = $this->calculateAccountTaxEfficiency($data['assets'], $accountType);
        }

        return $allocation;
    }

    /**
     * Calculate optimal allocation based on tax situation
     *
     * @param array $portfolio Portfolio assets
     * @param array $taxSituation Tax situation
     * @return array Optimal allocation
     */
    private function calculateOptimalAllocation(array $portfolio, array $taxSituation): array
    {
        $taxBracket = $taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM;
        $bracketMultiplier = self::TAX_BRACKET_MULTIPLIERS[$taxBracket] ?? 1.0;

        $optimal = [
            AssetEfficiencyEngine::ACCOUNT_REGISTERED => ['value' => 0.0, 'assets' => []],
            AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => ['value' => 0.0, 'assets' => []],
            AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => ['value' => 0.0, 'assets' => []]
        ];

        foreach ($portfolio as $asset) {
            $assetClass = $asset['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;
            $value = $asset['value'] ?? 0.0;

            $preferences = self::ASSET_TAX_CHARACTERISTICS[$assetClass] ?? self::ASSET_TAX_CHARACTERISTICS[DiversificationCalculator::ASSET_CLASS_EQUITY];

            // Calculate preference scores adjusted for tax bracket
            $registeredScore = $this->calculatePreferenceScore($preferences['registered_preference']) * $bracketMultiplier;
            $nonRegisteredScore = $this->calculatePreferenceScore($preferences['non_registered_preference']);
            $taxDeferredScore = $this->calculatePreferenceScore($preferences['tax_deferred_preference']) * $bracketMultiplier;

            // Determine optimal account type
            $scores = [
                AssetEfficiencyEngine::ACCOUNT_REGISTERED => $registeredScore,
                AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => $nonRegisteredScore,
                AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => $taxDeferredScore
            ];

            $optimalAccount = array_keys($scores, max($scores))[0];

            $optimal[$optimalAccount]['value'] += $value;
            $optimal[$optimalAccount]['assets'][] = array_merge($asset, [
                'optimal_account' => $optimalAccount,
                'preference_scores' => $scores
            ]);
        }

        // Calculate percentages
        $totalValue = array_sum(array_column($portfolio, 'value'));
        foreach ($optimal as &$data) {
            $data['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
        }

        return $optimal;
    }

    /**
     * Generate reallocation recommendations
     *
     * @param array $current Current allocation
     * @param array $optimal Optimal allocation
     * @param array $taxSituation Tax situation
     * @return array Reallocation recommendations
     */
    private function generateReallocationRecommendations(array $current, array $optimal, array $taxSituation): array
    {
        $recommendations = [];

        foreach ($optimal as $accountType => $optimalData) {
            $currentValue = $current[$accountType]['value'] ?? 0.0;
            $optimalValue = $optimalData['value'];
            $difference = $optimalValue - $currentValue;

            if (abs($difference) > 1000) { // Only recommend for significant differences
                $recommendations[] = [
                    'account_type' => $accountType,
                    'current_value' => $currentValue,
                    'optimal_value' => $optimalValue,
                    'difference' => $difference,
                    'action' => $difference > 0 ? 'increase' : 'decrease',
                    'priority' => $this->calculateReallocationPriority($accountType, $difference, $taxSituation),
                    'estimated_tax_impact' => $this->estimateReallocationTaxImpact($difference, $accountType, $taxSituation)
                ];
            }
        }

        // Sort by priority (high to low)
        usort($recommendations, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $recommendations;
    }

    /**
     * Calculate tax savings from optimal allocation
     *
     * @param array $current Current allocation
     * @param array $optimal Optimal allocation
     * @param array $taxSituation Tax situation
     * @return float Estimated tax savings
     */
    private function calculateTaxSavings(array $current, array $optimal, array $taxSituation): float
    {
        $currentEfficiency = $this->calculatePortfolioTaxEfficiency($current, $taxSituation);
        $optimalEfficiency = $this->calculatePortfolioTaxEfficiency($optimal, $taxSituation);

        // Assume 7% annual return for savings calculation
        $annualReturn = 0.07;
        $timeHorizon = 20; // 20-year projection

        $currentAfterTax = $this->calculateAfterTaxValue($currentEfficiency, $annualReturn, $timeHorizon);
        $optimalAfterTax = $this->calculateAfterTaxValue($optimalEfficiency, $annualReturn, $timeHorizon);

        return $optimalAfterTax - $currentAfterTax;
    }

    /**
     * Calculate efficiency score for optimal allocation
     *
     * @param array $optimal Optimal allocation
     * @param array $taxSituation Tax situation
     * @return float Efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $optimal, array $taxSituation): float
    {
        $efficiency = $this->calculatePortfolioTaxEfficiency($optimal, $taxSituation);

        // Convert efficiency ratio to score (0-100)
        // Efficiency > 1.0 is good (more after-tax value), < 1.0 is poor
        if ($efficiency >= 1.0) {
            return min(100.0, $efficiency * 50.0); // Cap at 100
        } else {
            return max(0.0, $efficiency * 100.0); // Minimum 0
        }
    }

    /**
     * Assess tax bracket impact on optimization
     *
     * @param array $taxSituation Tax situation
     * @return array Tax bracket impact analysis
     */
    private function assessTaxBracketImpact(array $taxSituation): array
    {
        $bracket = $taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM;

        $impact = [
            'tax_bracket' => $bracket,
            'optimization_importance' => match($bracket) {
                AssetEfficiencyEngine::TAX_BRACKET_HIGH => 'critical',
                AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 'important',
                AssetEfficiencyEngine::TAX_BRACKET_LOW => 'moderate',
                default => 'moderate'
            },
            'registered_account_benefit' => match($bracket) {
                AssetEfficiencyEngine::TAX_BRACKET_HIGH => 'maximum',
                AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 'significant',
                AssetEfficiencyEngine::TAX_BRACKET_LOW => 'moderate',
                default => 'moderate'
            }
        ];

        return $impact;
    }

    /**
     * Generate tax location recommendations
     *
     * @param array $reallocations Reallocation recommendations
     * @param float $taxSavings Estimated tax savings
     * @param float $efficiencyScore Efficiency score
     * @param array $taxSituation Tax situation
     * @return array Tax location recommendations
     */
    private function generateTaxLocationRecommendations(
        array $reallocations,
        float $taxSavings,
        float $efficiencyScore,
        array $taxSituation
    ): array {
        $recommendations = [];

        if ($efficiencyScore < 60.0) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'immediate_optimization',
                'message' => 'Tax location efficiency is low. Immediate reallocation recommended.',
                'action_items' => [
                    'Review current asset locations',
                    'Execute recommended reallocations',
                    'Consider tax-loss harvesting opportunities',
                    'Consult tax advisor for complex situations'
                ]
            ];
        } elseif ($efficiencyScore < 80.0) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'gradual_optimization',
                'message' => 'Tax location can be improved with strategic reallocations.',
                'action_items' => [
                    'Implement high-priority reallocations first',
                    'Use new contributions for optimal placement',
                    'Monitor and adjust annually'
                ]
            ];
        } else {
            $recommendations[] = [
                'priority' => 'low',
                'type' => 'maintenance',
                'message' => 'Tax location strategy is well-optimized.',
                'action_items' => [
                    'Continue current allocation strategy',
                    'Review annually for changes in tax situation',
                    'Monitor new tax legislation'
                ]
            ];
        }

        if ($taxSavings > 50000) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'significant_savings',
                'message' => 'Significant tax savings opportunity identified.',
                'action_items' => [
                    'Prioritize implementation of recommendations',
                    'Consider professional tax advice',
                    'Document tax strategy rationale'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate account tax efficiency
     *
     * @param array $assets Assets in account
     * @param string $accountType Account type
     * @return float Tax efficiency ratio
     */
    private function calculateAccountTaxEfficiency(array $assets, string $accountType): float
    {
        if (empty($assets)) {
            return 1.0;
        }

        $totalEfficiency = 0.0;
        foreach ($assets as $asset) {
            $assetClass = $asset['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;
            $characteristics = self::ASSET_TAX_CHARACTERISTICS[$assetClass] ?? self::ASSET_TAX_CHARACTERISTICS[DiversificationCalculator::ASSET_CLASS_EQUITY];

            $efficiency = match($accountType) {
                AssetEfficiencyEngine::ACCOUNT_REGISTERED => 1.3, // Tax-deferred
                AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => match($characteristics['tax_efficiency']) {
                    'high' => 1.2,
                    'medium' => 1.0,
                    'low' => 0.7,
                    default => 1.0
                },
                AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => 1.25, // Tax-deferred
                default => 1.0
            };

            $totalEfficiency += $efficiency;
        }

        return $totalEfficiency / count($assets);
    }

    /**
     * Calculate preference score from preference level
     *
     * @param string $preference Preference level
     * @return float Preference score
     */
    private function calculatePreferenceScore(string $preference): float
    {
        return match($preference) {
            'high' => 3.0,
            'medium' => 2.0,
            'low' => 1.0,
            default => 1.0
        };
    }

    /**
     * Calculate reallocation priority
     *
     * @param string $accountType Account type
     * @param float $difference Value difference
     * @param array $taxSituation Tax situation
     * @return int Priority score (1-10)
     */
    private function calculateReallocationPriority(string $accountType, float $difference, array $taxSituation): int
    {
        $bracket = $taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM;

        $basePriority = match($accountType) {
            AssetEfficiencyEngine::ACCOUNT_REGISTERED => 8,
            AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => 7,
            AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => 5,
            default => 5
        };

        // Higher priority for higher tax brackets
        $bracketMultiplier = match($bracket) {
            AssetEfficiencyEngine::TAX_BRACKET_HIGH => 1.5,
            AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 1.2,
            AssetEfficiencyEngine::TAX_BRACKET_LOW => 1.0,
            default => 1.0
        };

        // Larger differences get higher priority
        $sizeMultiplier = min(2.0, abs($difference) / 50000);

        return (int) min(10, $basePriority * $bracketMultiplier * $sizeMultiplier);
    }

    /**
     * Estimate tax impact of reallocation
     *
     * @param float $difference Value difference
     * @param string $accountType Target account type
     * @param array $taxSituation Tax situation
     * @return float Estimated tax impact
     */
    private function estimateReallocationTaxImpact(float $difference, string $accountType, array $taxSituation): float
    {
        if ($difference <= 0) {
            return 0.0; // No tax impact for reductions
        }

        $taxRate = match($taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM) {
            AssetEfficiencyEngine::TAX_BRACKET_HIGH => 0.45,
            AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 0.35,
            AssetEfficiencyEngine::TAX_BRACKET_LOW => 0.25,
            default => 0.35
        };

        // Simplified: assume 20% of reallocated amount is taxable
        return $difference * 0.20 * $taxRate;
    }

    /**
     * Calculate portfolio tax efficiency
     *
     * @param array $allocation Account allocation
     * @param array $taxSituation Tax situation
     * @return float Portfolio tax efficiency ratio
     */
    private function calculatePortfolioTaxEfficiency(array $allocation, array $taxSituation): float
    {
        $totalValue = array_sum(array_column($allocation, 'value'));
        if ($totalValue == 0) {
            return 1.0;
        }

        $weightedEfficiency = 0.0;
        foreach ($allocation as $accountType => $data) {
            $weight = $data['value'] / $totalValue;
            $efficiency = $data['tax_efficiency'] ?? 1.0;
            $weightedEfficiency += $weight * $efficiency;
        }

        return $weightedEfficiency;
    }

    /**
     * Calculate after-tax value projection
     *
     * @param float $efficiency Tax efficiency ratio
     * @param float $annualReturn Annual return rate
     * @param int $years Time horizon in years
     * @return float Projected after-tax value
     */
    private function calculateAfterTaxValue(float $efficiency, float $annualReturn, int $years): float
    {
        // Simplified compound growth calculation
        $growthFactor = pow(1 + ($annualReturn * $efficiency), $years);
        return 100000.0 * $growthFactor; // Assume $100K base for comparison
    }
}
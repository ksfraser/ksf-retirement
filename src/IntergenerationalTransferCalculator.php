<?php

declare(strict_types=1);

namespace Ksfraser\Retirement;

/**
 * Intergenerational Transfer Calculator
 *
 * Calculates optimal strategies for transferring wealth between generations,
 * including estate taxes, lifetime gifts, and trust structures.
 *
 * Single Responsibility: Calculate intergenerational transfer efficiency.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class IntergenerationalTransferCalculator
{
    /**
     * Transfer methods
     */
    public const METHOD_OUTRIGHT_GIFT = 'outright_gift';
    public const METHOD_LIFETIME_TRUST = 'lifetime_trust';
    public const METHOD_TESTAMENTARY_TRUST = 'testamentary_trust';
    public const METHOD_LIFE_INSURANCE = 'life_insurance';
    public const METHOD_BUSINESS_INTERESTS = 'business_interests';

    /**
     * Canadian estate tax rates (federal + average provincial)
     */
    private const ESTATE_TAX_RATES = [
        'low' => 0.48,    // 0-200K estate
        'medium' => 0.52, // 200K-1M estate
        'high' => 0.55    // 1M+ estate
    ];

    /**
     * Annual gift tax exemption (2025 values)
     */
    private const ANNUAL_GIFT_EXEMPTION = 34200; // $34,200 CAD

    /**
     * Lifetime capital gains exemption (2025 values)
     */
    private const LIFETIME_CG_EXEMPTION = 971190; // $971,190 CAD

    /**
     * Calculate transfer efficiency for intergenerational wealth transfer
     *
     * @param array $portfolio Portfolio assets
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon for transfer
     * @return array Transfer efficiency analysis
     */
    public function calculateTransferEfficiency(array $portfolio, array $taxSituation, int $timeHorizon): array
    {
        $estateValue = $this->calculateEstateValue($portfolio);
        $transferStrategies = $this->evaluateTransferStrategies($estateValue, $taxSituation, $timeHorizon);
        $optimalStrategy = $this->determineOptimalStrategy($transferStrategies, $timeHorizon);
        $taxEfficiency = $this->calculateTaxEfficiency($transferStrategies, $estateValue);
        $generationalImpact = $this->assessGenerationalImpact($transferStrategies, $timeHorizon);

        return [
            'estate_value' => $estateValue,
            'time_horizon' => $timeHorizon,
            'transfer_strategies' => $transferStrategies,
            'optimal_strategy' => $optimalStrategy,
            'tax_efficiency' => $taxEfficiency,
            'generational_impact' => $generationalImpact,
            'efficiency_score' => $this->calculateOverallEfficiencyScore($taxEfficiency, $generationalImpact),
            'estimated_savings' => $this->calculateTotalEstimatedSavings($transferStrategies, $optimalStrategy),
            'recommendations' => $this->generateTransferRecommendations(
                $optimalStrategy,
                $transferStrategies,
                $taxEfficiency,
                $timeHorizon
            )
        ];
    }

    /**
     * Calculate total estate value
     *
     * @param array $portfolio Portfolio assets
     * @return array Estate value breakdown
     */
    private function calculateEstateValue(array $portfolio): array
    {
        $totalValue = array_sum(array_column($portfolio, 'value'));
        $taxableValue = $this->calculateTaxableEstate($portfolio);
        $exemptValue = $totalValue - $taxableValue;

        return [
            'total_value' => $totalValue,
            'taxable_value' => $taxableValue,
            'exempt_value' => $exemptValue,
            'estimated_estate_tax' => $this->calculateEstateTax($taxableValue),
            'after_tax_value' => $taxableValue - $this->calculateEstateTax($taxableValue)
        ];
    }

    /**
     * Calculate taxable estate value
     *
     * @param array $portfolio Portfolio assets
     * @return float Taxable estate value
     */
    private function calculateTaxableEstate(array $portfolio): float
    {
        $totalValue = array_sum(array_column($portfolio, 'value'));

        // Simplified: assume 50% of estate is exempt (spouse, charity, etc.)
        $exemptions = min($totalValue * 0.5, 1000000); // Up to $1M exemption

        return max(0, $totalValue - $exemptions);
    }

    /**
     * Calculate estate tax
     *
     * @param float $taxableValue Taxable estate value
     * @return float Estate tax amount
     */
    private function calculateEstateTax(float $taxableValue): float
    {
        if ($taxableValue <= 200000) {
            return $taxableValue * self::ESTATE_TAX_RATES['low'];
        } elseif ($taxableValue <= 1000000) {
            return $taxableValue * self::ESTATE_TAX_RATES['medium'];
        } else {
            return $taxableValue * self::ESTATE_TAX_RATES['high'];
        }
    }

    /**
     * Evaluate different transfer strategies
     *
     * @param array $estateValue Estate value information
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Transfer strategy evaluations
     */
    private function evaluateTransferStrategies(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        $strategies = [];

        // Outright gift strategy
        $strategies[self::METHOD_OUTRIGHT_GIFT] = $this->evaluateOutrightGift(
            $estateValue,
            $taxSituation,
            $timeHorizon
        );

        // Lifetime trust strategy
        $strategies[self::METHOD_LIFETIME_TRUST] = $this->evaluateLifetimeTrust(
            $estateValue,
            $taxSituation,
            $timeHorizon
        );

        // Testamentary trust strategy
        $strategies[self::METHOD_TESTAMENTARY_TRUST] = $this->evaluateTestamentaryTrust(
            $estateValue,
            $taxSituation,
            $timeHorizon
        );

        // Life insurance strategy
        $strategies[self::METHOD_LIFE_INSURANCE] = $this->evaluateLifeInsurance(
            $estateValue,
            $taxSituation,
            $timeHorizon
        );

        // Business interests strategy
        $strategies[self::METHOD_BUSINESS_INTERESTS] = $this->evaluateBusinessInterests(
            $estateValue,
            $taxSituation,
            $timeHorizon
        );

        return $strategies;
    }

    /**
     * Evaluate outright gift strategy
     *
     * @param array $estateValue Estate value
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Strategy evaluation
     */
    private function evaluateOutrightGift(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        $annualExemption = self::ANNUAL_GIFT_EXEMPTION;
        $totalTransferable = $annualExemption * $timeHorizon;
        $actualTransfer = min($totalTransferable, $estateValue['total_value']);

        $taxSavings = $this->calculateEstateTax($estateValue['taxable_value'] - $actualTransfer) -
                     $this->calculateEstateTax($estateValue['taxable_value']);

        return [
            'method' => self::METHOD_OUTRIGHT_GIFT,
            'transfer_amount' => $actualTransfer,
            'tax_savings' => max(0, $taxSavings),
            'efficiency_score' => $this->calculateStrategyEfficiency($actualTransfer, $taxSavings, $timeHorizon),
            'risk_level' => 'low',
            'complexity' => 'low',
            'control_retained' => 'none',
            'pros' => ['Simple to implement', 'Immediate tax savings', 'No ongoing costs'],
            'cons' => ['Loss of control', 'Potential gift tax issues', 'Limited amount transferable']
        ];
    }

    /**
     * Evaluate lifetime trust strategy
     *
     * @param array $estateValue Estate value
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Strategy evaluation
     */
    private function evaluateLifetimeTrust(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        // Assume 70% of estate can be transferred via trust
        $transferAmount = $estateValue['total_value'] * 0.7;
        $taxSavings = $this->calculateEstateTax($estateValue['taxable_value'] * 0.3); // Tax only on remaining 30%

        // Account for trust administration costs (2% annually)
        $annualCosts = $transferAmount * 0.02;
        $totalCosts = $annualCosts * $timeHorizon;

        return [
            'method' => self::METHOD_LIFETIME_TRUST,
            'transfer_amount' => $transferAmount,
            'tax_savings' => $taxSavings,
            'administration_costs' => $totalCosts,
            'net_benefit' => $taxSavings - $totalCosts,
            'efficiency_score' => $this->calculateStrategyEfficiency($transferAmount, $taxSavings - $totalCosts, $timeHorizon),
            'risk_level' => 'medium',
            'complexity' => 'high',
            'control_retained' => 'partial',
            'pros' => ['Significant tax savings', 'Retained control', 'Asset protection'],
            'cons' => ['High complexity', 'Ongoing costs', 'Professional management required']
        ];
    }

    /**
     * Evaluate testamentary trust strategy
     *
     * @param array $estateValue Estate value
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Strategy evaluation
     */
    private function evaluateTestamentaryTrust(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        // Testamentary trusts provide estate tax savings but no lifetime benefits
        $transferAmount = $estateValue['total_value'] * 0.8;
        $taxSavings = $this->calculateEstateTax($estateValue['taxable_value'] * 0.2); // Tax only on 20%

        return [
            'method' => self::METHOD_TESTAMENTARY_TRUST,
            'transfer_amount' => $transferAmount,
            'tax_savings' => $taxSavings,
            'efficiency_score' => $this->calculateStrategyEfficiency($transferAmount, $taxSavings, $timeHorizon),
            'risk_level' => 'low',
            'complexity' => 'medium',
            'control_retained' => 'partial',
            'pros' => ['Estate tax savings', 'Flexible distribution', 'No lifetime gift tax'],
            'cons' => ['No lifetime benefits', 'Requires estate planning', 'Potential income tax issues']
        ];
    }

    /**
     * Evaluate life insurance strategy
     *
     * @param array $estateValue Estate value
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Strategy evaluation
     */
    private function evaluateLifeInsurance(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        // Life insurance proceeds are generally tax-free
        $insuranceAmount = min($estateValue['taxable_value'] * 0.5, 2000000); // Up to $2M coverage
        $annualPremium = $insuranceAmount * 0.005; // 0.5% of coverage annually
        $totalPremiums = $annualPremium * min($timeHorizon, 20); // Max 20 years

        return [
            'method' => self::METHOD_LIFE_INSURANCE,
            'transfer_amount' => $insuranceAmount,
            'annual_premium' => $annualPremium,
            'total_cost' => $totalPremiums,
            'tax_savings' => $this->calculateEstateTax($insuranceAmount), // Tax savings on insurance proceeds
            'net_benefit' => $this->calculateEstateTax($insuranceAmount) - $totalPremiums,
            'efficiency_score' => $this->calculateStrategyEfficiency($insuranceAmount, $this->calculateEstateTax($insuranceAmount) - $totalPremiums, $timeHorizon),
            'risk_level' => 'medium',
            'complexity' => 'medium',
            'control_retained' => 'full',
            'pros' => ['Tax-free proceeds', 'Definite amount', 'Estate liquidity'],
            'cons' => ['Premium costs', 'Health requirements', 'Opportunity cost of premiums']
        ];
    }

    /**
     * Evaluate business interests strategy
     *
     * @param array $estateValue Estate value
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return array Strategy evaluation
     */
    private function evaluateBusinessInterests(array $estateValue, array $taxSituation, int $timeHorizon): array
    {
        // Assume 30% of estate is business interests eligible for special treatment
        $businessValue = $estateValue['total_value'] * 0.3;
        $transferAmount = $businessValue;

        // Business interests may qualify for enhanced exemptions
        $enhancedExemption = min($businessValue, 1000000); // Up to $1M additional exemption
        $taxSavings = $this->calculateEstateTax($enhancedExemption);

        return [
            'method' => self::METHOD_BUSINESS_INTERESTS,
            'transfer_amount' => $transferAmount,
            'tax_savings' => $taxSavings,
            'efficiency_score' => $this->calculateStrategyEfficiency($transferAmount, $taxSavings, $timeHorizon),
            'risk_level' => 'high',
            'complexity' => 'high',
            'control_retained' => 'partial',
            'pros' => ['Enhanced exemptions', 'Business continuity', 'Potential income tax deferral'],
            'cons' => ['Complex valuation', 'Business succession issues', 'Limited liquidity']
        ];
    }

    /**
     * Determine optimal transfer strategy
     *
     * @param array $strategies Transfer strategies
     * @param int $timeHorizon Time horizon
     * @return array Optimal strategy information
     */
    private function determineOptimalStrategy(array $strategies, int $timeHorizon): array
    {
        $bestStrategy = null;
        $bestScore = -1;

        foreach ($strategies as $strategy) {
            $score = $strategy['efficiency_score'];

            // Adjust score based on time horizon
            if ($timeHorizon >= 20 && in_array($strategy['method'], [self::METHOD_LIFETIME_TRUST, self::METHOD_LIFE_INSURANCE])) {
                $score *= 1.2; // Bonus for long-term strategies
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }

        return $bestStrategy ?? $strategies[self::METHOD_OUTRIGHT_GIFT];
    }

    /**
     * Calculate tax efficiency across strategies
     *
     * @param array $strategies Transfer strategies
     * @param array $estateValue Estate value
     * @return array Tax efficiency analysis
     */
    private function calculateTaxEfficiency(array $strategies, array $estateValue): array
    {
        $baselineTax = $estateValue['estimated_estate_tax'];
        $efficiencyScores = [];

        foreach ($strategies as $strategy) {
            $netSavings = $strategy['net_benefit'] ?? $strategy['tax_savings'];
            $efficiency = $baselineTax > 0 ? ($netSavings / $baselineTax) * 100 : 0;
            $efficiencyScores[$strategy['method']] = max(0, min(100, $efficiency));
        }

        $averageEfficiency = array_sum($efficiencyScores) / count($efficiencyScores);

        return [
            'baseline_estate_tax' => $baselineTax,
            'strategy_efficiencies' => $efficiencyScores,
            'average_efficiency' => $averageEfficiency,
            'efficiency_rating' => $this->rateEfficiency($averageEfficiency)
        ];
    }

    /**
     * Assess generational impact
     *
     * @param array $strategies Transfer strategies
     * @param int $timeHorizon Time horizon
     * @return array Generational impact analysis
     */
    private function assessGenerationalImpact(array $strategies, int $timeHorizon): array
    {
        $impact = [
            'immediate_transfer' => 0.0,
            'deferred_transfer' => 0.0,
            'multi_generational_benefit' => 0.0,
            'compounding_advantage' => 0.0
        ];

        foreach ($strategies as $strategy) {
            $transferAmount = $strategy['transfer_amount'];

            if (in_array($strategy['method'], [self::METHOD_OUTRIGHT_GIFT, self::METHOD_LIFETIME_TRUST])) {
                $impact['immediate_transfer'] += $transferAmount * 0.6;
                $impact['deferred_transfer'] += $transferAmount * 0.4;
            } else {
                $impact['deferred_transfer'] += $transferAmount;
            }
        }

        // Calculate compounding advantage (simplified)
        $compoundingYears = max(0, $timeHorizon - 10); // Assume 10 years to transfer
        $impact['compounding_advantage'] = $impact['deferred_transfer'] * pow(1.07, $compoundingYears) - $impact['deferred_transfer'];

        $impact['multi_generational_benefit'] = $impact['compounding_advantage'] * 1.5; // Additional benefit for multiple generations

        return $impact;
    }

    /**
     * Calculate overall efficiency score
     *
     * @param array $taxEfficiency Tax efficiency analysis
     * @param array $generationalImpact Generational impact
     * @return float Overall efficiency score (0-100)
     */
    private function calculateOverallEfficiencyScore(array $taxEfficiency, array $generationalImpact): float
    {
        $taxScore = $taxEfficiency['average_efficiency'];
        $impactScore = ($generationalImpact['compounding_advantage'] / 100000) * 10; // Normalize to 0-100 scale

        return min(100, ($taxScore + $impactScore) / 2);
    }

    /**
     * Calculate total estimated savings
     *
     * @param array $strategies Transfer strategies
     * @param array $optimalStrategy Optimal strategy
     * @return float Total estimated savings
     */
    private function calculateTotalEstimatedSavings(array $strategies, array $optimalStrategy): float
    {
        return $optimalStrategy['net_benefit'] ?? $optimalStrategy['tax_savings'];
    }

    /**
     * Generate transfer recommendations
     *
     * @param array $optimalStrategy Optimal strategy
     * @param array $strategies All strategies
     * @param array $taxEfficiency Tax efficiency analysis
     * @param int $timeHorizon Time horizon
     * @return array Transfer recommendations
     */
    private function generateTransferRecommendations(
        array $optimalStrategy,
        array $strategies,
        array $taxEfficiency,
        int $timeHorizon
    ): array {
        $recommendations = [];

        $recommendations[] = [
            'priority' => 'high',
            'type' => 'optimal_strategy',
            'message' => "Recommended transfer method: {$optimalStrategy['method']}",
            'action_items' => [
                'Consult estate planning professional',
                'Implement ' . $optimalStrategy['method'] . ' strategy',
                'Review beneficiary designations',
                'Update will and powers of attorney'
            ]
        ];

        if ($timeHorizon >= 20) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'long_term_planning',
                'message' => 'Long time horizon allows for sophisticated transfer strategies.',
                'action_items' => [
                    'Consider lifetime trusts for maximum tax efficiency',
                    'Evaluate life insurance for estate liquidity',
                    'Plan for multi-generational wealth transfer'
                ]
            ];
        }

        if ($taxEfficiency['efficiency_rating'] === 'poor') {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'tax_efficiency_improvement',
                'message' => 'Current estate plan has significant tax inefficiencies.',
                'action_items' => [
                    'Prioritize tax-efficient transfer strategies',
                    'Consider professional tax advice',
                    'Review asset titling and ownership'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate strategy efficiency score
     *
     * @param float $transferAmount Transfer amount
     * @param float $netBenefit Net tax benefit
     * @param int $timeHorizon Time horizon
     * @return float Efficiency score (0-100)
     */
    private function calculateStrategyEfficiency(float $transferAmount, float $netBenefit, int $timeHorizon): float
    {
        if ($transferAmount == 0) {
            return 0.0;
        }

        $efficiencyRatio = $netBenefit / $transferAmount;

        // Adjust for time horizon (longer horizons allow more sophisticated strategies)
        $timeBonus = min(0.2, $timeHorizon / 100); // Up to 20% bonus

        $score = ($efficiencyRatio * 100) + ($timeBonus * 100);

        return max(0.0, min(100.0, $score));
    }

    /**
     * Rate efficiency level
     *
     * @param float $efficiency Efficiency percentage
     * @return string Efficiency rating
     */
    private function rateEfficiency(float $efficiency): string
    {
        return match(true) {
            $efficiency >= 70 => 'excellent',
            $efficiency >= 50 => 'good',
            $efficiency >= 30 => 'fair',
            default => 'poor'
        };
    }
}
<?php

declare(strict_types=1);

namespace Ksfraser\Retirement;

/**
 * Withdrawal Sequencing Engine
 *
 * Optimizes withdrawal sequencing from different account types to minimize taxes
 * and maximize after-tax income during retirement or other withdrawal periods.
 *
 * Single Responsibility: Optimize withdrawal sequencing strategies.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class WithdrawalSequencingEngine
{
    /**
     * Withdrawal strategies
     */
    public const STRATEGY_TAX_EFFICIENT = 'tax_efficient';
    public const STRATEGY_MINIMUM_TAX = 'minimum_tax';
    public const STRATEGY_MAXIMUM_INCOME = 'maximum_income';
    public const STRATEGY_PRESERVATION = 'preservation';

    /**
     * Account withdrawal priorities (lower number = withdraw first)
     */
    private const WITHDRAWAL_PRIORITIES = [
        AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => 1, // Withdraw taxable accounts first
        AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => 2,   // Then tax-deferred
        AssetEfficiencyEngine::ACCOUNT_REGISTERED => 3      // Finally tax-free
    ];

    /**
     * Tax efficiency by account type and asset class
     */
    private const TAX_EFFICIENCY_MATRIX = [
        AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => [
            DiversificationCalculator::ASSET_CLASS_EQUITY => 0.7,     // Capital gains tax
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.6, // Interest income
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.8,  // Mixed taxation
            DiversificationCalculator::ASSET_CLASS_CASH => 0.5         // Interest income
        ],
        AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => [
            DiversificationCalculator::ASSET_CLASS_EQUITY => 0.9,     // Tax-deferred growth
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.8,
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.95,
            DiversificationCalculator::ASSET_CLASS_CASH => 0.7
        ],
        AssetEfficiencyEngine::ACCOUNT_REGISTERED => [
            DiversificationCalculator::ASSET_CLASS_EQUITY => 1.0,     // Tax-free
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 1.0,
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 1.0,
            DiversificationCalculator::ASSET_CLASS_CASH => 1.0
        ]
    ];

    /**
     * Optimize withdrawal sequence
     *
     * @param array $portfolio Portfolio assets
     * @param float|null $annualWithdrawal Annual withdrawal amount
     * @param int $timeHorizon Time horizon in years
     * @param array $taxSituation Tax situation
     * @return array Withdrawal optimization results
     */
    public function optimizeWithdrawalSequence(
        array $portfolio,
        ?float $annualWithdrawal,
        int $timeHorizon,
        array $taxSituation
    ): array {
        $accountBalances = $this->calculateAccountBalances($portfolio);
        $withdrawalStrategy = $this->determineOptimalStrategy($taxSituation, $timeHorizon);

        if ($annualWithdrawal === null) {
            $annualWithdrawal = $this->estimateRequiredWithdrawal($accountBalances, $timeHorizon);
        }

        $withdrawalSequence = $this->calculateWithdrawalSequence(
            $accountBalances,
            $annualWithdrawal,
            $timeHorizon,
            $withdrawalStrategy,
            $taxSituation
        );

        $taxImpact = $this->calculateTaxImpact($withdrawalSequence, $taxSituation);
        $sustainability = $this->assessSustainability($accountBalances, $annualWithdrawal, $timeHorizon);
        $efficiencyScore = $this->calculateEfficiencyScore($withdrawalSequence, $taxImpact);

        return [
            'withdrawal_strategy' => $withdrawalStrategy,
            'annual_withdrawal_amount' => $annualWithdrawal,
            'time_horizon' => $timeHorizon,
            'account_balances' => $accountBalances,
            'withdrawal_sequence' => $withdrawalSequence,
            'tax_impact' => $taxImpact,
            'sustainability_assessment' => $sustainability,
            'efficiency_score' => $efficiencyScore,
            'estimated_savings' => $taxImpact['total_tax_savings'] ?? 0.0,
            'recommendations' => $this->generateWithdrawalRecommendations(
                $withdrawalStrategy,
                $withdrawalSequence,
                $taxImpact,
                $sustainability,
                $timeHorizon
            )
        ];
    }

    /**
     * Calculate current account balances
     *
     * @param array $portfolio Portfolio assets
     * @return array Account balances
     */
    private function calculateAccountBalances(array $portfolio): array
    {
        $balances = [
            AssetEfficiencyEngine::ACCOUNT_REGISTERED => 0.0,
            AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED => 0.0,
            AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED => 0.0
        ];

        foreach ($portfolio as $asset) {
            $accountType = $asset['account_type'] ?? AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED;
            $value = $asset['value'] ?? 0.0;

            if (isset($balances[$accountType])) {
                $balances[$accountType] += $value;
            }
        }

        return $balances;
    }

    /**
     * Determine optimal withdrawal strategy
     *
     * @param array $taxSituation Tax situation
     * @param int $timeHorizon Time horizon
     * @return string Optimal strategy
     */
    private function determineOptimalStrategy(array $taxSituation, int $timeHorizon): string
    {
        $taxBracket = $taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM;

        // High tax bracket favors tax-efficient strategies
        if ($taxBracket === AssetEfficiencyEngine::TAX_BRACKET_HIGH) {
            return self::STRATEGY_TAX_EFFICIENT;
        }

        // Long time horizons favor preservation strategies
        if ($timeHorizon >= 30) {
            return self::STRATEGY_PRESERVATION;
        }

        // Medium time horizons favor minimum tax
        if ($timeHorizon >= 15) {
            return self::STRATEGY_MINIMUM_TAX;
        }

        // Short time horizons favor maximum income
        return self::STRATEGY_MAXIMUM_INCOME;
    }

    /**
     * Estimate required annual withdrawal amount
     *
     * @param array $accountBalances Current account balances
     * @param int $timeHorizon Time horizon
     * @return float Estimated annual withdrawal
     */
    private function estimateRequiredWithdrawal(array $accountBalances, int $timeHorizon): float
    {
        $totalBalance = array_sum($accountBalances);

        if ($totalBalance == 0 || $timeHorizon == 0) {
            return 0.0;
        }

        // Use 4% safe withdrawal rate as default
        $safeWithdrawalRate = 0.04;

        // Adjust for time horizon (more conservative for longer periods)
        if ($timeHorizon > 30) {
            $safeWithdrawalRate = 0.035;
        } elseif ($timeHorizon < 10) {
            $safeWithdrawalRate = 0.05;
        }

        return $totalBalance * $safeWithdrawalRate;
    }

    /**
     * Calculate optimal withdrawal sequence
     *
     * @param array $accountBalances Account balances
     * @param float $annualWithdrawal Annual withdrawal amount
     * @param int $timeHorizon Time horizon
     * @param string $strategy Withdrawal strategy
     * @param array $taxSituation Tax situation
     * @return array Withdrawal sequence by year
     */
    private function calculateWithdrawalSequence(
        array $accountBalances,
        float $annualWithdrawal,
        int $timeHorizon,
        string $strategy,
        array $taxSituation
    ): array {
        $sequence = [];
        $remainingBalances = $accountBalances;

        for ($year = 1; $year <= $timeHorizon; $year++) {
            $yearlyWithdrawals = $this->calculateYearlyWithdrawals(
                $remainingBalances,
                $annualWithdrawal,
                $strategy,
                $taxSituation,
                $year,
                $timeHorizon
            );

            $sequence[$year] = [
                'year' => $year,
                'total_withdrawal' => array_sum(array_column($yearlyWithdrawals, 'amount')),
                'account_withdrawals' => $yearlyWithdrawals,
                'remaining_balances' => $remainingBalances
            ];

            // Update remaining balances
            foreach ($yearlyWithdrawals as $accountType => $withdrawal) {
                $remainingBalances[$accountType] -= $withdrawal['amount'];
                $remainingBalances[$accountType] = max(0, $remainingBalances[$accountType]);
            }
        }

        return $sequence;
    }

    /**
     * Calculate yearly withdrawals by account
     *
     * @param array $balances Current balances
     * @param float $totalWithdrawal Total withdrawal needed
     * @param string $strategy Withdrawal strategy
     * @param array $taxSituation Tax situation
     * @param int $currentYear Current year in sequence
     * @param int $totalYears Total years
     * @return array Withdrawals by account
     */
    private function calculateYearlyWithdrawals(
        array $balances,
        float $totalWithdrawal,
        string $strategy,
        array $taxSituation,
        int $currentYear,
        int $totalYears
    ): array {
        $withdrawals = [];
        $remainingWithdrawal = $totalWithdrawal;

        // Order accounts by withdrawal priority based on strategy
        $accountOrder = $this->getAccountWithdrawalOrder($strategy, $taxSituation);

        foreach ($accountOrder as $accountType) {
            if ($remainingWithdrawal <= 0) {
                break;
            }

            $available = $balances[$accountType] ?? 0.0;
            if ($available <= 0) {
                continue;
            }

            // Calculate withdrawal amount from this account
            $withdrawalAmount = min($available, $remainingWithdrawal);

            // Adjust based on strategy and year
            $withdrawalAmount = $this->adjustWithdrawalForStrategy(
                $withdrawalAmount,
                $accountType,
                $strategy,
                $currentYear,
                $totalYears,
                $taxSituation
            );

            if ($withdrawalAmount > 0) {
                $withdrawals[$accountType] = [
                    'amount' => $withdrawalAmount,
                    'tax_efficiency' => $this->calculateAccountTaxEfficiency($accountType, $balances, $taxSituation),
                    'percentage_of_total' => ($withdrawalAmount / $totalWithdrawal) * 100
                ];

                $remainingWithdrawal -= $withdrawalAmount;
            }
        }

        return $withdrawals;
    }

    /**
     * Get account withdrawal order based on strategy
     *
     * @param string $strategy Withdrawal strategy
     * @param array $taxSituation Tax situation
     * @return array Ordered account types
     */
    private function getAccountWithdrawalOrder(string $strategy, array $taxSituation): array
    {
        $baseOrder = array_keys(self::WITHDRAWAL_PRIORITIES);
        usort($baseOrder, fn($a, $b) => self::WITHDRAWAL_PRIORITIES[$a] <=> self::WITHDRAWAL_PRIORITIES[$b]);

        // Adjust order based on strategy
        switch ($strategy) {
            case self::STRATEGY_TAX_EFFICIENT:
                // Prioritize accounts with lowest tax impact
                return array_reverse($baseOrder); // Reverse to start with registered accounts

            case self::STRATEGY_MINIMUM_TAX:
                // Standard tax-efficient order
                return $baseOrder;

            case self::STRATEGY_MAXIMUM_INCOME:
                // Prioritize accounts that provide most income
                return [AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED, AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED, AssetEfficiencyEngine::ACCOUNT_REGISTERED];

            case self::STRATEGY_PRESERVATION:
                // Preserve tax-advantaged accounts
                return [AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED, AssetEfficiencyEngine::ACCOUNT_REGISTERED, AssetEfficiencyEngine::ACCOUNT_TAX_DEFERRED];

            default:
                return $baseOrder;
        }
    }

    /**
     * Adjust withdrawal amount based on strategy
     *
     * @param float $amount Base withdrawal amount
     * @param string $accountType Account type
     * @param string $strategy Withdrawal strategy
     * @param int $currentYear Current year
     * @param int $totalYears Total years
     * @param array $taxSituation Tax situation
     * @return float Adjusted withdrawal amount
     */
    private function adjustWithdrawalForStrategy(
        float $amount,
        string $accountType,
        string $strategy,
        int $currentYear,
        int $totalYears,
        array $taxSituation
    ): float {
        switch ($strategy) {
            case self::STRATEGY_PRESERVATION:
                // Reduce withdrawals from tax-advantaged accounts early
                if ($accountType === AssetEfficiencyEngine::ACCOUNT_REGISTERED && $currentYear <= $totalYears / 2) {
                    return $amount * 0.7;
                }
                break;

            case self::STRATEGY_MAXIMUM_INCOME:
                // Increase withdrawals from taxable accounts
                if ($accountType === AssetEfficiencyEngine::ACCOUNT_NON_REGISTERED) {
                    return $amount * 1.2;
                }
                break;
        }

        return $amount;
    }

    /**
     * Calculate tax impact of withdrawal sequence
     *
     * @param array $withdrawalSequence Withdrawal sequence
     * @param array $taxSituation Tax situation
     * @return array Tax impact analysis
     */
    private function calculateTaxImpact(array $withdrawalSequence, array $taxSituation): array
    {
        $totalTaxPaid = 0.0;
        $totalTaxSavings = 0.0;
        $yearlyTaxImpact = [];

        $taxRate = $this->getTaxRate($taxSituation);

        foreach ($withdrawalSequence as $year => $yearData) {
            $yearlyTax = 0.0;
            $yearlySavings = 0.0;

            foreach ($yearData['account_withdrawals'] as $accountType => $withdrawal) {
                $amount = $withdrawal['amount'];
                $efficiency = $withdrawal['tax_efficiency'];

                // Calculate tax paid on this withdrawal
                $taxPaid = $amount * (1 - $efficiency) * $taxRate;
                $yearlyTax += $taxPaid;

                // Calculate potential savings vs. worst-case scenario
                $worstCaseTax = $amount * (1 - 0.5) * $taxRate; // Assume 50% efficiency baseline
                $yearlySavings += max(0, $worstCaseTax - $taxPaid);
            }

            $yearlyTaxImpact[$year] = [
                'year' => $year,
                'tax_paid' => $yearlyTax,
                'tax_savings' => $yearlySavings,
                'effective_tax_rate' => $yearData['total_withdrawal'] > 0 ? ($yearlyTax / $yearData['total_withdrawal']) * 100 : 0
            ];

            $totalTaxPaid += $yearlyTax;
            $totalTaxSavings += $yearlySavings;
        }

        return [
            'total_tax_paid' => $totalTaxPaid,
            'total_tax_savings' => $totalTaxSavings,
            'average_effective_rate' => $this->calculateAverageEffectiveRate($withdrawalSequence),
            'yearly_breakdown' => $yearlyTaxImpact,
            'tax_efficiency_rating' => $this->rateTaxEfficiency($totalTaxSavings, $totalTaxPaid)
        ];
    }

    /**
     * Assess withdrawal sustainability
     *
     * @param array $accountBalances Account balances
     * @param float $annualWithdrawal Annual withdrawal
     * @param int $timeHorizon Time horizon
     * @return array Sustainability assessment
     */
    private function assessSustainability(array $accountBalances, float $annualWithdrawal, int $timeHorizon): array
    {
        $totalBalance = array_sum($accountBalances);
        $totalNeeded = $annualWithdrawal * $timeHorizon;

        $sustainabilityRatio = $totalBalance / $totalNeeded;

        $assessment = [
            'sustainability_ratio' => $sustainabilityRatio,
            'total_balance' => $totalBalance,
            'total_needed' => $totalNeeded,
            'shortfall' => max(0, $totalNeeded - $totalBalance),
            'rating' => $this->rateSustainability($sustainabilityRatio),
            'risk_level' => $this->assessRiskLevel($sustainabilityRatio, $timeHorizon)
        ];

        return $assessment;
    }

    /**
     * Calculate efficiency score
     *
     * @param array $withdrawalSequence Withdrawal sequence
     * @param array $taxImpact Tax impact analysis
     * @return float Efficiency score (0-100)
     */
    private function calculateEfficiencyScore(array $withdrawalSequence, array $taxImpact): float
    {
        $taxEfficiency = $taxImpact['tax_efficiency_rating'] ?? 'poor';
        $sustainabilityRatio = 1.0; // Would need to be passed in

        $taxScore = match($taxEfficiency) {
            'excellent' => 100,
            'good' => 80,
            'fair' => 60,
            'poor' => 40,
            default => 50
        };

        $sustainabilityScore = min(100, $sustainabilityRatio * 50);

        return ($taxScore + $sustainabilityScore) / 2;
    }

    /**
     * Generate withdrawal recommendations
     *
     * @param string $strategy Withdrawal strategy
     * @param array $sequence Withdrawal sequence
     * @param array $taxImpact Tax impact
     * @param array $sustainability Sustainability assessment
     * @param int $timeHorizon Time horizon
     * @return array Withdrawal recommendations
     */
    private function generateWithdrawalRecommendations(
        string $strategy,
        array $sequence,
        array $taxImpact,
        array $sustainability,
        int $timeHorizon
    ): array {
        $recommendations = [];

        // Strategy-specific recommendations
        switch ($strategy) {
            case self::STRATEGY_TAX_EFFICIENT:
                $recommendations[] = [
                    'priority' => 'high',
                    'type' => 'tax_optimization',
                    'message' => 'Tax-efficient withdrawal strategy implemented.',
                    'action_items' => [
                        'Follow the recommended withdrawal sequence',
                        'Consider Roth conversions if applicable',
                        'Monitor tax bracket changes annually'
                    ]
                ];
                break;

            case self::STRATEGY_PRESERVATION:
                $recommendations[] = [
                    'priority' => 'medium',
                    'type' => 'capital_preservation',
                    'message' => 'Focus on preserving tax-advantaged accounts.',
                    'action_items' => [
                        'Minimize withdrawals from registered accounts',
                        'Consider delaying Social Security benefits',
                        'Review strategy annually'
                    ]
                ];
                break;
        }

        // Sustainability recommendations
        if ($sustainability['rating'] === 'poor') {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'sustainability_concern',
                'message' => 'Portfolio may not sustain current withdrawal rate.',
                'action_items' => [
                    'Reduce annual withdrawal amount',
                    'Consider part-time work or delayed retirement',
                    'Consult financial advisor for sustainability analysis'
                ]
            ];
        }

        // Tax efficiency recommendations
        if (($taxImpact['tax_efficiency_rating'] ?? 'poor') === 'poor') {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'tax_efficiency_improvement',
                'message' => 'Tax efficiency can be improved.',
                'action_items' => [
                    'Review asset location strategy',
                    'Consider tax-loss harvesting',
                    'Consult tax professional for optimization'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate account tax efficiency
     *
     * @param string $accountType Account type
     * @param array $balances Account balances
     * @param array $taxSituation Tax situation
     * @return float Tax efficiency ratio (0-1)
     */
    private function calculateAccountTaxEfficiency(string $accountType, array $balances, array $taxSituation): float
    {
        // Simplified: use average efficiency across asset classes
        $efficiencies = self::TAX_EFFICIENCY_MATRIX[$accountType] ?? [0.5, 0.5, 0.5, 0.5];
        return array_sum($efficiencies) / count($efficiencies);
    }

    /**
     * Get tax rate from tax situation
     *
     * @param array $taxSituation Tax situation
     * @return float Tax rate
     */
    private function getTaxRate(array $taxSituation): float
    {
        return match($taxSituation['bracket'] ?? AssetEfficiencyEngine::TAX_BRACKET_MEDIUM) {
            AssetEfficiencyEngine::TAX_BRACKET_HIGH => 0.45,
            AssetEfficiencyEngine::TAX_BRACKET_MEDIUM => 0.35,
            AssetEfficiencyEngine::TAX_BRACKET_LOW => 0.25,
            default => 0.35
        };
    }

    /**
     * Calculate average effective tax rate
     *
     * @param array $withdrawalSequence Withdrawal sequence
     * @return float Average effective rate
     */
    private function calculateAverageEffectiveRate(array $withdrawalSequence): float
    {
        $totalWithdrawal = 0.0;
        $totalTax = 0.0;

        foreach ($withdrawalSequence as $yearData) {
            $totalWithdrawal += $yearData['total_withdrawal'];
            foreach ($yearData['account_withdrawals'] as $withdrawal) {
                $amount = $withdrawal['amount'];
                $efficiency = $withdrawal['tax_efficiency'];
                $totalTax += $amount * (1 - $efficiency) * 0.35; // Assume 35% tax rate
            }
        }

        return $totalWithdrawal > 0 ? ($totalTax / $totalWithdrawal) * 100 : 0;
    }

    /**
     * Rate tax efficiency
     *
     * @param float $savings Tax savings
     * @param float $taxPaid Tax paid
     * @return string Efficiency rating
     */
    private function rateTaxEfficiency(float $savings, float $taxPaid): string
    {
        if ($taxPaid == 0) {
            return 'excellent';
        }

        $efficiencyRatio = $savings / $taxPaid;

        return match(true) {
            $efficiencyRatio >= 0.5 => 'excellent',
            $efficiencyRatio >= 0.25 => 'good',
            $efficiencyRatio >= 0.1 => 'fair',
            default => 'poor'
        };
    }

    /**
     * Rate sustainability
     *
     * @param float $ratio Sustainability ratio
     * @return string Sustainability rating
     */
    private function rateSustainability(float $ratio): string
    {
        return match(true) {
            $ratio >= 1.5 => 'excellent',
            $ratio >= 1.2 => 'good',
            $ratio >= 0.9 => 'fair',
            default => 'poor'
        };
    }

    /**
     * Assess risk level
     *
     * @param float $ratio Sustainability ratio
     * @param int $timeHorizon Time horizon
     * @return string Risk level
     */
    private function assessRiskLevel(float $ratio, int $timeHorizon): string
    {
        if ($ratio >= 1.5) {
            return 'low';
        } elseif ($ratio >= 1.0) {
            return 'moderate';
        } elseif ($ratio >= 0.8) {
            return 'high';
        } else {
            return 'very_high';
        }
    }
}
<?php

/**
 * Canadian Retirement Income Projection Calculator
 *
 * Models CPP (early/deferred), OAS, GIS, investment burn rates,
 * expense tapering, and break-even analysis.
 *
 * @package Ksfraser\Retirement
 * @requirement BR-RET-001, FR-RET-001, FR-RET-002, FR-RET-003
 */

declare(strict_types=1);

namespace Ksfraser\Retirement;

use UnexpectedValueException;

class RetirementCalculator
{
    // ------------------------------------------------------------------
    // Default assumptions (configurable)
    // ------------------------------------------------------------------
    /** Max monthly CPP at age 65 (2024 rate) */
    private const CPP_MAX_AT_65 = 1306.57;
    /** CPP reduction per month before 65 (yields 849.27 at age 60) */
    private const CPP_REDUCTION_PCT = 0.0058333;
    /** CPP increase per month after 65 (yields 1855.79 at age 70) */
    private const CPP_INCREASE_PCT = 0.0070059;
    /** Max CPP at age 70 = max * (1 + 60 * 0.007) */
    private const CPP_MAX_AT_70 = 1855.79;

    /** OAS max monthly at 65 (2024 rate) */
    private const OAS_MAX_AT_65 = 713.34;
    /** OAS increase per month deferred (0.6%) */
    private const OAS_INCREASE_PCT = 0.006;
    /** OAS max at 70 = max * (1 + 60 * 0.006) */
    private const OAS_MAX_AT_70 = 981.17;

    /** GIS max monthly for single (2024 rate) */
    private const GIS_MAX_SINGLE = 1093.53;
    /** GIS max monthly for couple (2024 rate) */
    private const GIS_MAX_COUPLE = 1638.34;
    /** GIS income exemption threshold (single) */
    private const GIS_EXEMPTION_SINGLE = 18000.0;
    /** GIS income exemption threshold (couple) */
    private const GIS_EXEMPTION_COUPLE = 24000.0;
    /** GIS reduction rate per dollar above exemption */
    private const GIS_REDUCTION_RATE = 0.50;

    /** Default OAS clawback threshold (annual) */
    private const OAS_CLAWBACK_THRESHOLD = 148000.0;

    private array $assumptions;

    public function __construct(array $assumptions = [])
    {
        $this->assumptions = $assumptions;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Run a complete retirement projection.
     *
     * @param array $input {
     *     @var int    $client_age
     *     @var int    $spouse_age
     *     @var int    $retirement_age
     *     @var int    $life_expectancy
     *     @var string $marital_status
     *     @var float  $rrsp_balance
     *     @var float  $tfsa_balance
     *     @var float  $non_reg_investments
     *     @var float  $other_assets
     *     @var float  $annual_employment_income
     *     @var float  $spouse_annual_income
     *     @var float  $other_annual_income
     *     @var float  $desired_retirement_income_annual
     *     @var float  $expense_taper_pct          (e.g. 10 = 10% reduction)
     *     @var int    $expense_taper_start_age
     *     @var int    $expense_taper_end_age
     *     @var float  $investment_return_pre
     *     @var float  $investment_return_post
     *     @var float  $inflation_rate
     *     @var float  $income_tax_rate
     *     @var array  $scenarios                  (optional overrides)
     * }
     *
     * @return array{
     *     success: bool,
     *     profile: array,
     *     scenarios: array,
     *     break_even: array,
     *     forward: array,
     *     reverse: array
     * }
     */
    public function project(array $input): array
    {
        $this->validate($input);

        $profile = $this->buildProfile($input);
        $scenarios = $this->runScenarios($input, $profile);
        $breakEven = $this->calculateBreakEven($scenarios);
        $forward = $this->forwardProjection($input, $profile, $scenarios);
        $reverse = $this->reverseProjection($input, $profile);

        return [
            'success' => true,
            'profile' => $profile,
            'scenarios' => $scenarios,
            'break_even' => $breakEven,
            'forward' => $forward,
            'reverse' => $reverse,
        ];
    }

    // ------------------------------------------------------------------
    // CPP / OAS / GIS calculations
    // ------------------------------------------------------------------

    /**
     * Calculate monthly CPP at a given start age.
     *
     * @param int   $startAge
     * @param float $maxAt65
     * @return float
     */
    public function calculateCpp(int $startAge, float $maxAt65 = self::CPP_MAX_AT_65): float
    {
        if ($startAge < 60 || $startAge > 70) {
            throw new UnexpectedValueException('CPP start age must be between 60 and 70');
        }

        if ($startAge === 65) {
            return round($maxAt65, 2);
        }

        if ($startAge < 65) {
            $monthsEarly = (65 - $startAge) * 12;
            return round($maxAt65 * (1 - self::CPP_REDUCTION_PCT * $monthsEarly), 2);
        }

        $monthsDeferred = ($startAge - 65) * 12;
        return round($maxAt65 * (1 + self::CPP_INCREASE_PCT * $monthsDeferred), 2);
    }

    /**
     * Calculate monthly OAS at a given start age (deferral only, no early start).
     *
     * @param int   $startAge
     * @param float $maxAt65
     * @return float
     */
    public function calculateOas(int $startAge, float $maxAt65 = self::OAS_MAX_AT_65): float
    {
        if ($startAge < 65 || $startAge > 70) {
            throw new UnexpectedValueException('OAS start age must be between 65 and 70');
        }

        if ($startAge === 65) {
            return round($maxAt65, 2);
        }

        $monthsDeferred = ($startAge - 65) * 12;
        return round($maxAt65 * (1 + self::OAS_INCREASE_PCT * $monthsDeferred), 2);
    }

    /**
     * Calculate GIS eligibility and monthly amount.
     *
     * @param bool   $isSingle
     * @param float  $annualIncome
     * @param float  $maxBenefit
     * @param float  $exemptionThreshold
     * @return float
     */
    public function calculateGis(
        bool $isSingle,
        float $annualIncome,
        float $maxBenefit,
        float $exemptionThreshold
    ): float {
        if ($annualIncome <= $exemptionThreshold) {
            return round($maxBenefit, 2);
        }

        $excess = $annualIncome - $exemptionThreshold;
        $reduction = $excess * self::GIS_REDUCTION_RATE;

        return round(max(0.0, $maxBenefit - $reduction), 2);
    }

    /**
     * Calculate OAS clawback amount.
     *
     * @param float $annualNetIncome
     * @return float
     */
    public function calculateOasClawback(float $annualNetIncome): float
    {
        if ($annualNetIncome <= self::OAS_CLAWBACK_THRESHOLD) {
            return 0.0;
        }

        $excess = $annualNetIncome - self::OAS_CLAWBACK_THRESHOLD;
        // OAS clawback is $0.15 per dollar over threshold
        return round(min($excess * 0.15, self::OAS_MAX_AT_65 * 12), 2);
    }

    // ------------------------------------------------------------------
    // RRSP → RRIF conversion + mandatory minimum withdrawals
    // ------------------------------------------------------------------

    /** RRIF minimum factors for ages 71+ (CRA published table) */
    private const RRIF_FACTORS_71_PLUS = [
        71 => 0.0528,
        72 => 0.0540,
        73 => 0.0553,
        74 => 0.0567,
        75 => 0.0617,
        76 => 0.0636,
        77 => 0.0658,
        78 => 0.0682,
        79 => 0.0708,
        80 => 0.0738,
        81 => 0.0771,
        82 => 0.0808,
        83 => 0.0851,
        84 => 0.0899,
        85 => 0.0954,
        86 => 0.1017,
        87 => 0.1091,
        88 => 0.1179,
        89 => 0.1285,
        90 => 0.1414,
        91 => 0.1575,
        92 => 0.1775,
        93 => 0.2039,
        94 => 0.2386,
        95 => 0.2834,
        96 => 0.3426,
        97 => 0.4227,
        98 => 0.5346,
        99 => 0.6989,
        100 => 1.0000,
    ];

    /**
     * Calculate the RRIF mandatory minimum withdrawal for a given age/balance.
     *
     * @param int    $age
     * @param float  $balance        Beginning-of-year RRSP/RRIF balance
     * @param int    $spouseAge      Optional younger spouse age for spousal RRIF
     * @return float
     */
    public function calculateRrifMinimum(int $age, float $balance, ?int $spouseAge = null): float
    {
        if ($balance <= 0) {
            return 0.0;
        }

        // Use younger spouse age if provided and younger (spousal RRIF rule)
        $factorAge = $age;
        if ($spouseAge !== null && $spouseAge < $age) {
            $factorAge = $spouseAge;
        }

        if ($factorAge <= 70) {
            // 1/(90 - age) formula for ages 55-70
            $factor = 1.0 / (90 - $factorAge);
        } else {
            $factor = self::RRIF_FACTORS_71_PLUS[$factorAge] ?? 1.0;
        }

        return round($balance * $factor, 2);
    }

    // ------------------------------------------------------------------
    // Scenario generation
    // ------------------------------------------------------------------

    /**
     * Run standard retirement scenarios: early, normal, deferred.
     *
     * @param array $input
     * @param array $profile
     * @return array<string, array>
     */
    private function runScenarios(array $input, array $profile): array
    {
        $clientAge = (int) $input['client_age'];
        $scenarios = [];

        // Standard CPP age sweep
        $cppAges = [60, 62, 65, 67, 70];
        foreach ($cppAges as $cppAge) {
            $scenario = $this->runSingleScenario($input, $profile, [
                'cpp_start_age' => $cppAge,
                'oas_start_age' => $cppAge >= 65 ? $cppAge : 65,
                'scenario_label' => $this->labelForAge($cppAge),
            ]);
            $scenarios[$scenario['label']] = $scenario;
        }

        // Strategy: Burn RRSP before RRIF conversion, defer CPP
        $burnScenario = $this->runSingleScenario($input, $profile, [
            'cpp_start_age' => 70,
            'oas_start_age' => 65,
            'scenario_label' => 'Burn RRSP + Defer CPP',
            'strategy' => 'burn_rrsp_before_rrif',
        ]);
        $scenarios[$burnScenario['label']] = $burnScenario;

        // Annuity variants
        if (!empty($input['annuity_strategy']) && $input['annuity_strategy'] !== 'none') {
            $annuityLabel = $input['annuity_strategy'] === 'full' ? 'Full Annuity' : 'Partial Annuity';
            $annuityScenario = $this->runSingleScenario($input, $profile, [
                'cpp_start_age' => 65,
                'oas_start_age' => 65,
                'scenario_label' => $annuityLabel,
                'strategy' => $input['annuity_strategy'],
                'annuity_amount' => (float) ($input['annuity_amount'] ?? 0),
                'annuity_rate' => (float) ($input['annuity_rate'] ?? 0.035),
            ]);
            $scenarios[$annuityScenario['label']] = $annuityScenario;
        }

        // Custom scenarios from input if provided
        if (!empty($input['scenarios']) && is_array($input['scenarios'])) {
            foreach ($input['scenarios'] as $idx => $custom) {
                $scenario = $this->runSingleScenario($input, $profile, array_merge($custom, [
                    'scenario_label' => $custom['label'] ?? "Custom " . ($idx + 1),
                ]));
                $scenarios[$scenario['label']] = $scenario;
            }
        }

        return $scenarios;
    }

    /**
     * Run a single scenario year-by-year.
     *
     * @param array $input
     * @param array $profile
     * @param array $overrides
     * @return array
     */
    private function runSingleScenario(array $input, array $profile, array $overrides = []): array
    {
        $cppStart = (int) ($overrides['cpp_start_age'] ?? 65);
        $oasStart = (int) ($overrides['oas_start_age'] ?? 65);
        $clientAge = (int) $input['client_age'];
        $retirementAge = max((int) $input['retirement_age'], $cppStart, $oasStart);
        $lifeExpectancy = (int) $input['life_expectancy'];
        $maritalStatus = strtolower((string) ($input['marital_status'] ?? 'single'));
        $isSingle = !in_array($maritalStatus, ['married', 'common_law'], true);
        $rrifConversionAge = (int) ($input['rrif_conversion_age'] ?? 71);
        $spouseAge = isset($input['spouse_age']) && $input['spouse_age'] !== null ? (int) $input['spouse_age'] : null;

        // Strategy flags
        $strategy = strtolower((string) ($overrides['strategy'] ?? 'standard'));
        $burnRrspBeforeRrif = $strategy === 'burn_rrsp_before_rrif';
        $annuityStrategy = $strategy === 'partial' || $strategy === 'full' ? $strategy : 'none';
        $annuityAmount = (float) ($overrides['annuity_amount'] ?? 0);
        $annuityRate = (float) ($overrides['annuity_rate'] ?? 0.035);

        // Pre-retirement accumulation
        $rrsp = (float) $input['rrsp_balance'];
        $tfsa = (float) $input['tfsa_balance'];
        $nonReg = (float) $input['non_reg_investments'];
        $other = (float) $input['other_assets'];
        $annualEmployment = (float) $input['annual_employment_income'];
        $spouseIncome = (float) $input['spouse_annual_income'];
        $otherIncome = (float) $input['other_annual_income'];

        $returnPre = (float) $input['investment_return_pre'];
        $returnPost = (float) $input['investment_return_post'];
        $inflation = (float) $input['inflation_rate'];
        $taxRate = (float) $input['income_tax_rate'];
        $desiredIncome = (float) $input['desired_retirement_income_annual'];
        $taperPct = (float) $input['expense_taper_pct'];
        $taperStart = (int) $input['expense_taper_start_age'];
        $taperEnd = (int) $input['expense_taper_end_age'];

        // Annuity setup: purchased at retirement, fixed monthly payout for life
        $annuityPurchaseAmount = 0.0;
        $annuityMonthlyPayout = 0.0;
        if ($annuityStrategy !== 'none' && $age >= $retirementAge) {
            // Determine purchase amount: explicit amount, or percentage of RRSP
            $annuityPurchaseAmount = $annuityAmount > 0
                ? $annuityAmount
                : ($annuityStrategy === 'full' ? $rrsp : $rrsp * 0.3);
            $annuityPurchaseAmount = min($annuityPurchaseAmount, $rrsp);
            $annuityMonthlyPayout = ($annuityPurchaseAmount * $annuityRate) / 12;
            $rrsp -= $annuityPurchaseAmount;
        }

        $yearByYear = [];
        $totalAssetsOverTime = [];
        $shortfallYears = 0;
        $totalRrifWithdrawn = 0.0;

        for ($age = $clientAge; $age <= $lifeExpectancy; $age++) {
            $yearIndex = $age - $clientAge;

            // Asset growth / drawdown
            $rrifMinimum = 0.0;
            $cppMonthly = 0.0;
            $oasAnnual = 0.0;
            $gisMonthly = 0.0;
            $requiredGrossFromRRSP = 0.0;
            $annuityMonthlyPayout = 0.0;
            if ($age < $retirementAge) {
                // Accumulation phase
                $saveRate = $this->afterTaxIncome($annualEmployment + $spouseIncome + $otherIncome, $taxRate);
                $rrsp = ($rrsp + $saveRate) * (1 + $returnPre);
                $tfsa = $tfsa * (1 + $returnPre);
                $nonReg = $nonReg * (1 + $returnPre);
                $other = $other * (1 + $returnPre);
                $desiredThisYear = 0;
                $publicPension = 0.0;
            } else {
                // Decumulation phase
                $totalInvestable = $rrsp + $tfsa + $nonReg;
                $inflatedDesired = $desiredIncome * pow(1 + $inflation, $yearIndex);

                // Apply expense tapering
                if ($age >= $taperStart && $taperPct > 0) {
                    $taperProgress = min(1.0, ($age - $taperStart) / max(1, $taperEnd - $taperStart));
                    $inflatedDesired *= (1 - ($taperPct / 100) * $taperProgress);
                }

                // Public pensions
                $cppMonthly = $age >= $cppStart ? $this->calculateCpp($cppStart) : 0.0;
                $oasMonthly = $age >= $oasStart ? $this->calculateOas($oasStart) : 0.0;
                $oasAnnual = $oasMonthly * 12;

                // RRSP → RRIF mandatory minimum withdrawal
                if ($age >= $rrifConversionAge) {
                    $rrifMinimum = $this->calculateRrifMinimum($age, $rrsp, $spouseAge);
                }

                // GIS (income includes desired spending + RRIF minimum for realistic clawback interaction)
                $gisMonthly = 0.0;
                if ($age >= 65 && $oasMonthly > 0) {
                    $gisMonthly = $this->calculateGis(
                        $isSingle,
                        max(0, $inflatedDesired - $cppMonthly * 12 - $oasAnnual + $rrifMinimum),
                        $isSingle ? self::GIS_MAX_SINGLE : self::GIS_MAX_COUPLE,
                        $isSingle ? self::GIS_EXEMPTION_SINGLE : self::GIS_EXEMPTION_COUPLE
                    );
                }

                $publicPension = ($cppMonthly + $oasMonthly + $gisMonthly) * 12;

                // OAS clawback includes RRIF minimum as taxable income
                $netIncomeForClawback = $inflatedDesired + $publicPension + $rrifMinimum;
                $clawback = $this->calculateOasClawback($netIncomeForClawback);
                $oasAnnual = max(0, $oasAnnual - $clawback);
                $publicPension = $cppMonthly * 12 + $oasAnnual + $gisMonthly * 12;

                // Withdraw from investments to cover shortfall
                $shortfall = max(0, $inflatedDesired - $publicPension);
                $afterTaxShortfall = $shortfall / (1 - $taxRate);

                // RRIF minimum takes priority over spending shortfall
                $requiredGrossFromRRSP = $age >= $rrifConversionAge
                    ? max($afterTaxShortfall, $rrifMinimum)
                    : $afterTaxShortfall;

                if ($requiredGrossFromRRSP > $totalInvestable) {
                    $shortfallYears++;
                }

                // Tax-sheltered withdrawal order: RRSP/RRIF first, then non-reg, then TFSA
                $rrsp = max(0, $rrsp - $requiredGrossFromRRSP);
                if ($rrsp < 0) {
                    $nonReg = max(0, $nonReg + $rrsp);
                    $rrsp = 0;
                }
                $nonReg = max(0, $nonReg - max(0, $requiredGrossFromRRSP - $rrsp));
                if ($nonReg < 0) {
                    $tfsa = max(0, $tfsa + $nonReg);
                    $nonReg = 0;
                }
                $tfsa = max(0, $tfsa - max(0, $requiredGrossFromRRSP - $rrsp - $nonReg));

                $rrsp = $rrsp * (1 + $returnPost);
                $tfsa = $tfsa * (1 + $returnPost);
                $nonReg = $nonReg * (1 + $returnPost);
                $other = $other * (1 + $returnPost);

                $desiredThisYear = $inflatedDesired;
            }

            $totalAssets = $rrsp + $tfsa + $nonReg + $other;

            $yearByYear[] = [
                'age' => $age,
                'year_index' => $yearIndex,
                'phase' => $age < $retirementAge ? 'accumulation' : 'decumulation',
                'rrsp' => round($rrsp, 2),
                'tfsa' => round($tfsa, 2),
                'non_reg' => round($nonReg, 2),
                'other' => round($other, 2),
                'total_assets' => round($totalAssets, 2),
                'desired_income' => round($desiredThisYear, 2),
                'public_pension' => round($publicPension ?? 0, 2),
                'rrif_minimum' => round($rrifMinimum, 2),
                'cpp_income' => round($cppMonthly * 12, 2),
                'oas_income' => round($oasAnnual, 2),
                'gis_income' => round($gisMonthly * 12, 2),
                'investment_withdrawal' => round(max(0, $requiredGrossFromRRSP - $rrifMinimum), 2),
                'is_rrif_converted' => $age >= $rrifConversionAge,
                'annuity_income' => round($annuityMonthlyPayout * 12, 2),
            ];

            $totalAssetsOverTime[] = round($totalAssets, 2);
        }

        return [
            'label' => $overrides['scenario_label'] ?? 'Normal',
            'cpp_start_age' => $cppStart,
            'oas_start_age' => $oasStart,
            'cpp_monthly' => $this->calculateCpp($cppStart),
            'oas_monthly' => $this->calculateOas($oasStart),
            'rrif_conversion_age' => $rrifConversionAge,
            'initial_assets' => round((float) $input['rrsp_balance'] + (float) $input['tfsa_balance'] + (float) $input['non_reg_investments'] + (float) $input['other_assets'], 2),
            'final_assets' => end($totalAssetsOverTime) ?: 0,
            'min_assets' => min($totalAssetsOverTime),
            'depletes_before_end' => $shortfallYears > 0,
            'shortfall_years' => $shortfallYears,
            'total_rrif_withdrawn' => $this->sumRrifWithdrawals($yearByYear),
            'year_by_year' => $yearByYear,
        ];
    }

    /**
     * Sum total RRIF mandatory minimums over a scenario.
     */
    private function sumRrifWithdrawals(array $yearByYear): float
    {
        $sum = 0.0;
        foreach ($yearByYear as $row) {
            if ($row['is_rrif_converted']) {
                $sum += $row['rrif_minimum'];
            }
        }
        return round($sum, 2);
    }

    // ------------------------------------------------------------------
    // Break-even analysis
    // ------------------------------------------------------------------

    /**
     * Compare all scenarios and find the break-even point.
     *
     * @param array $scenarios
     * @return array{
     *     break_even_age: int|null,
     *     recommended_scenario: string,
     *     comparison: array
     * }
     */
    private function calculateBreakEven(array $scenarios): array
    {
        $comparison = [];
        $bestSurplus = PHP_INT_MIN;
        $bestScenario = 'Normal';
        $breakEvenAge = null;

        foreach ($scenarios as $label => $scenario) {
            $finalAssets = $scenario['final_assets'];
            $minAssets = $scenario['min_assets'];
            $surplusAt82 = 0;

            foreach ($scenario['year_by_year'] as $row) {
                if ($row['age'] === 82) {
                    $surplusAt82 = $row['total_assets'];
                    break;
                }
            }

            $comparison[$label] = [
                'label' => $label,
                'cpp_start_age' => $scenario['cpp_start_age'],
                'oas_start_age' => $scenario['oas_start_age'],
                'cpp_monthly' => $scenario['cpp_monthly'],
                'oas_monthly' => $scenario['oas_monthly'],
                'initial_assets' => $scenario['initial_assets'],
                'final_assets' => $finalAssets,
                'min_assets' => $minAssets,
                'surplus_at_82' => $surplusAt82,
                'depletes_before_end' => $scenario['depletes_before_end'],
            ];

            if ($minAssets > $bestSurplus) {
                $bestSurplus = $minAssets;
                $bestScenario = $label;
            }

            // Find first age where deferred scenario beats early
            if ($label === 'Deferred (70)') {
                foreach ($scenario['year_by_year'] as $row) {
                    if (isset($scenarios['Early (60)']['year_by_year'][$row['year_index']])) {
                        $earlyAssets = $scenarios['Early (60)']['year_by_year'][$row['year_index']]['total_assets'];
                        if ($row['total_assets'] > $earlyAssets) {
                            $breakEvenAge = $row['age'];
                            break;
                        }
                    }
                }
            }
        }

        return [
            'break_even_age' => $breakEvenAge,
            'recommended_scenario' => $bestScenario,
            'comparison' => $comparison,
        ];
    }

    // ------------------------------------------------------------------
    // Forward projection: "my bag of money"
    // ------------------------------------------------------------------

    /**
     * @param array $input
     * @param array $profile
     * @param array $scenarios
     * @return array
     */
    private function forwardProjection(array $input, array $profile, array $scenarios): array
    {
        $best = $scenarios[$profile['recommended_scenario']] ?? reset($scenarios);

        return [
            'mode' => 'forward',
            'description' => 'Given current assets and savings path, how much can I spend?',
            'initial_assets' => $best['initial_assets'],
            'final_assets' => $best['final_assets'],
            'min_assets' => $best['min_assets'],
            'depletes_before_end' => $best['depletes_before_end'],
            'effective_burn_rate_pct' => $best['initial_assets'] > 0
                ? round(($best['initial_assets'] - $best['final_assets']) / $best['initial_assets'] * 100, 2)
                : 0,
            'year_by_year' => $best['year_by_year'],
        ];
    }

    // ------------------------------------------------------------------
    // Reverse projection: "what do I need?"
    // ------------------------------------------------------------------

    /**
     * @param array $input
     * @param array $profile
     * @return array
     */
    private function reverseProjection(array $input, array $profile): array
    {
        // Given desired retirement income, solve for required asset target
        $retirementAge = (int) $input['retirement_age'];
        $lifeExpectancy = (int) $input['life_expectancy'];
        $inflation = (float) $input['inflation_rate'];
        $returnPost = (float) $input['investment_return_post'];
        $desiredIncome = (float) $input['desired_retirement_income_annual'];
        $taxRate = (float) $input['income_tax_rate'];
        $cppMonthly = $this->calculateCpp((int) $input['retirement_age']);
        $oasMonthly = $this->calculateOas((int) $input['retirement_age']);

        $totalPublic = ($cppMonthly + $oasMonthly) * 12;
        $shortfall = max(0, $desiredIncome - $totalPublic);
        $afterTaxShortfall = $shortfall / (1 - $taxRate);

        // Present value of shortfall over retirement years
        $years = $lifeExpectancy - $retirementAge;
        $pv = 0.0;
        for ($i = 0; $i < $years; $i++) {
            $inflatedShortfall = $afterTaxShortfall * pow(1 + $inflation, $i);
            $pv += $inflatedShortfall / pow(1 + $returnPost, $i + 1);
        }

        return [
            'mode' => 'reverse',
            'description' => 'To achieve desired retirement income, how much do I need?',
            'desired_income' => $desiredIncome,
            'total_public_income' => round($totalPublic, 2),
            'investment_shortfall' => round($shortfall, 2),
            'after_tax_shortfall' => round($afterTaxShortfall, 2),
            'required_assets_at_retirement' => round($pv, 2),
            'years_to_retirement' => max(0, $retirementAge - (int) $input['client_age']),
            'monthly_savings_needed' => $this->solveForPMT($pv, max(1, $retirementAge - (int) $input['client_age']), (float) $input['investment_return_pre']),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function afterTaxIncome(float $gross, float $taxRate): float
    {
        return $gross * (1 - $taxRate);
    }

    private function solveForPMT(float $pv, int $years, float $rate): float
    {
        if ($years <= 0) {
            return 0.0;
        }
        if ($rate == 0.0) {
            return round($pv / $years / 12, 2);
        }

        $monthlyRate = $rate / 12;
        $months = $years * 12;
        $pmt = ($pv * $monthlyRate) / (pow(1 + $monthlyRate, $months) - 1);
        return round($pmt, 2);
    }

    private function buildProfile(array $input): array
    {
        return [
            'client_age' => (int) $input['client_age'],
            'spouse_age' => (int) $input['spouse_age'],
            'retirement_age' => (int) $input['retirement_age'],
            'life_expectancy' => (int) $input['life_expectancy'],
            'marital_status' => strtolower((string) $input['marital_status']),
            'recommended_scenario' => $this->recommendScenario($input),
        ];
    }

    private function recommendScenario(array $input): string
    {
        $age = (int) $input['client_age'];
        if ($age >= 68) {
            return 'Deferred (70)';
        }
        if ($age >= 62) {
            return 'Normal (65)';
        }
        return 'Early (60)';
    }

    /**
     * Recommend an age-appropriate asset allocation with cash buffer.
     *
     * Returns percentages for cash/bond buffer, balanced, and growth/income buckets,
     * plus the dollar amounts based on total investable assets.
     */
    public function recommendAssetAllocation(int $age, float $totalAssets): array
    {
        // Buffer = 2 years of estimated spending at retirement (assume ~40k/yr if unknown)
        $estimatedAnnualSpend = 40000.0;
        $bufferTarget = $estimatedAnnualSpend * 2;

        if ($age < 55) {
            $cashPct = 0.10;
            $balancedPct = 0.40;
            $growthPct = 0.50;
        } elseif ($age < 65) {
            $cashPct = 0.20;
            $balancedPct = 0.40;
            $growthPct = 0.40;
        } elseif ($age < 75) {
            $cashPct = 0.25;
            $balancedPct = 0.45;
            $growthPct = 0.30;
        } elseif ($age < 85) {
            $cashPct = 0.35;
            $balancedPct = 0.45;
            $growthPct = 0.20;
        } else {
            $cashPct = 0.50;
            $balancedPct = 0.40;
            $growthPct = 0.10;
        }

        // Floor cash at buffer target, reduce from balanced if needed
        $cashDollars = max($bufferTarget, $totalAssets * $cashPct);
        $remaining = max(0, $totalAssets - $cashDollars);
        $balancedDollars = $remaining * ($balancedPct / ($balancedPct + $growthPct));
        $growthDollars = $remaining - $balancedDollars;

        return [
            'cash_buffer_pct' => round($cashDollars / $totalAssets * 100, 1),
            'balanced_pct' => round($balancedDollars / $totalAssets * 100, 1),
            'growth_income_pct' => round($growthDollars / $totalAssets * 100, 1),
            'cash_buffer_dollars' => round($cashDollars, 2),
            'balanced_dollars' => round($balancedDollars, 2),
            'growth_income_dollars' => round($growthDollars, 2),
            'notes' => $age >= 75
                ? 'Higher cash/bond allocation reduces sequence-of-returns risk in later retirement.'
                : 'Balanced bucket provides stability; growth/income bucket funds longevity.',
        ];
    }

    private function labelForAge(int $age): string
    {
        $labels = [
            60 => 'Early (60)',
            62 => 'Early (62)',
            65 => 'Normal (65)',
            67 => 'Normal (67)',
            70 => 'Deferred (70)',
        ];
        return $labels[$age] ?? "Age {$age}";
    }

    private function validate(array $input): void
    {
        $required = ['client_age', 'life_expectancy', 'rrsp_balance', 'tfsa_balance', 'desired_retirement_income_annual'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new UnexpectedValueException("Missing required field: {$field}");
            }
        }

        if ((int) $input['life_expectancy'] < (int) $input['client_age']) {
            throw new UnexpectedValueException('Life expectancy must be greater than current age');
        }

        $rrifAge = (int) ($input['rrif_conversion_age'] ?? 71);
        if ($rrifAge < 55 || $rrifAge > 100) {
            throw new UnexpectedValueException('RRIF conversion age must be between 55 and 100');
        }
    }

    // ------------------------------------------------------------------
    // Export: CSV and PDF-ready HTML
    // ------------------------------------------------------------------

    /**
     * Generate a CSV string from results.
     */
    public function toCsv(array $results): string
    {
        $lines = [];
        $lines[] = '# Retirement Projection CSV Export';
        $lines[] = '# Generated: ' . date('Y-m-d H:i');

        // Summary block
        if (!empty($results['break_even']['comparison'])) {
            $lines[] = '';
            $lines[] = 'SCENARIO,CPP_START,OAS_START,INITIAL_ASSETS,FINAL_ASSETS,MIN_ASSETS,SHORTFALL_YEARS,TOTAL_RRIF';
            foreach ($results['break_even']['comparison'] as $c) {
                $scenario = $results['scenarios'][$c['label']] ?? [];
                $lines[] = sprintf(
                    '"%s",%d,%d,%.2f,%.2f,%.2f,%d,%.2f',
                    str_replace('"', '""', $c['label']),
                    $c['cpp_start_age'] ?? 0,
                    $c['oas_start_age'] ?? 0,
                    $c['initial_assets'],
                    $c['final_assets'],
                    $c['min_assets'],
                    $c['depletes_before_end'] ? 1 : 0,
                    $scenario['total_rrif_withdrawn'] ?? 0
                );
            }
        }

        // Year-by-year for recommended scenario
        $rec = $results['scenarios'][$results['break_even']['recommended_scenario']] ?? reset($results['scenarios']);
        if ($rec && !empty($rec['year_by_year'])) {
            $lines[] = '';
            $lines[] = 'YEAR_BY_YEAR (Recommended: ' . ($results['break_even']['recommended_scenario'] ?? 'N/A') . ')';
            $lines[] = implode(',', [
                'age','phase','desired_income','public_pension','cpp_income','oas_income','gis_income',
                'rrif_minimum','investment_withdrawal','annuity_income','rrsp','tfsa','non_reg','other','total_assets'
            ]);
            foreach ($rec['year_by_year'] as $row) {
                $lines[] = implode(',', [
                    $row['age'],
                    $row['phase'],
                    $row['desired_income'],
                    $row['public_pension'],
                    $row['cpp_income'],
                    $row['oas_income'],
                    $row['gis_income'],
                    $row['rrif_minimum'],
                    $row['investment_withdrawal'],
                    $row['annuity_income'],
                    $row['rrsp'],
                    $row['tfsa'],
                    $row['non_reg'],
                    $row['other'],
                    $row['total_assets'],
                ]);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate a print-friendly HTML document for PDF export.
     */
    public function toPdfHtml(array $results, array $input): string
    {
        $rec = $results['scenarios'][$results['break_even']['recommended_scenario']] ?? reset($results['scenarios']);
        $alloc = $this->recommendAssetAllocation((int) $input['client_age'], $rec['initial_assets'] ?? 0);

        $fmt = function($v) { return '$' . number_format((float)$v, 0); };

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Retirement Projection</title>
<style>
  body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
  h1 { font-size: 22px; }
  h2 { font-size: 16px; margin-top: 24px; border-bottom: 1px solid #ccc; }
  table { border-collapse: collapse; width: 100%; margin-top: 10px; }
  th, td { border: 1px solid #ccc; padding: 6px; font-size: 12px; text-align: right; }
  th { background: #f0f0f0; }
  td:first-child, th:first-child { text-align: left; }
  .meta { font-size: 12px; color: #555; margin-bottom: 20px; }
  .allocation { display: flex; height: 24px; margin-top: 8px; }
  .bar { display: inline-block; height: 100%; }
  .cash { background: #2ecc71; }
  .balanced { background: #3498db; }
  .growth { background: #9b59b6; }
  .legend { font-size: 11px; margin-top: 4px; }
</style>
</head>
<body>
  <h1>Retirement Projection Report</h1>
  <div class="meta">
    Client Age: <?php echo (int)$input['client_age']; ?> |
    Retirement Age: <?php echo (int)$input['retirement_age']; ?> |
    Life Expectancy: <?php echo (int)$input['life_expectancy']; ?> |
    Inflation: <?php echo round((float)$input['inflation_rate']*100,1); ?>% |
    Post-Return: <?php echo round((float)$input['investment_return_post']*100,1); ?>%
  </div>

  <h2>Scenario Comparison</h2>
  <table>
    <tr>
      <th>Scenario</th><th>CPP</th><th>OAS</th><th>Initial Assets</th><th>Final Assets</th><th>Min Assets</th><th>Shortfall</th>
    </tr>
    <?php foreach ($results['break_even']['comparison'] as $c): ?>
    <tr>
      <td><?php echo htmlspecialchars($c['label']); ?></td>
      <td><?php echo (int)($c['cpp_start_age'] ?? 0); ?></td>
      <td><?php echo (int)($c['oas_start_age'] ?? 0); ?></td>
      <td><?php echo $fmt($c['initial_assets']); ?></td>
      <td><?php echo $fmt($c['final_assets']); ?></td>
      <td><?php echo $fmt($c['min_assets']); ?></td>
      <td><?php echo $c['depletes_before_end'] ? 'Yes' : 'No'; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if ($results['break_even']['break_even_age']): ?>
    <p><strong>Break-even:</strong> Deferring beats early around age <?php echo $results['break_even']['break_even_age']; ?>.</p>
  <?php endif; ?>

  <h2>Asset Allocation Recommendation (Age <?php echo (int)$input['client_age']; ?>)</h2>
  <div class="allocation">
    <span class="bar cash" style="width:<?php echo $alloc['cash_buffer_pct']; ?>%"></span>
    <span class="bar balanced" style="width:<?php echo $alloc['balanced_pct']; ?>%"></span>
    <span class="bar growth" style="width:<?php echo $alloc['growth_income_pct']; ?>%"></span>
  </div>
  <div class="legend">
    Cash/Bond Buffer: <?php echo $alloc['cash_buffer_pct']; ?>% (<?php echo $fmt($alloc['cash_buffer_dollars']); ?>) |
    Balanced: <?php echo $alloc['balanced_pct']; ?>% (<?php echo $fmt($alloc['balanced_dollars']); ?>) |
    Growth/Income: <?php echo $alloc['growth_income_pct']; ?>% (<?php echo $fmt($alloc['growth_income_dollars']); ?>)
  </div>
  <p><?php echo htmlspecialchars($alloc['notes']); ?></p>

  <h2>Recommended Scenario Year-by-Year (<?php echo htmlspecialchars($rec['label'] ?? 'N/A'); ?>)</h2>
  <table>
    <tr>
      <th>Age</th><th>Phase</th><th>Desired</th><th>Public Pension</th><th>CPP</th><th>OAS</th><th>GIS</th>
      <th>RRIF Min</th><th>Investment WD</th><th>Annuity</th><th>RRSP</th><th>TFSA</th><th>Non-Reg</th><th>Total Assets</th>
    </tr>
    <?php foreach ($rec['year_by_year'] as $row): ?>
    <tr>
      <td><?php echo $row['age']; ?></td>
      <td><?php echo $row['phase']; ?></td>
      <td><?php echo $fmt($row['desired_income']); ?></td>
      <td><?php echo $fmt($row['public_pension']); ?></td>
      <td><?php echo $fmt($row['cpp_income']); ?></td>
      <td><?php echo $fmt($row['oas_income']); ?></td>
      <td><?php echo $fmt($row['gis_income']); ?></td>
      <td><?php echo $fmt($row['rrif_minimum']); ?></td>
      <td><?php echo $fmt($row['investment_withdrawal']); ?></td>
      <td><?php echo $fmt($row['annuity_income']); ?></td>
      <td><?php echo $fmt($row['rrsp']); ?></td>
      <td><?php echo $fmt($row['tfsa']); ?></td>
      <td><?php echo $fmt($row['non_reg']); ?></td>
      <td><?php echo $fmt($row['total_assets']); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p style="font-size:11px;color:#777;">Generated by KSFII Retirement Calculator</p>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}

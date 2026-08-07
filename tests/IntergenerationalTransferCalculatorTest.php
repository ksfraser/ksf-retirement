<?php

declare(strict_types=1);

namespace Ksfraser\Retirement\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Intergenerational Transfer Calculator Test Suite
 *
 * Tests the IntergenerationalTransferCalculator for transfer efficiency calculations.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class IntergenerationalTransferCalculatorTest extends TestCase
{
    private IntergenerationalTransferCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new IntergenerationalTransferCalculator();
    }

    /**
     * Test successful transfer efficiency calculation
     */
    public function testCalculateTransferEfficiencySuccess(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 500000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 300000, 'account_type' => 'registered'],
            ['type' => 'cash', 'value' => 200000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = [
            'marginal_rate' => 0.45,
            'province' => 'Ontario',
            'bracket' => 'high'
        ];

        $timeHorizon = 25;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('estate_value', $result);
        $this->assertArrayHasKey('time_horizon', $result);
        $this->assertArrayHasKey('transfer_strategies', $result);
        $this->assertArrayHasKey('optimal_strategy', $result);
        $this->assertArrayHasKey('tax_efficiency', $result);
        $this->assertArrayHasKey('generational_impact', $result);
        $this->assertArrayHasKey('efficiency_score', $result);
        $this->assertArrayHasKey('estimated_savings', $result);
        $this->assertArrayHasKey('recommendations', $result);

        $this->assertGreaterThan(0, $result['efficiency_score']);
        $this->assertLessThanOrEqual(100, $result['efficiency_score']);
        $this->assertIsArray($result['transfer_strategies']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test calculation with short time horizon
     */
    public function testCalculateTransferEfficiencyShortHorizon(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 100000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 50000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Alberta', 'bracket' => 'high'];
        $timeHorizon = 3;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertGreaterThan(0, $result['efficiency_score']);
        $this->assertEquals(3, $result['time_horizon']);

        // Check that outright gift is recommended for short horizons
        $optimalStrategy = $result['optimal_strategy'];
        $this->assertEquals(IntergenerationalTransferCalculator::METHOD_OUTRIGHT_GIFT, $optimalStrategy['method']);
    }

    /**
     * Test calculation with long time horizon
     */
    public function testCalculateTransferEfficiencyLongHorizon(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'bonds', 'value' => 500000, 'account_type' => 'registered'],
            ['type' => 'cash', 'value' => 200000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = ['marginal_rate' => 0.25, 'province' => 'Quebec', 'bracket' => 'low'];
        $timeHorizon = 40;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertGreaterThan(0, $result['efficiency_score']);
        $this->assertEquals(40, $result['time_horizon']);

        // Check that lifetime trust is recommended for long horizons
        $optimalStrategy = $result['optimal_strategy'];
        $this->assertContains($optimalStrategy['method'], [
            IntergenerationalTransferCalculator::METHOD_LIFETIME_TRUST,
            IntergenerationalTransferCalculator::METHOD_LIFE_INSURANCE
        ]);
    }

    /**
     * Test calculation with high tax bracket
     */
    public function testCalculateTransferEfficiencyHighTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 1000000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 500000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.53, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 20;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertGreaterThan(0, $result['efficiency_score']);

        // Check that tax-efficient strategies are prioritized
        $strategies = $result['transfer_strategies'];
        $this->assertArrayHasKey(IntergenerationalTransferCalculator::METHOD_LIFETIME_TRUST, $strategies);
        $this->assertArrayHasKey(IntergenerationalTransferCalculator::METHOD_TESTAMENTARY_TRUST, $strategies);
    }

    /**
     * Test calculation with low tax bracket
     */
    public function testCalculateTransferEfficiencyLowTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'cash', 'value' => 100000, 'account_type' => 'tax_deferred'],
            ['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.20, 'province' => 'Alberta', 'bracket' => 'low'];
        $timeHorizon = 15;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertGreaterThan(0, $result['efficiency_score']);

        // Check that simpler strategies are recommended for low bracket
        $strategies = $result['transfer_strategies'];
        $this->assertArrayHasKey(IntergenerationalTransferCalculator::METHOD_OUTRIGHT_GIFT, $strategies);
    }

    /**
     * Test calculation with empty portfolio
     */
    public function testCalculateTransferEfficiencyEmptyPortfolio(): void
    {
        // Arrange
        $portfolio = [];
        $taxSituation = ['marginal_rate' => 0.35, 'province' => 'BC', 'bracket' => 'medium'];
        $timeHorizon = 20;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertEquals(0, $result['estate_value']['total_value']);
        $this->assertEquals(0, $result['efficiency_score']);
        $this->assertEmpty($result['transfer_strategies']);
    }

    /**
     * Test calculation with zero time horizon
     */
    public function testCalculateTransferEfficiencyZeroHorizon(): void
    {
        // Arrange
        $portfolio = [['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.30, 'province' => 'BC', 'bracket' => 'medium'];
        $timeHorizon = 0;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $this->assertEquals(0, $result['time_horizon']);
        $this->assertGreaterThan(0, $result['efficiency_score']); // Still calculates efficiency
    }

    /**
     * Test estate value calculation
     */
    public function testCalculateTransferEfficiencyEstateValue(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 500000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 300000, 'account_type' => 'registered'],
            ['type' => 'cash', 'value' => 200000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = ['marginal_rate' => 0.45, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 25;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $estateValue = $result['estate_value'];
        $this->assertEquals(1000000, $estateValue['total_value']);
        $this->assertGreaterThan(0, $estateValue['taxable_value']);
        $this->assertGreaterThan(0, $estateValue['estimated_estate_tax']);
        $this->assertLessThan($estateValue['total_value'], $estateValue['after_tax_value']);
    }

    /**
     * Test exception handling for invalid portfolio
     */
    public function testCalculateTransferEfficiencyInvalidPortfolio(): void
    {
        // Arrange
        $portfolio = [['invalid' => 'data']]; // Missing required fields
        $taxSituation = ['marginal_rate' => 0.35];
        $timeHorizon = 20;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);
    }

    /**
     * Test exception handling for invalid tax situation
     */
    public function testCalculateTransferEfficiencyInvalidTaxSituation(): void
    {
        // Arrange
        $portfolio = [['type' => 'equity', 'value' => 100000, 'account_type' => 'registered']];
        $taxSituation = ['invalid' => 'data']; // Missing marginal_rate
        $timeHorizon = 20;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);
    }

    /**
     * Test transfer strategies evaluation
     */
    public function testCalculateTransferEfficiencyStrategies(): void
    {
        // Arrange
        $portfolio = [['type' => 'mixed', 'value' => 500000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 25;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $strategies = $result['transfer_strategies'];

        // Check that all expected strategies are present
        $expectedStrategies = [
            IntergenerationalTransferCalculator::METHOD_OUTRIGHT_GIFT,
            IntergenerationalTransferCalculator::METHOD_LIFETIME_TRUST,
            IntergenerationalTransferCalculator::METHOD_TESTAMENTARY_TRUST,
            IntergenerationalTransferCalculator::METHOD_LIFE_INSURANCE,
            IntergenerationalTransferCalculator::METHOD_BUSINESS_INTERESTS
        ];

        foreach ($expectedStrategies as $strategy) {
            $this->assertArrayHasKey($strategy, $strategies, "Strategy {$strategy} should be present");
        }

        // Check that each strategy has required fields
        foreach ($strategies as $strategy) {
            $this->assertArrayHasKey('method', $strategy);
            $this->assertArrayHasKey('transfer_amount', $strategy);
            $this->assertArrayHasKey('tax_savings', $strategy);
            $this->assertArrayHasKey('efficiency_score', $strategy);
            $this->assertArrayHasKey('risk_level', $strategy);
            $this->assertArrayHasKey('complexity', $strategy);
            $this->assertArrayHasKey('control_retained', $strategy);
            $this->assertArrayHasKey('pros', $strategy);
            $this->assertArrayHasKey('cons', $strategy);
        }
    }

    /**
     * Test generational impact calculation
     */
    public function testCalculateTransferEfficiencyGenerationalImpact(): void
    {
        // Arrange
        $portfolio = [['type' => 'equity', 'value' => 300000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 30;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert
        $generationalImpact = $result['generational_impact'];

        $this->assertArrayHasKey('immediate_transfer', $generationalImpact);
        $this->assertArrayHasKey('deferred_transfer', $generationalImpact);
        $this->assertArrayHasKey('multi_generational_benefit', $generationalImpact);
        $this->assertArrayHasKey('compounding_advantage', $generationalImpact);

        $this->assertGreaterThanOrEqual(0, $generationalImpact['immediate_transfer']);
        $this->assertGreaterThanOrEqual(0, $generationalImpact['deferred_transfer']);
        $this->assertGreaterThanOrEqual(0, $generationalImpact['compounding_advantage']);
        $this->assertGreaterThanOrEqual(0, $generationalImpact['multi_generational_benefit']);
    }

    /**
     * Test result structure completeness
     */
    public function testCalculateTransferEfficiencyResultStructure(): void
    {
        // Arrange
        $portfolio = [['type' => 'mixed', 'value' => 300000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 25;

        // Act
        $result = $this->calculator->calculateTransferEfficiency($portfolio, $taxSituation, $timeHorizon);

        // Assert - verify all expected keys are present
        $expectedKeys = [
            'estate_value',
            'time_horizon',
            'transfer_strategies',
            'optimal_strategy',
            'tax_efficiency',
            'generational_impact',
            'efficiency_score',
            'estimated_savings',
            'recommendations'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain key: {$key}");
        }

        // Verify nested structures
        $this->assertIsArray($result['estate_value']);
        $this->assertIsArray($result['transfer_strategies']);
        $this->assertIsArray($result['optimal_strategy']);
        $this->assertIsArray($result['tax_efficiency']);
        $this->assertIsArray($result['generational_impact']);
        $this->assertIsArray($result['recommendations']);

        // Verify numeric values
        $this->assertIsNumeric($result['efficiency_score']);
        $this->assertIsNumeric($result['estimated_savings']);
        $this->assertIsInt($result['time_horizon']);

        $this->assertGreaterThanOrEqual(0, $result['efficiency_score']);
        $this->assertLessThanOrEqual(100, $result['efficiency_score']);
        $this->assertGreaterThanOrEqual(0, $result['estimated_savings']);
        $this->assertGreaterThanOrEqual(0, $result['time_horizon']);
    }
}
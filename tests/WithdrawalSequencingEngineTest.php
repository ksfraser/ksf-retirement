<?php

declare(strict_types=1);

namespace Ksfraser\Retirement\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Retirement\WithdrawalSequencingEngine;

/**
 * Withdrawal Sequencing Engine Test Suite
 *
 * Tests the WithdrawalSequencingEngine for optimal withdrawal sequencing.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class WithdrawalSequencingEngineTest extends TestCase
{
    private WithdrawalSequencingEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new WithdrawalSequencingEngine();
    }

    /**
     * Test successful withdrawal sequencing optimization
     */
    public function testOptimizeWithdrawalSequenceSuccess(): void
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
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('optimal_sequence', $result);
        $this->assertArrayHasKey('total_withdrawals', $result);
        $this->assertArrayHasKey('tax_efficiency', $result);
        $this->assertArrayHasKey('sustainability_score', $result);
        $this->assertArrayHasKey('yearly_optimization', $result);
        $this->assertArrayHasKey('withdrawal_strategy', $result);

        $this->assertGreaterThan(0, $result['tax_efficiency']);
        $this->assertGreaterThan(0, $result['sustainability_score']);
        $this->assertLessThanOrEqual(100, $result['tax_efficiency']);
        $this->assertLessThanOrEqual(100, $result['sustainability_score']);
        $this->assertIsArray($result['optimal_sequence']);
    }

    /**
     * Test optimization with short time horizon
     */
    public function testOptimizeWithdrawalSequenceShortHorizon(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 100000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 50000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Alberta', 'bracket' => 'high'];
        $timeHorizon = 3;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertGreaterThan(60, $result['tax_efficiency']); // Higher efficiency for short horizon
        $this->assertGreaterThan(70, $result['sustainability_score']); // Good sustainability

        // Check that tax-deferred accounts are prioritized for short horizons
        $sequence = implode(' ', $result['optimal_sequence']);
        $this->assertStringContainsString('tax_deferred', $sequence);
    }

    /**
     * Test optimization with long time horizon
     */
    public function testOptimizeWithdrawalSequenceLongHorizon(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'bonds', 'value' => 500000, 'account_type' => 'registered'],
            ['type' => 'cash', 'value' => 200000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = ['marginal_rate' => 0.25, 'province' => 'Quebec', 'bracket' => 'low'];
        $timeHorizon = 40;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertGreaterThan(70, $result['tax_efficiency']);
        $this->assertGreaterThan(80, $result['sustainability_score']); // High sustainability for long horizon

        // Check that registered accounts are preserved for long horizons
        $sequence = implode(' ', $result['optimal_sequence']);
        $this->assertStringContainsString('registered', $sequence);
    }

    /**
     * Test optimization with high tax bracket
     */
    public function testOptimizeWithdrawalSequenceHighTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 1000000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 500000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.53, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 20;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertGreaterThan(75, $result['tax_efficiency']); // High efficiency needed for high bracket

        // Check that tax-efficient strategies are prioritized
        $strategy = $result['withdrawal_strategy'];
        $this->assertStringContainsString('tax', strtolower($strategy));
    }

    /**
     * Test optimization with low tax bracket
     */
    public function testOptimizeWithdrawalSequenceLowTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'cash', 'value' => 100000, 'account_type' => 'tax_deferred'],
            ['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.20, 'province' => 'Alberta', 'bracket' => 'low'];
        $timeHorizon = 15;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertGreaterThan(50, $result['tax_efficiency']); // Moderate efficiency for low bracket
        $this->assertGreaterThan(60, $result['sustainability_score']);
    }

    /**
     * Test optimization with empty portfolio
     */
    public function testOptimizeWithdrawalSequenceEmptyPortfolio(): void
    {
        // Arrange
        $portfolio = [];
        $taxSituation = ['marginal_rate' => 0.35, 'province' => 'BC', 'bracket' => 'medium'];
        $timeHorizon = 20;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertEquals(0, $result['total_withdrawals']);
        $this->assertEquals(0, $result['tax_efficiency']);
        $this->assertEquals(0, $result['sustainability_score']);
        $this->assertEmpty($result['optimal_sequence']);
        $this->assertEmpty($result['yearly_optimization']);
    }

    /**
     * Test optimization with zero time horizon
     */
    public function testOptimizeWithdrawalSequenceZeroHorizon(): void
    {
        // Arrange
        $portfolio = [['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.30, 'province' => 'BC', 'bracket' => 'medium'];
        $timeHorizon = 0;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertEquals(0, $result['total_withdrawals']);
        $this->assertEquals(0, $result['tax_efficiency']);
        $this->assertEquals(0, $result['sustainability_score']);
    }

    /**
     * Test optimization with only tax-deferred assets
     */
    public function testOptimizeWithdrawalSequenceOnlyTaxDeferred(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'cash', 'value' => 300000, 'account_type' => 'tax_deferred'],
            ['type' => 'bonds', 'value' => 200000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 25;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert
        $this->assertGreaterThan(0, $result['total_withdrawals']);
        $this->assertGreaterThan(85, $result['tax_efficiency']); // High efficiency with tax-deferred
        $this->assertGreaterThan(75, $result['sustainability_score']);

        // Check that tax-deferred is prioritized
        $sequence = implode(' ', $result['optimal_sequence']);
        $this->assertStringContainsString('tax_deferred', $sequence);
    }

    /**
     * Test exception handling for invalid portfolio
     */
    public function testOptimizeWithdrawalSequenceInvalidPortfolio(): void
    {
        // Arrange
        $portfolio = [['invalid' => 'data']]; // Missing required fields
        $taxSituation = ['marginal_rate' => 0.35];
        $timeHorizon = 20;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);
    }

    /**
     * Test exception handling for invalid tax situation
     */
    public function testOptimizeWithdrawalSequenceInvalidTaxSituation(): void
    {
        // Arrange
        $portfolio = [['type' => 'equity', 'value' => 100000, 'account_type' => 'registered']];
        $taxSituation = ['invalid' => 'data']; // Missing marginal_rate
        $timeHorizon = 20;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);
    }

    /**
     * Test optimization result structure
     */
    public function testOptimizeWithdrawalSequenceResultStructure(): void
    {
        // Arrange
        $portfolio = [['type' => 'mixed', 'value' => 300000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];
        $timeHorizon = 25;

        // Act
        $result = $this->engine->optimizeWithdrawalSequence($portfolio, null, $timeHorizon, $taxSituation);

        // Assert - verify all expected keys are present
        $expectedKeys = [
            'optimal_sequence',
            'total_withdrawals',
            'tax_efficiency',
            'sustainability_score',
            'yearly_optimization',
            'withdrawal_strategy',
            'account_utilization',
            'tax_impact_analysis',
            'sustainability_analysis'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain key: {$key}");
        }

        // Verify structure of nested arrays
        $this->assertIsArray($result['optimal_sequence']);
        $this->assertIsArray($result['yearly_optimization']);
        $this->assertIsArray($result['account_utilization']);
        $this->assertIsArray($result['tax_impact_analysis']);
        $this->assertIsArray($result['sustainability_analysis']);

        // Verify numeric values
        $this->assertIsNumeric($result['total_withdrawals']);
        $this->assertIsNumeric($result['tax_efficiency']);
        $this->assertIsNumeric($result['sustainability_score']);

        $this->assertGreaterThanOrEqual(0, $result['total_withdrawals']);
        $this->assertGreaterThanOrEqual(0, $result['tax_efficiency']);
        $this->assertGreaterThanOrEqual(0, $result['sustainability_score']);
        $this->assertLessThanOrEqual(100, $result['tax_efficiency']);
        $this->assertLessThanOrEqual(100, $result['sustainability_score']);
    }
}
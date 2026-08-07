<?php

declare(strict_types=1);

namespace Ksfraser\Retirement\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tax Location Optimizer Test Suite
 *
 * Tests the TaxLocationOptimizer for tax-efficient asset location.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class TaxLocationOptimizerTest extends TestCase
{
    private TaxLocationOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->optimizer = new TaxLocationOptimizer();
    }

    /**
     * Test successful tax location optimization
     */
    public function testOptimizeAssetLocationSuccess(): void
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

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('optimal_allocation', $result);
        $this->assertArrayHasKey('tax_savings', $result);
        $this->assertArrayHasKey('efficiency_score', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('current_allocation', $result);
        $this->assertArrayHasKey('reallocation_suggestions', $result);

        $this->assertGreaterThan(0, $result['tax_savings']);
        $this->assertGreaterThan(0, $result['efficiency_score']);
        $this->assertLessThanOrEqual(100, $result['efficiency_score']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test optimization with high tax bracket
     */
    public function testOptimizeAssetLocationHighTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 1000000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 500000, 'account_type' => 'registered']
        ];

        $taxSituation = [
            'marginal_rate' => 0.53,
            'province' => 'Ontario',
            'bracket' => 'high'
        ];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertGreaterThan(50000, $result['tax_savings']); // High tax savings expected
        $this->assertGreaterThan(80, $result['efficiency_score']); // High efficiency expected

        // Check that high-growth assets are recommended for registered accounts
        $recommendations = implode(' ', $result['recommendations']);
        $this->assertStringContains('registered', strtolower($recommendations));
    }

    /**
     * Test optimization with low tax bracket
     */
    public function testOptimizeAssetLocationLowTaxBracket(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'cash', 'value' => 100000, 'account_type' => 'tax_deferred'],
            ['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']
        ];

        $taxSituation = [
            'marginal_rate' => 0.20,
            'province' => 'Alberta',
            'bracket' => 'low'
        ];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertGreaterThan(0, $result['tax_savings']);
        $this->assertGreaterThan(40, $result['efficiency_score']); // Moderate efficiency expected

        // Check that tax-deferred assets are utilized
        $recommendations = implode(' ', $result['recommendations']);
        $this->assertStringContains('tax', strtolower($recommendations));
    }

    /**
     * Test optimization with empty portfolio
     */
    public function testOptimizeAssetLocationEmptyPortfolio(): void
    {
        // Arrange
        $portfolio = [];
        $taxSituation = ['marginal_rate' => 0.35, 'province' => 'BC', 'bracket' => 'medium'];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertEquals(0, $result['tax_savings']);
        $this->assertEquals(0, $result['efficiency_score']);
        $this->assertEmpty($result['optimal_allocation']);
        $this->assertEmpty($result['recommendations']);
    }

    /**
     * Test optimization with only registered assets
     */
    public function testOptimizeAssetLocationOnlyRegistered(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 300000, 'account_type' => 'registered'],
            ['type' => 'bonds', 'value' => 200000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Quebec', 'bracket' => 'high'];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertEquals(0, $result['tax_savings']); // No tax savings possible
        $this->assertGreaterThan(90, $result['efficiency_score']); // Already optimal

        // Check that no reallocation is suggested
        $recommendations = implode(' ', $result['recommendations']);
        $this->assertStringContains('optimal', strtolower($recommendations));
    }

    /**
     * Test optimization with mixed asset types
     */
    public function testOptimizeAssetLocationMixedAssets(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 400000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 300000, 'account_type' => 'registered'],
            ['type' => 'real_estate', 'value' => 200000, 'account_type' => 'non_registered'],
            ['type' => 'cash', 'value' => 100000, 'account_type' => 'tax_deferred']
        ];

        $taxSituation = ['marginal_rate' => 0.45, 'province' => 'Ontario', 'bracket' => 'high'];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertGreaterThan(0, $result['tax_savings']);
        $this->assertArrayHasKey('registered', $result['optimal_allocation']);
        $this->assertArrayHasKey('non_registered', $result['optimal_allocation']);
        $this->assertArrayHasKey('tax_deferred', $result['optimal_allocation']);

        // Check that different account types are utilized
        $this->assertGreaterThan(0, count($result['optimal_allocation']['registered']));
        $this->assertGreaterThan(0, count($result['optimal_allocation']['non_registered']));
    }

    /**
     * Test exception handling for invalid portfolio data
     */
    public function testOptimizeAssetLocationInvalidPortfolio(): void
    {
        // Arrange
        $portfolio = [['invalid' => 'data']]; // Missing required fields
        $taxSituation = ['marginal_rate' => 0.35];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);
    }

    /**
     * Test exception handling for invalid tax situation
     */
    public function testOptimizeAssetLocationInvalidTaxSituation(): void
    {
        // Arrange
        $portfolio = [['type' => 'equity', 'value' => 100000, 'account_type' => 'registered']];
        $taxSituation = ['invalid' => 'data']; // Missing marginal_rate

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);
    }

    /**
     * Test optimization with zero tax rate
     */
    public function testOptimizeAssetLocationZeroTaxRate(): void
    {
        // Arrange
        $portfolio = [
            ['type' => 'equity', 'value' => 200000, 'account_type' => 'non_registered'],
            ['type' => 'bonds', 'value' => 100000, 'account_type' => 'registered']
        ];

        $taxSituation = ['marginal_rate' => 0.0, 'province' => 'Alberta', 'bracket' => 'low'];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert
        $this->assertEquals(0, $result['tax_savings']); // No tax savings with 0% rate
        $this->assertGreaterThan(0, $result['efficiency_score']); // Still has efficiency score
    }

    /**
     * Test optimization result structure
     */
    public function testOptimizeAssetLocationResultStructure(): void
    {
        // Arrange
        $portfolio = [['type' => 'mixed', 'value' => 300000, 'account_type' => 'registered']];
        $taxSituation = ['marginal_rate' => 0.40, 'province' => 'Ontario', 'bracket' => 'high'];

        // Act
        $result = $this->optimizer->optimizeTaxLocation($portfolio, $taxSituation);

        // Assert - verify all expected keys are present
        $expectedKeys = [
            'optimal_allocation',
            'current_allocation',
            'tax_savings',
            'efficiency_score',
            'reallocation_suggestions',
            'recommendations',
            'account_efficiency',
            'asset_preferences'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result should contain key: {$key}");
        }

        // Verify optimal allocation structure
        $this->assertIsArray($result['optimal_allocation']);
        $this->assertIsArray($result['current_allocation']);
        $this->assertIsArray($result['reallocation_suggestions']);
        $this->assertIsArray($result['recommendations']);
        $this->assertIsArray($result['account_efficiency']);
        $this->assertIsArray($result['asset_preferences']);

        // Verify numeric values
        $this->assertIsNumeric($result['tax_savings']);
        $this->assertIsNumeric($result['efficiency_score']);
        $this->assertGreaterThanOrEqual(0, $result['tax_savings']);
        $this->assertGreaterThanOrEqual(0, $result['efficiency_score']);
        $this->assertLessThanOrEqual(100, $result['efficiency_score']);
    }
}
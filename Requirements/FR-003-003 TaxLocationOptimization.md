# FR-003-003 TaxLocationOptimization: Tax Location Optimization

**Related:** BR-003 Retirement Planning, UC-003-001 RetirementPlanningUseCases
**Engine:** `Ksfraser\\Retirement\\TaxLocationOptimizer`

## Description
Optimize asset location for tax efficiency.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's plan summary.

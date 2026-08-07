# FR-003-001 IntergenerationalTransfer: Intergenerational Transfer

**Related:** BR-003 Retirement Planning, UC-003-001 RetirementPlanningUseCases
**Engine:** `Ksfraser\\Retirement\\IntergenerationalTransferCalculator`

## Description
Project wealth transfer across generations, tax-aware.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's plan summary.

# FR-003-002 WithdrawalSequencing: Withdrawal Sequencing

**Related:** BR-003 Retirement Planning, UC-003-001 RetirementPlanningUseCases
**Engine:** `Ksfraser\\Retirement\\WithdrawalSequencingEngine`

## Description
Optimize withdrawal order across accounts in retirement.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's plan summary.

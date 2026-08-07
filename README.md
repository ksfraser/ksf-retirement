# ksf_retirement

Retirement Planning calculation engines — business logic. Part of the KSF calculation package family.

- Namespace: `Ksfraser\Retirement`
- Framework: `ksfraser/ksf_modules_common` (`Ksfraser\ModulesCommon`)
- Exceptions: `ksfraser/exceptions`

## Engines
| Engine | Purpose |
|--------|---------|
| `IntergenerationalTransferCalculator` | Project wealth transfer across generations, tax-aware. |
| `WithdrawalSequencingEngine` | Optimize withdrawal order across accounts in retirement. |
| `TaxLocationOptimizer` | Optimize asset location for tax efficiency. |

## Requirements (BABOK)
- `Requirements/BR-003 Retirement Planning.md`
- `Requirements/FR-003-001..003 *.md`
- `Requirements/UC-003-001 RetirementPlanningUseCases.md`

## Status
Scaffold — engines extracted and namespaced.

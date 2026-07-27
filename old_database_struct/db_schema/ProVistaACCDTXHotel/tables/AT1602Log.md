# Table: AT1602Log
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Id** | `int` | NO | YES |  | PK |
| 2 | **VoucherNo** | `varchar(20)` | NO | NO |  |  |
| 3 | **DivisionID** | `varchar(20)` | NO | NO |  |  |
| 4 | **TranMonth** | `int` | NO | NO |  |  |
| 5 | **TranYear** | `int` | NO | NO |  |  |
| 6 | **ToolID** | `varchar(50)` | NO | NO |  |  |
| 7 | **ConvertedAmountAfter** | `money` | NO | NO |  |  |
| 8 | **RemainAmountAfter** | `money` | NO | NO |  |  |
| 9 | **PeriodAfter** | `int` | NO | NO |  |  |
| 10 | **AllocateValuetAfter** | `money` | NO | NO |  |  |
| 11 | **QuantityAfter** | `int` | NO | NO |  |  |
| 12 | **BrokenAndPaymentValueAfter** | `money` | NO | NO |  |  |
| 13 | **ApportionRateAfter** | `decimal(18,4)` | NO | NO |  |  |
| 14 | **BeginDateAfter** | `datetime` | NO | NO |  |  |
| 15 | **EndDateAfter** | `datetime` | NO | NO |  |  |
| 16 | **Type** | `int` | NO | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

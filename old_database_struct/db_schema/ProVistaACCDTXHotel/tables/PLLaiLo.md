# Table: PLLaiLo
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **item** | `nvarchar(250)` | YES | NO |  |  |
| 2 | **AccFrom** | `varchar(20)` | YES | NO |  |  |
| 3 | **AccTo** | `varchar(20)` | YES | NO |  |  |
| 4 | **MonthActual** | `money` | YES | NO |  |  |
| 5 | **MonthActualPer** | `float` | YES | NO |  |  |
| 6 | **MonthBudget** | `money` | YES | NO |  |  |
| 7 | **MonthBudgetPer** | `float` | YES | NO |  |  |
| 8 | **MonthLastYear** | `money` | YES | NO |  |  |
| 9 | **MonthLastYearPer** | `float` | YES | NO |  |  |
| 10 | **YearhActual** | `money` | YES | NO |  |  |
| 11 | **YearActualPer** | `float` | YES | NO |  |  |
| 12 | **YearBudget** | `money` | YES | NO |  |  |
| 13 | **YearBudgetPer** | `float` | YES | NO |  |  |
| 14 | **YearLastYear** | `money` | YES | NO |  |  |
| 15 | **YearLastYearPer** | `float` | YES | NO |  |  |
| 16 | **itemID** | `varchar(50)` | YES | NO |  |  |
| 17 | **ParentID** | `varchar(50)` | YES | NO |  |  |
| 18 | **Level** | `bigint` | YES | NO |  |  |
| 19 | **Sign** | `char(1)` | YES | NO |  |  |
| 20 | **IsPrint** | `tinyint` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: PLResult_anhnguyet
- **Est. Row Count**: 177

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **LineID** | `nvarchar(250)` | YES | NO |  |  |
| 2 | **item** | `nvarchar(250)` | YES | NO |  |  |
| 3 | **AccFrom** | `varchar(20)` | YES | NO |  |  |
| 4 | **AccTo** | `varchar(20)` | YES | NO |  |  |
| 5 | **MonthActual** | `money` | YES | NO |  |  |
| 6 | **MonthActualPer** | `float` | YES | NO |  |  |
| 7 | **MonthBudget** | `money` | YES | NO |  |  |
| 8 | **MonthBudgetPer** | `float` | YES | NO |  |  |
| 9 | **MonthBudgetLastYear** | `money` | YES | NO |  |  |
| 10 | **MonthLastYear** | `money` | YES | NO |  |  |
| 11 | **MonthLastYearPer** | `float` | YES | NO |  |  |
| 12 | **YearhActual** | `money` | YES | NO |  |  |
| 13 | **YearActualPer** | `float` | YES | NO |  |  |
| 14 | **YearBudget** | `money` | YES | NO |  |  |
| 15 | **YearBudgetPer** | `float` | YES | NO |  |  |
| 16 | **YearBudgetLastYear** | `money` | YES | NO |  |  |
| 17 | **YearLastYear** | `money` | YES | NO |  |  |
| 18 | **YearLastYearPer** | `float` | YES | NO |  |  |
| 19 | **itemID** | `varchar(50)` | YES | NO |  |  |
| 20 | **ParentID** | `varchar(50)` | YES | NO |  |  |
| 21 | **Level** | `bigint` | YES | NO |  |  |
| 22 | **Sign** | `char(1)` | YES | NO |  |  |
| 23 | **IsPrint** | `tinyint` | YES | NO |  |  |
| 24 | **IsBold** | `int` | YES | NO |  |  |
| 25 | **LastMonth** | `money` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: SchemaInvoiceInherit
- **Est. Row Count**: 96

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Code** | `varchar(10)` | NO | NO |  |  |
| 2 | **CodeCT** | `varchar(10)` | NO | NO |  |  |
| 3 | **ID** | `varchar(2)` | NO | NO |  |  |
| 4 | **PaymentModeID** | `nvarchar(20)` | NO | NO |  |  |
| 5 | **CodeName** | `nvarchar(max)` | YES | NO |  |  |
| 6 | **DebitAccID** | `varchar(20)` | YES | NO |  |  |
| 7 | **CreditAccID** | `varchar(20)` | YES | NO |  |  |
| 8 | **IsAuto** | `bit` | YES | NO |  |  |
| 9 | **Fomula** | `varchar(50)` | YES | NO |  |  |
| 10 | **VATGroupID** | `varchar(20)` | YES | NO |  |  |
| 11 | **VATTypeID** | `varchar(20)` | YES | NO |  |  |
| 12 | **Ana01ID** | `varchar(20)` | YES | NO |  |  |
| 13 | **Ana02ID** | `varchar(20)` | YES | NO |  |  |
| 14 | **Ana03ID** | `varchar(20)` | YES | NO |  |  |
| 15 | **BDescription** | `nvarchar(max)` | YES | NO |  |  |
| 16 | **TDescription** | `nvarchar(max)` | YES | NO |  |  |
| 17 | **Status** | `bit` | NO | NO | ((1)) |  |
| 18 | **DebitBankAccountID** | `varchar(20)` | YES | NO |  |  |
| 19 | **CreditBankAccountID** | `varchar(20)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

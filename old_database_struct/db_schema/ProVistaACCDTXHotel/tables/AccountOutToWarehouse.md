# Table: AccountOutToWarehouse
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **WarehouseID** | `varchar(20)` | NO | NO |  | PK |
| 2 | **GroupAccountID** | `varchar(50)` | NO | NO |  | PK |
| 3 | **DebitAccountId** | `varchar(20)` | YES | NO |  |  |
| 4 | **Ana01Id** | `varchar(50)` | YES | NO |  |  |
| 5 | **Ana02Id** | `varchar(50)` | YES | NO |  |  |
| 6 | **Ana03Id** | `varchar(50)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

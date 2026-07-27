# Table: LockWarehouse
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **WarehouseID** | `varchar(20)` | NO | NO |  | PK |
| 2 | **IsLock** | `bit` | NO | NO |  | PK |
| 3 | **TranMonth** | `int` | YES | NO |  |  |
| 4 | **TranYear** | `int` | YES | NO |  |  |
| 5 | **WarehouseName** | `varchar(100)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: WarehouseTemp
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Id** | `int` | NO | NO |  | PK |
| 2 | **Orders** | `int` | YES | NO |  |  |
| 3 | **WarehouseInput** | `varchar(50)` | YES | NO |  |  |
| 4 | **WarehouseOutput** | `varchar(50)` | YES | NO |  |  |
| 5 | **InventoryID** | `varchar(50)` | YES | NO |  |  |
| 6 | **Disabled** | `bit` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

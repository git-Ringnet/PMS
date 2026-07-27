# Table: MenuTypeCost
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **MenuTypeID** | `int` | NO | NO |  | PK |
| 2 | **MenuTypeName** | `nvarchar(500)` | YES | NO |  |  |
| 3 | **WareHouseID** | `varchar(20)` | NO | NO |  | PK |
| 4 | **WarehouseName** | `nvarchar(500)` | YES | NO |  |  |
| 5 | **AccountID** | `varchar(20)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

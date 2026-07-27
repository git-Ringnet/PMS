# Table: BT1603Detail
- **Est. Row Count**: 4

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **ToolID** | `varchar(20)` | NO | NO |  | PK, FK |
| 2 | **PeriodMonth** | `int` | NO | NO |  | PK |
| 3 | **PeriodYear** | `int` | NO | NO |  | PK |
| 4 | **MaintenanceProgress** | `int` | YES | NO |  |  |
| 5 | **MaintenanceNote** | `nvarchar(250)` | YES | NO |  |  |
| 6 | **MaintenanceStatus** | `int` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
| Column | References Table | References Column | Constraint Name |
|--------|------------------|-------------------|-----------------|
| `ToolID` | [BT1603](BT1603.md) | `ToolID` | `FK_BT1603Detail_BT1603` |

### Incoming Foreign Keys (Referenced By)
*None*

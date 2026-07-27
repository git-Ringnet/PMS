# Table: BT1503Detail
- **Est. Row Count**: 7

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **AssetID** | `varchar(20)` | NO | NO |  | PK, FK |
| 2 | **PeriodMonth** | `int` | NO | NO |  | PK |
| 3 | **PeriodYear** | `int` | NO | NO |  | PK |
| 4 | **MaintenanceProgress** | `int` | YES | NO |  |  |
| 5 | **MaintenanceNote** | `nvarchar(250)` | YES | NO |  |  |
| 6 | **MaintenanceStatus** | `int` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
| Column | References Table | References Column | Constraint Name |
|--------|------------------|-------------------|-----------------|
| `AssetID` | [BT1503](BT1503.md) | `AssetID` | `FK_BT1503Detail_BT1503` |

### Incoming Foreign Keys (Referenced By)
*None*

# Table: Menu
- **Est. Row Count**: 389

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **ID** | `int` | NO | NO |  | PK |
| 2 | **ControlName** | `nvarchar(150)` | YES | NO |  |  |
| 3 | **MenuNameVie** | `nvarchar(500)` | YES | NO |  |  |
| 4 | **MenuNameEng** | `nvarchar(500)` | YES | NO |  |  |
| 5 | **Level** | `int` | YES | NO |  |  |
| 6 | **MenuParent** | `int` | YES | NO |  |  |
| 7 | **ModuleID** | `int` | NO | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: sysdiagrams
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **name** | `sysname` | NO | NO |  |  |
| 2 | **principal_id** | `int` | NO | NO |  |  |
| 3 | **diagram_id** | `int` | NO | YES |  | PK |
| 4 | **version** | `int` | YES | NO |  |  |
| 5 | **definition** | `varbinary(max)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

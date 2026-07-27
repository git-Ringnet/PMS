# Table: LogHistory
- **Est. Row Count**: 32,638

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **ID** | `int` | NO | YES |  | PK |
| 2 | **Account** | `varchar(50)` | NO | NO |  |  |
| 3 | **Computer** | `varchar(50)` | YES | NO |  |  |
| 4 | **Time** | `varchar(14)` | NO | NO |  |  |
| 5 | **Action** | `tinyint` | NO | NO |  |  |
| 6 | **Description** | `nvarchar(max)` | NO | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

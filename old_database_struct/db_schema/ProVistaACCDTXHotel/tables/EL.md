# Table: EL
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **ElId** | `int` | NO | YES |  | PK |
| 2 | **ElTime** | `datetime` | NO | NO |  |  |
| 3 | **ElComp** | `varchar(20)` | YES | NO |  |  |
| 4 | **ElAcc** | `varchar(20)` | YES | NO |  |  |
| 5 | **ElGrp** | `varchar(20)` | YES | NO |  |  |
| 6 | **ElType** | `varchar(50)` | NO | NO |  |  |
| 7 | **ElDesc** | `nvarchar(max)` | NO | NO |  |  |
| 8 | **ElTarget** | `varchar(100)` | NO | NO |  |  |
| 9 | **ElDetail** | `nvarchar(max)` | NO | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

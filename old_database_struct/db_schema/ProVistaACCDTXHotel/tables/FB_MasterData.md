# Table: FB_MasterData
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **MD_System** | `varchar(5)` | NO | NO |  | PK |
| 2 | **MD_MaSo** | `bigint` | NO | NO |  | PK |
| 3 | **MD_Language** | `varchar(20)` | NO | NO |  |  |
| 4 | **MD_Table** | `varchar(100)` | NO | NO |  |  |
| 5 | **MD_Key** | `varchar(50)` | NO | NO |  |  |
| 6 | **MD_Value** | `nvarchar(200)` | NO | NO |  |  |
| 7 | **MD_MoTa** | `nvarchar(200)` | YES | NO |  |  |
| 8 | **MD_Loai** | `varchar(20)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: SoftwareVersion
- **Est. Row Count**: 1

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Version** | `varchar(10)` | NO | NO |  | PK |
| 2 | **Description** | `nvarchar(max)` | YES | NO |  |  |
| 3 | **Status** | `bit` | YES | NO |  |  |
| 4 | **CreateDate** | `datetime` | YES | NO |  |  |
| 5 | **LastModified** | `datetime` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

# Table: ThongSo
- **Est. Row Count**: 160

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Ma_ThongSo** | `varchar(50)` | NO | NO |  | PK |
| 2 | **Ten** | `nvarchar(200)` | NO | NO |  |  |
| 3 | **GiaTri** | `nvarchar(500)` | YES | NO |  |  |
| 4 | **DienGiai** | `nvarchar(200)` | YES | NO |  |  |
| 5 | **PhanHe** | `nchar(2)` | NO | NO |  |  |
| 6 | **DivisionID** | `varchar(20)` | YES | NO |  |  |
| 7 | **Hidden** | `bit` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

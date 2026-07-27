# Table: FB_ThucDon_SanPham
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **TD_MaSo** | `nvarchar(10)` | NO | NO |  |  |
| 2 | **SP_MaSo** | `nvarchar(10)` | NO | NO |  |  |
| 3 | **MNSP_SoLuong** | `float` | YES | NO |  |  |
| 4 | **MNSP_DonGia** | `float` | YES | NO |  |  |
| 5 | **MNSP_ThanhTien** | `float` | YES | NO |  |  |
| 6 | **MNSP_GhiChu** | `ntext` | YES | NO |  |  |
| 7 | **MNSP_Active** | `char(1)` | YES | NO |  |  |
| 8 | **MNSP_MenuID** | `varchar(20)` | YES | NO |  |  |
| 9 | **Deleted** | `int` | NO | NO |  |  |
| 10 | **DivisionID** | `varchar(20)` | YES | NO |  |  |
| 11 | **SysDivision** | `varchar(20)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

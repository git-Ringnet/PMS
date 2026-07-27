# Table: FB_ChiTietSanPham
- **Est. Row Count**: 49

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **SP_MaSo** | `char(10)` | NO | NO |  | PK |
| 2 | **HH_MaSo** | `varchar(20)` | NO | NO |  | PK |
| 3 | **DVT_MaSoHH** | `varchar(20)` | NO | NO |  |  |
| 4 | **CTSP_SoLuong** | `float` | NO | NO |  |  |
| 5 | **CTSP_DonGia** | `float` | NO | NO |  |  |
| 6 | **CTSP_ThanhTien** | `float` | NO | NO |  |  |
| 7 | **CTSP_GhiChu** | `ntext` | YES | NO |  |  |
| 8 | **Deleted** | `int` | YES | NO |  |  |
| 9 | **DivisionID** | `varchar(20)` | NO | NO |  | PK |
| 10 | **CTSP_Kho** | `nvarchar(max)` | YES | NO |  |  |
| 11 | **CTSP_Percent** | `float` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

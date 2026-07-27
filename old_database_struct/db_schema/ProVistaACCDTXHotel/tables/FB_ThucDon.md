# Table: FB_ThucDon
- **Est. Row Count**: 82

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **TD_MaSo** | `nvarchar(10)` | NO | NO |  | PK |
| 2 | **TD_Ten** | `nvarchar(50)` | YES | NO |  |  |
| 3 | **TD_DienGiai** | `ntext` | YES | NO |  |  |
| 4 | **TD_CoSP** | `int` | YES | NO |  |  |
| 5 | **TD_Active** | `int` | YES | NO |  |  |
| 6 | **TD_MaSoCha** | `nvarchar(10)` | YES | NO |  |  |
| 7 | **TD_MaSoGoc** | `nvarchar(10)` | YES | NO |  |  |
| 8 | **TD_SoKhach** | `int` | YES | NO |  |  |
| 9 | **TD_TongTien** | `float` | YES | NO |  |  |
| 10 | **TD_GiaBan** | `float` | YES | NO |  |  |
| 11 | **TD_ADKhuyenMai** | `int` | YES | NO |  |  |
| 12 | **TD_Loai** | `int` | YES | NO |  |  |
| 13 | **TD_OutLet** | `varchar(10)` | YES | NO |  |  |
| 14 | **TD_PhongBan** | `varchar(10)` | YES | NO |  |  |
| 15 | **Deleted** | `int` | NO | NO | ((2)) |  |
| 16 | **MNSP_MenuID** | `varchar(20)` | YES | NO |  |  |
| 17 | **DivisionID** | `varchar(20)` | YES | NO |  |  |
| 18 | **SysDivision** | `varchar(20)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

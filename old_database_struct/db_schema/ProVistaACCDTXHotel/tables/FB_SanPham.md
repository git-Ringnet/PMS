# Table: FB_SanPham
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **SP_MaSo** | `char(10)` | NO | NO |  |  |
| 2 | **SP_SoHieuSP** | `nvarchar(20)` | YES | NO |  |  |
| 3 | **SP_TenSanPham** | `nvarchar(100)` | YES | NO |  |  |
| 4 | **SP_MoTa** | `nvarchar(200)` | YES | NO |  |  |
| 5 | **SP_DonViBan** | `varchar(5)` | YES | NO |  |  |
| 6 | **SP_GiaDinhLuong** | `money` | YES | NO |  |  |
| 7 | **SP_ChiPhiKhac** | `float` | YES | NO |  |  |
| 8 | **SP_ChiTietChiPhiKhac** | `nvarchar(255)` | YES | NO |  |  |
| 9 | **SP_GiaVon** | `money` | YES | NO |  |  |
| 10 | **SP_DVTTGiaGoc** | `varchar(5)` | YES | NO |  |  |
| 11 | **SP_GiaGoc** | `money` | YES | NO |  |  |
| 12 | **SP_PhucVu** | `float` | YES | NO |  |  |
| 13 | **SP_TienPhucVu** | `money` | NO | NO |  |  |
| 14 | **SP_ThueDacBiet** | `float` | YES | NO |  |  |
| 15 | **SP_TienThueDacBiet** | `money` | YES | NO |  |  |
| 16 | **SP_ThueVAT** | `float` | YES | NO |  |  |
| 17 | **SP_TienThueVAT** | `money` | NO | NO |  |  |
| 18 | **SP_GiaBan** | `money` | YES | NO |  |  |
| 19 | **SP_GiamGia** | `float` | YES | NO |  |  |
| 20 | **SP_TienGiamGia** | `money` | YES | NO |  |  |
| 21 | **SP_ImagePath** | `nvarchar(100)` | YES | NO |  |  |
| 22 | **SP_GhiChu** | `nvarchar(200)` | YES | NO |  |  |
| 23 | **SP_PhanNhom** | `varchar(3)` | NO | NO |  |  |
| 24 | **SP_Loai** | `int` | YES | NO |  |  |
| 25 | **LSP_MaSo** | `char(10)` | YES | NO |  |  |
| 26 | **SP_Alcohol** | `char(1)` | NO | NO |  |  |
| 27 | **SP_SuaGia** | `char(1)` | YES | NO |  |  |
| 28 | **SP_Raw** | `char(1)` | YES | NO |  |  |
| 29 | **HH_MaSo** | `varchar(10)` | YES | NO |  |  |
| 30 | **SP_Outlet** | `varchar(10)` | YES | NO |  |  |
| 31 | **SP_PhongBan** | `varchar(10)` | YES | NO |  |  |
| 32 | **OwnerUser** | `char(10)` | YES | NO |  |  |
| 33 | **RootOwnerUser** | `char(10)` | YES | NO |  |  |
| 34 | **CreatedUser** | `char(10)` | YES | NO |  |  |
| 35 | **CreatedDate** | `char(8)` | YES | NO |  |  |
| 36 | **CreatedHour** | `char(5)` | YES | NO |  |  |
| 37 | **UpdatedUser** | `char(10)` | YES | NO |  |  |
| 38 | **UpdatedDate** | `char(8)` | YES | NO |  |  |
| 39 | **UpdatedHour** | `char(5)` | YES | NO |  |  |
| 40 | **Deleted** | `int` | YES | NO |  |  |
| 41 | **SP_CoSanPhamThayThe** | `char(1)` | YES | NO |  |  |
| 42 | **SP_CoCongThucCheBien** | `char(1)` | YES | NO |  |  |
| 43 | **SP_SLTuongUngCTCB** | `float` | YES | NO |  |  |
| 44 | **SP_ThoiGianCheBien** | `float` | YES | NO |  |  |
| 45 | **SP_CachCheBien** | `ntext` | YES | NO |  |  |
| 46 | **SP_PhuongThucCheBien** | `char(15)` | YES | NO |  |  |
| 47 | **SP_ThoiGianPhucVu** | `float` | YES | NO |  |  |
| 48 | **SP_Printer** | `varchar(200)` | YES | NO |  |  |
| 49 | **DivisionID** | `varchar(20)` | YES | NO |  |  |
| 50 | **SysDivision** | `varchar(20)` | YES | NO |  |  |
| 51 | **SP_SoPhan** | `int` | YES | NO |  |  |
| 52 | **SP_ChonChiTiet** | `int` | YES | NO |  |  |
| 53 | **MS_MaSo** | `int` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

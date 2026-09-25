CREATE       PROC [dbo].[sp_TotalRevenueFromReportSetup]
@reportCode varchar(50),
@DateFrom date,
@DateTo date,
@GroupType varchar(10) = '0' -- 0: doanh thu chi tiet theo san pham
							 -- 1: doanh thu chi tiet theo hoa don
							 -- 2: doanh thu tong hop so tien theo tung outlet
							 -- 3: group theo HTTT
AS
BEGIN
	-- dinh nghia cho vong lap theo AT7621
	DECLARE @LineID varchar(50)
	DECLARE @LineCode varchar(50)
	DECLARE @LineDescription nvarchar(300)
	DECLARE @LevelID tinyint
	DECLARE @Sign varchar(1)
	DECLARE @AccuLineID varchar(50)
	DECLARE @IsPrint tinyint
	DECLARE @IsBold int
	DECLARE @DeparmentId varchar(500)
	DECLARE @OutletId varchar(500)
	DECLARE @LocationId varchar(500)
	DECLARE @ServiceId varchar(500)
	DECLARE @Color varchar(50)

	-- dinh nghia cho vong lap theo ngay
	DECLARE @Ngay date

	-- dinh nghia cho vong lap theo hoa don
	DECLARE @HTTT nvarchar(25)
	DECLARE @BillCode varchar(50)
	DECLARE @BillId varchar(20)
	DECLARE @GioVao varchar(30)
	DECLARE @GioRa varchar(30)

	-- dinh nghia thong tin dong
	DECLARE @SoLuongDong float = 0
	DECLARE @GiaBanDong float = 0
	DECLARE @GiaTruocGiamTangDong float = 0
	DECLARE @PhiPVDong float = 0
	DECLARE @ThueDong float = 0
	DECLARE @GiamGiaDong float = 0
	DECLARE @TangGiaDong float = 0
	DECLARE @ThanhTienDong float = 0
	DECLARE @TTTienMatDong float = 0
	DECLARE @TTChuyenKhoanDong float = 0
	DECLARE @TTNoDong float = 0
	DECLARE @TTKhacDong float = 0
	DECLARE @DoanhThuDaThuDong float = 0
	DECLARE @DoanhThuChuaThuDong float = 0

	-- dinh nghia thong tin ngay
	DECLARE @SoLuongNgay float = 0
	DECLARE @GiaBanNgay float = 0
	DECLARE @GiaTruocGiamTangNgay float = 0
	DECLARE @PhiPVNgay float = 0
	DECLARE @ThueNgay float = 0
	DECLARE @GiamGiaNgay float = 0
	DECLARE @TangGiaNgay float = 0
	DECLARE @ThanhTienNgay float = 0
	DECLARE @TTTienMatNgay float = 0
	DECLARE @TTChuyenKhoanNgay float = 0
	DECLARE @TTNoNgay float = 0
	DECLARE @TTKhacNgay float = 0
	DECLARE @DoanhThuDaThuNgay float = 0
	DECLARE @DoanhThuChuaThuNgay float = 0

	-- dinh nghia thong tin bill
	DECLARE @SoLuongBill float = 0
	DECLARE @GiaBanBill float = 0
	DECLARE @GiaTruocGiamTangBill float = 0
	DECLARE @PhiPVBill float = 0
	DECLARE @ThueBill float = 0
	DECLARE @GiamGiaBill float = 0
	DECLARE @TangGiaBill float = 0
	DECLARE @ThanhTienBill float = 0
	

	DECLARE @TTTienMat float = 0
	DECLARE @TTChuyenKhoan float = 0
	DECLARE @TTNo float = 0
	DECLARE @TTKhac float = 0
	DECLARE @DoanhThuDaThu float = 0
	DECLARE @DoanhThuChuaThu float = 0

	CREATE TABLE #RevenueTemp (STT int IDENTITY, Ngay varchar(50), CotNgay varchar(50), SoHD nvarchar(100), ProductId varchar(50), ProductName nvarchar(max), SoLuong float, DVT nvarchar(25), GiaBan float, GiaTruocGiamTang float, PhiPV float, Thue float, GiamGia float, TangGia float, ThanhTien float, HTTT nvarchar(25), [Type] varchar(10), BillCode varchar(20), AccuLineID varchar(50), LineID varchar(50), IsBold int, IsPrint int, Color varchar(50), LevelID varchar(20), GioVao nvarchar(30), GioRa nvarchar(30), TTTienMat float, TTChuyenKhoan float, TTNo float, TTKhac float, DoanhThuDaThu float, DoanhThuChuaThu float)

	DECLARE @SetupCursor CURSOR;
	DECLARE @DateCursor CURSOR;
	DECLARE @BillCursor CURSOR;
	DECLARE @LevelCursor CURSOR;
	BEGIN
		-- vong lap tung dong setup AT7621
		SET @SetupCursor = CURSOR FOR
		SELECT LineID, LineCode, LineDescription, LevelID, [Sign], AccuLineID, IsPrint, IsBold, DeparmentId, OutletId, LocationId, ServiceId, Color 
		FROM AT7621 
		WHERE ReportCode = @reportCode

		OPEN @SetupCursor
		FETCH NEXT FROM @SetupCursor INTO @LineID, @LineCode, @LineDescription, @LevelID, @Sign, @AccuLineID, @IsPrint, @IsBold, @DeparmentId, @OutletId, @LocationId, @ServiceId, @Color
		WHILE @@FETCH_STATUS = 0
		BEGIN
			SET @SoLuongDong = 0
			SET @GiaBanDong = 0
			SET @GiaTruocGiamTangDong = 0
			SET @PhiPVDong = 0
			SET @ThueDong = 0
			SET @GiamGiaDong = 0
			SET @TangGiaDong = 0
			SET @ThanhTienDong = 0
			SET @TTTienMatDong = 0
			SET @TTChuyenKhoanDong = 0
			SET @TTNoDong = 0
			SET @TTKhacDong = 0
			SET @DoanhThuDaThuDong = 0
			SET @DoanhThuChuaThuDong = 0

			--insert into dong setup
			INSERT INTO #RevenueTemp (SoHD, [Type], BillCode, AccuLineID, LineID, IsBold, IsPrint, Color, LevelID, TTTienMat,TTChuyenKhoan,TTNo,TTKhac) VALUES (@LineDescription,'LINE',@LineID, @AccuLineID, @LineID, @IsBold, @IsPrint, @Color, @LevelID,0,0,0,0)

			-- F&B
			IF(EXISTS(SELECT value FROM openjson(@DeparmentId) WHERE value = 'FB'))
				BEGIN
					-- vong lap theo ngay
					SET @DateCursor = CURSOR FOR 
					SELECT CAST(tt.Date as DATE) as PaymentDatee 
					FROM SP5000 
					left join SP3000 hddv on hddv.Ma = SP5000.ServicePayId
					left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID order by Date desc)
					WHERE Oulet in (SELECT value FROM openjson(@OutletId)) 
						and (@LocationId = '' or LocationId = @LocationId) 
						and PaymentStatus = 1
						and SP5000.Edit = 0
						and Deleted = 0
						and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = hddv.PaymentID),'')  not in (select Ma from SP1326 where HTMienPhi = 1)  
						and cast(PaymentDate as Date) between @DateFrom and @DateTo
						GROUP BY CAST(tt.Date as DATE) 
						ORDER BY PaymentDatee

					OPEN @DateCursor
					FETCH NEXT FROM @DateCursor INTO @Ngay
					WHILE @@FETCH_STATUS = 0
					BEGIN
						SET @SoLuongNgay = 0
						SET @GiaBanNgay = 0
						SET @GiaTruocGiamTangNgay = 0
						SET @PhiPVNgay = 0
						SET @ThueNgay = 0
						SET @GiamGiaNgay = 0
						SET @TangGiaNgay = 0
						SET @ThanhTienNgay = 0
						SET @TTTienMatNgay = 0
						SET @TTChuyenKhoanNgay = 0
						SET @TTNoNgay = 0
						SET @TTKhacNgay = 0
						SET @DoanhThuDaThuNgay = 0
						SET @DoanhThuChuaThuNgay = 0

						-- insert into ngay
						IF NOT EXISTS (select * FROM #RevenueTemp where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID)
							BEGIN
								INSERT INTO #RevenueTemp (Ngay, [Type], BillCode, IsBold, IsPrint, Color, CotNgay) VALUES (@Ngay, 'DATE', @LineID, @IsBold, @IsPrint, @Color, CONVERT(varchar(30), @Ngay, 103))
							END

						-- vong lap theo hoa don
						SET @BillCursor = CURSOR FOR
						SELECT SP5000.PaymentMethod, SP5000.BillCode, SP5000.BillId, SP5000.CreateTime, SP3000.OpenTime,
						ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (SP3000.PaymentID) and SP3002.PaymentMethod = 'CA' and SP5000.PaymentMethod not in ('Room','Group')),0) as TTTienMat,
						ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (SP3000.PaymentID) and SP3002.PaymentMethod = 'BT' and SP5000.PaymentMethod not in ('Room','Group')),0) as TTChuyenKhoan,
						ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (SP3000.PaymentID) and SP3002.PaymentMethod = 'AC' and SP5000.PaymentMethod not in ('Room','Group')),0) as TTNo,
						ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (SP3000.PaymentID) and SP3002.PaymentMethod not in ('CA','BT','AC') and SP5000.PaymentMethod not in ('Room','Group')),0) as TTKhac,
						ISNULL(case when SP3000.PaymentID is not null then SP5000.TotalAmount else 0 end, 0) as DoanhThuDaThu,
						ISNULL(case when SP3000.PaymentID is null then SP5000.TotalAmount else 0 end, 0) as DoanhThuChuaThu
						FROM SP5000
						LEFT JOIN SP3000 on SP3000.Ma = SP5000.ServicePayId
						left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = SP3000.PaymentID)
						WHERE Oulet in (SELECT value FROM openjson(@OutletId)) 
						and (@LocationId = '' or SP5000.LocationId = @LocationId) 
						and SP5000.PaymentStatus = 1
						and SP5000.Edit = 0
						and SP5000.Deleted = 0
						and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = SP3000.PaymentID),'')  not in (select Ma from SP1326 where HTMienPhi = 1)    
						and cast(tt.Date as Date) = @Ngay

						OPEN @BillCursor
						FETCH NEXT FROM @BillCursor INTO @HTTT, @BillCode, @BillId, @GioVao, @GioRa,@TTTienMat,@TTChuyenKhoan,@TTNo,@TTKhac, @DoanhThuDaThu, @DoanhThuChuaThu
						WHILE @@FETCH_STATUS = 0
						BEGIN
							SET @SoLuongBill = 0
							SET @GiaBanBill = 0
							SET @GiaTruocGiamTangBill = 0
							SET @PhiPVBill = 0
							SET @ThueBill = 0
							SET @GiamGiaBill = 0
							SET @TangGiaBill = 0
							SET @ThanhTienBill = 0
							

							-- insert into bill
							IF(@GroupType in ('0','1','3'))
								INSERT INTO #RevenueTemp (Ngay, SoHD, HTTT, [Type], BillCode, IsBold, IsPrint, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien,GioVao,GioRa,DoanhThuDaThu,DoanhThuChuaThu) VALUES (@Ngay, @BillCode, @HTTT, 'BILL', @BillCode, @IsBold, @IsPrint,0,0,0,0,0,0,0, @GioVao, @GioRa,@DoanhThuDaThu,@DoanhThuChuaThu)
							-- insert into detail
							IF(@GroupType = '0')
								BEGIN
									INSERT INTO #RevenueTemp (Ngay, ProductId, ProductName, SoLuong, DVT, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien, [Type], BillCode, IsBold, IsPrint) SELECT @Ngay, SP.ProductCode, CT.ProductName, ISNULL(CT.Quantity, 0), DV.[Name], ISNULL(CT.Rate, 0), ISNULL(CT.Amount, 0), ISNULL(CT.ServiceChargeRate, 0), ISNULL(CT.TaxRate, 0), ISNULL(CT.DiscountAmount, 0), ISNULL(CT.IncreaseAmount, 0), ISNULL(CT.TotalAmount, 0), 'DETAIL', @BillCode, 0, 1
										FROM SP5100 CT
										LEFT JOIN SP5300 SP ON SP.ProductId = CT.ProductId
										LEFT JOIN SP5401 DV ON DV.Id = CT.Id
										WHERE CT.BillId = @BillId and CT.Deleted = 0
								END
						  	
							-- tinh toan tien detail + vao bill
							IF(EXISTS(SELECT * FROM SP5100 WHERE BillId = @BillId  and Deleted = 0))
								BEGIN
									SELECT @SoLuongBill = D.Quantity, 
								   @GiaBanBill = D.Rate,  
								   @GiaTruocGiamTangBill = D.Amount,  
								   @PhiPVBill = D.ServiceCharge,  
								   @ThueBill = D.TaxRate,  
								   @GiamGiaBill = D.DiscountAmount,  
								   @TangGiaBill = D.IncreaseAmount,  
								   @ThanhTienBill = D.TotalAmount
								   FROM (SELECT SUM(ISNULL(Quantity, 0)) as Quantity, SUM(ISNULL(Rate, 0)) as Rate, SUM(ISNULL(Amount, 0)) as Amount, SUM(ISNULL(ServiceChargeRate, 0)) as ServiceCharge, SUM(ISNULL(TaxRate, 0)) as TaxRate, SUM(ISNULL(DiscountAmount, 0)) as DiscountAmount, SUM(ISNULL(IncreaseAmount, 0)) as IncreaseAmount, SUM(ISNULL(TotalAmount, 0)) as TotalAmount FROM SP5100 WHERE BillId = @BillId  and Deleted = 0) as D

								   -- group theo htt thì không có thành tiền những bill về room, group
								 --  IF(@GroupType = '3' and @HTTT in ('Room','Group'))
									--BEGIN
									-- SET @ThanhTienBill = 0
									--END
								END
							
							

							SET	@SoLuongNgay += @SoLuongBill
							SET	@GiaBanNgay += @GiaBanBill
							SET	@GiaTruocGiamTangNgay += @GiaTruocGiamTangBill
							SET	@PhiPVNgay += @PhiPVBill
							SET	@ThueNgay += @ThueBill
							SET	@GiamGiaNgay += @GiamGiaBill
							SET	@TangGiaNgay += @TangGiaBill
							SET	@ThanhTienNgay += @ThanhTienBill

							SET	@TTTienMatNgay += @TTTienMat
							SET	@TTChuyenKhoanNgay += @TTChuyenKhoan
							SET	@TTNoNgay += @TTNo
							SET	@TTKhacNgay += @TTKhac
							SET @DoanhThuDaThuNgay += @DoanhThuDaThu
							SET @DoanhThuChuaThuNgay += @DoanhThuChuaThu

							UPDATE #RevenueTemp SET SoLuong = @SoLuongBill, GiaBan = @GiaBanBill, GiaTruocGiamTang = @GiaTruocGiamTangBill, PhiPV = @PhiPVBill, Thue = @ThueBill, GiamGia = @GiamGiaBill, TangGia = @TangGiaBill, ThanhTien = @ThanhTienBill, TTTienMat = ISNULL(@TTTienMat,0), TTChuyenKhoan = @TTChuyenKhoan, TTNo = @TTNo, TTKhac = @TTKhac
							where Ngay = @Ngay and [Type] = 'BILL' and BillCode = @BillCode

							FETCH NEXT FROM @BillCursor INTO @HTTT, @BillCode, @BillId, @GioVao, @GioRa,@TTTienMat,@TTChuyenKhoan,@TTNo,@TTKhac, @DoanhThuDaThu, @DoanhThuChuaThu
						END
						CLOSE @BillCursor
						DEALLOCATE @BillCursor

						-- update tien tong bill trong ngay
						SET	@SoLuongDong += @SoLuongNgay
						SET	@GiaBanDong += @GiaBanNgay
						SET	@GiaTruocGiamTangDong += @GiaTruocGiamTangNgay
						SET	@PhiPVDong += @PhiPVNgay
						SET	@ThueDong += @ThueNgay
						SET	@GiamGiaDong += @GiamGiaNgay
						SET	@TangGiaDong += @TangGiaNgay
						SET	@ThanhTienDong += @ThanhTienNgay

						SET @TTTienMatDong += @TTTienMatNgay
						SET @TTChuyenKhoanDong += @TTChuyenKhoanNgay
						SET @TTNoDong += @TTNoNgay
						SET @TTKhacDong += @TTKhacNgay
						SET @DoanhThuDaThuDong += @DoanhThuDaThuNgay
						SET @DoanhThuChuaThuDong += @DoanhThuChuaThuNgay

						UPDATE #RevenueTemp SET SoLuong = @SoLuongNgay, GiaBan = @GiaBanNgay, GiaTruocGiamTang = @GiaTruocGiamTangNgay, PhiPV = @PhiPVNgay, Thue = @ThueNgay, GiamGia = @GiamGiaNgay, TangGia = @TangGiaNgay, ThanhTien = @ThanhTienNgay,TTTienMat = @TTTienMatNgay, TTChuyenKhoan = @TTChuyenKhoanNgay, TTNo = @TTNoNgay, TTKhac = @TTKhacNgay, DoanhThuDaThu = @DoanhThuDaThuNgay, DoanhThuChuaThu = @DoanhThuChuaThuNgay
							where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID
						
						FETCH NEXT FROM @DateCursor INTO @Ngay
					END
					CLOSE @DateCursor
					DEALLOCATE @DateCursor
					
				END

			-- FO
			IF(EXISTS(SELECT value FROM openjson(@DeparmentId) WHERE value = 'FO'))
				BEGIN
					-- vong lap theo ngay
					SET @DateCursor = CURSOR FOR 
					SELECT  TOP (DATEDIFF(DAY, @DateFrom, @DateTo) + 1)
					[Date] = DATEADD(DAY, ROW_NUMBER() OVER(ORDER BY a.object_id) - 1, @DateFrom)
					FROM    sys.all_objects a
					CROSS JOIN sys.all_objects b;

					OPEN @DateCursor
					FETCH NEXT FROM @DateCursor INTO @Ngay
					WHILE @@FETCH_STATUS = 0
					BEGIN
						SET @SoLuongNgay = 0
						SET @GiaBanNgay = 0
						SET @GiaTruocGiamTangNgay = 0
						SET @PhiPVNgay = 0
						SET @ThueNgay = 0
						SET @GiamGiaNgay = 0
						SET @TangGiaNgay = 0
						SET @ThanhTienNgay = 0
						SET @TTTienMatNgay = 0
						SET @TTChuyenKhoanNgay = 0
						SET @TTNoNgay = 0
						SET @TTKhacNgay = 0
						SET @DoanhThuDaThuNgay = 0
						SET @DoanhThuChuaThuNgay = 0

						-- insert into ngay
						IF NOT EXISTS (select * FROM #RevenueTemp where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID)
							BEGIN
								INSERT INTO #RevenueTemp (Ngay, [Type], BillCode, IsBold, IsPrint, Color, CotNgay) VALUES (@Ngay, 'DATE', @LineID, @IsBold, @IsPrint, @Color, CONVERT(varchar(30), @Ngay, 103))
							END
						-- vong lap theo hoa don
							IF(@GroupType != '3')
								BEGIN
									SET @BillCursor = CURSOR FOR
									SELECT  hddv.Ma,
									(select STRING_AGG(PaymentMethod,',') from SP3002  where PaymentID = hddv.PaymentID) as HTTT,
									0,0,0,0,
									(case when hddv.PaymentID is not null then hddv.Amount else 0 end) as DoanhThuDaThu,
									(case when hddv.PaymentID is null then hddv.Amount else 0 end) as DoanhThuChuaThu
									
									FROM SP3000 hddv
									left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID  order by Date desc)
									WHERE 
									hddv.ServiceId in (SELECT value FROM openjson(@ServiceId)) 
									and hddv.DepartmentId in (SELECT value FROM openjson(@DeparmentId)) 
									and hddv.Edit = 0
									and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = hddv.PaymentID),'') not in (select Ma from SP1326  	where	HTMienPhi = 1)
									and hddv.Amount != 0 
									and (tt.Date = @Ngay)
								END
							ELSE
								BEGIN
									SET @BillCursor = CURSOR FOR
									select A.BookingIdd, STRING_AGG(A.PaymentMethod,',') as HTTT, 
									ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (select value from string_split(STRING_AGG(A.PaymentID,','),','))	and PaymentMethod =	'CA' and SP3002.Date = @Ngay),0)   as   	TTTienMat
									,
									ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (select value from string_split(STRING_AGG(A.PaymentID,','),','))	and PaymentMethod =	'BT' and SP3002.Date = @Ngay),0)   as   	TTChuyenKhoan,
									ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (select value from string_split(STRING_AGG(A.PaymentID,','),','))	and PaymentMethod =	'AC' and SP3002.Date = @Ngay),0)   as   	TTNo,
									ISNULL((select SUM(ISNULL(Amount,0)) from SP3002 where PaymentID in (select value from string_split(STRING_AGG(A.PaymentID,','),','))	and PaymentMethod not in ('CA','BT','AC')  and SP3002.Date = @Ngay),0) as TTKhac,
									0,0
									from (
									select distinct (CASE WHEN SP3000.RentalRoomId2 is null THEN SP3000.RegisterID2 else SP2100.BookingId end) as	BookingIdd,   					SP3002.PaymentMethod,			SP3000.PaymentID
									from SP3000
									LEFT JOIN SP2100 on SP3000.RentalRoomId2 = SP2100.Ma
									LEFT JOIN SP3002 on SP3002.PaymentID = SP3000.PaymentID
									where SP3000.Edit = 0
									and SP3000.Amount != 0 
									and (SP3002.Date = @Ngay or sp3000.Date = @Ngay)
									and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = SP3000.PaymentID),'') not in (select Ma from	SP1326			where   			HTMienPhi	= 1)
									) as A 
									where A.BookingIdd is not null
									group by BookingIdd
									order by BookingIdd
								END

						OPEN @BillCursor
						FETCH NEXT FROM @BillCursor INTO @BillCode,@HTTT,@TTTienMat, @TTChuyenKhoan, @TTNo, @TTKhac,@DoanhThuDaThu,@DoanhThuChuaThu
						WHILE @@FETCH_STATUS = 0
						BEGIN
							SET @SoLuongBill = 0
							SET @GiaBanBill = 0
							SET @GiaTruocGiamTangBill = 0
							SET @PhiPVBill = 0
							SET @ThueBill = 0
							SET @GiamGiaBill = 0
							SET @TangGiaBill = 0
							SET @ThanhTienBill = 0
							-- insert into bill
							IF(@GroupType in ('0','1','3'))
								BEGIN
									IF(ISNULL(@TTTienMat,0) + ISNULL(@TTChuyenKhoan,0) + ISNULL(@TTNo,0) + ISNULL(@TTKhac,0) = 0) SET @HTTT = ''

									INSERT INTO #RevenueTemp (Ngay, SoHD, HTTT, [Type], BillCode, IsBold, IsPrint, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien,DoanhThuDaThu,DoanhThuChuaThu) VALUES (@Ngay, @BillCode, @HTTT, 'BILL', @BillCode, @IsBold, @IsPrint,0,0,0,0,0,0,0,@DoanhThuDaThu,@DoanhThuChuaThu)
								END
								
							-- insert into detail
							IF(@GroupType = '0')
								BEGIN
									INSERT INTO #RevenueTemp (Ngay, ProductId, ProductName, SoLuong, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien, [Type], BillCode, IsBold, IsPrint) SELECT @Ngay, hddvct.ServiceId, hddvct.DescriptionServive, ISNULL(hddv.Quantity, 0), ISNULL(hddvct.Amount, 0), ISNULL(hddvct.Amount, 0), ISNULL(hddvct.ServiceChargeAmount, 0), ISNULL(hddvct.TaxAmount, 0), 0, 0, ISNULL(hddvct.Amount, 0), 'DETAIL', @BillCode, 0, 1
										FROM SP3001 hddvct
										LEFT JOIN SP3000 hddv on hddv.Ma = hddvct.BillServiceId
										WHERE hddvct.BillServiceId = @BillCode and hddvct.Amount != 0
								END
							
							------------------------------------
							-- tinh toan tien detail + vao bill
							SELECT @SoLuongBill = ISNULL(D.Quantity,0), 
								   @GiaBanBill =  ISNULL(D.Amount,0),  
								   @GiaTruocGiamTangBill = ISNULL(D.Amount,0),  
								   @PhiPVBill = ISNULL(D.ServiceCharge,0),  
								   @ThueBill = ISNULL(D.TaxRate,0),  
								   @GiamGiaBill = 0,  
								   @TangGiaBill = 0,  
								   @ThanhTienBill =  ISNULL(D.Amount,0)
								   FROM (SELECT SUM(ISNULL(hddv.Quantity, 0)) as Quantity, SUM(ISNULL(hddvct.Amount, 0)) as Amount, SUM(ISNULL(hddvct.TaxAmount, 0)) as TaxRate, SUM(ISNULL(hddvct.ServiceChargeAmount, 0)) as ServiceCharge FROM SP3001 hddvct
										LEFT JOIN SP3000 hddv on hddv.Ma = hddvct.BillServiceId
										LEFT JOIN SP2100 on SP2100.Ma = hddv.RentalRoomId2
										WHERE 
										case when @GroupType != '3' then hddvct.BillServiceId else (CASE WHEN hddv.RentalRoomId2 is null THEN hddv.RegisterID2 else SP2100.BookingId end) end = @BillCode 
										and (@GroupType != '3'  or hddv.Date = @Ngay)
										and hddvct.Amount != 0 ) as D

							SET	@SoLuongNgay += @SoLuongBill
							SET	@GiaBanNgay += @GiaBanBill
							SET	@GiaTruocGiamTangNgay += @GiaTruocGiamTangBill
							SET	@PhiPVNgay += @PhiPVBill
							SET	@ThueNgay += @ThueBill
							SET	@GiamGiaNgay += @GiamGiaBill
							SET	@TangGiaNgay += @TangGiaBill
							SET	@ThanhTienNgay += @ThanhTienBill

							SET	@TTTienMatNgay += @TTTienMat
							SET	@TTChuyenKhoanNgay += @TTChuyenKhoan
							SET	@TTNoNgay += @TTNo
							SET	@TTKhacNgay += @TTKhac
							SET @DoanhThuDaThuNgay += @DoanhThuDaThu
							SET @DoanhThuChuaThuNgay += @DoanhThuChuaThu

							UPDATE #RevenueTemp SET SoLuong = @SoLuongBill, GiaBan = @GiaBanBill, GiaTruocGiamTang = @GiaTruocGiamTangBill, PhiPV = @PhiPVBill, Thue = @ThueBill, GiamGia = @GiamGiaBill, TangGia = @TangGiaBill, ThanhTien = @ThanhTienBill, TTTienMat = @TTTienMat, TTChuyenKhoan = @TTChuyenKhoan, TTNo = @TTNo, TTKhac = @TTKhac
							where Ngay = @Ngay and [Type] = 'BILL' and BillCode = @BillCode
							------------------------------------
							
							FETCH NEXT FROM @BillCursor INTO  @BillCode,@HTTT,@TTTienMat, @TTChuyenKhoan, @TTNo, @TTKhac, @DoanhThuDaThu, @DoanhThuChuaThu
						END
						CLOSE @BillCursor
						DEALLOCATE @BillCursor


						-- lay tat ca dat coc (deposit) trong ngay
						if(@GroupType = '3' and exists (select * from SP3002 where  Date = @Ngay and Edit = 0  and Pack2 = 'DPR' and PaymentID is null))
							BEGIN
								DECLARE @DepositTM float = ISNULL((SELECT SUM(ISNULL(Amount,0)) FROM SP3002 where Date = @Ngay and Edit = 0  and Pack2 = 'DPR' and PaymentID is null and PaymentMethod = 'CA'),0)
								DECLARE @DepositCK float = ISNULL((SELECT SUM(ISNULL(Amount,0)) FROM SP3002 where Date = @Ngay and Edit = 0  and Pack2 = 'DPR' and PaymentID is null and PaymentMethod = 'BT'),0)
								DECLARE @DepositNo float = ISNULL((SELECT SUM(ISNULL(Amount,0)) FROM SP3002 where Date = @Ngay and Edit = 0  and Pack2 = 'DPR' and PaymentID is null and PaymentMethod = 'AC'),0)
								DECLARE @DepositKhac float = ISNULL((SELECT SUM(ISNULL(Amount,0)) FROM SP3002 where Date = @Ngay and Edit = 0  and Pack2 = 'DPR' and PaymentID is null and PaymentMethod not in (select Ma from SP1326 where HTMienPhi = 1) and PaymentMethod not in ('CA','BT','AC')),0)

								INSERT INTO #RevenueTemp (Ngay, SoHD, HTTT, [Type], BillCode, IsBold, IsPrint, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien,TTTienMat,TTChuyenKhoan,TTNo,TTKhac) VALUES (@Ngay, 'Deposit', '', 'BILL', 'Deposit', @IsBold, @IsPrint,0,0,0,0,0,0,
								0, @DepositTM, @DepositCK, @DepositNo, @DepositKhac)

								SET @TTTienMatNgay += @DepositTM
								SET @TTChuyenKhoanNgay += @DepositCK
								SET @TTNoNgay += @DepositNo
								SET @TTKhacNgay += @DepositKhac
							END

						-- update tien tong bill trong ngay
						SET	@SoLuongDong += @SoLuongNgay
						SET	@GiaBanDong += @GiaBanNgay
						SET	@GiaTruocGiamTangDong += @GiaTruocGiamTangNgay
						SET	@PhiPVDong += @PhiPVNgay
						SET	@ThueDong += @ThueNgay
						SET	@GiamGiaDong += @GiamGiaNgay
						SET	@TangGiaDong += @TangGiaNgay
						SET	@ThanhTienDong += @ThanhTienNgay

						SET @TTTienMatDong += @TTTienMatNgay
						SET @TTChuyenKhoanDong += @TTChuyenKhoanNgay
						SET @TTNoDong += @TTNoNgay
						SET @TTKhacDong += @TTKhacNgay
						SET @DoanhThuDaThuDong += @DoanhThuDaThuNgay
						SET @DoanhThuChuaThuDong += @DoanhThuChuaThuNgay

						UPDATE #RevenueTemp SET SoLuong = @SoLuongNgay, GiaBan = @GiaBanNgay, GiaTruocGiamTang = @GiaTruocGiamTangNgay, PhiPV = @PhiPVNgay, Thue = @ThueNgay, GiamGia = @GiamGiaNgay, TangGia = @TangGiaNgay, ThanhTien = @ThanhTienNgay, TTTienMat = @TTTienMatNgay, TTChuyenKhoan = @TTChuyenKhoanNgay, TTNo = @TTNoNgay, TTKhac = @TTKhacNgay, DoanhThuDaThu = @DoanhThuDaThuNgay, DoanhThuChuaThu = @DoanhThuChuaThuNgay
							where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID

						FETCH NEXT FROM @DateCursor INTO @Ngay
					END
					CLOSE @DateCursor
					DEALLOCATE @DateCursor
				END

			-- HK
			IF(EXISTS(SELECT value FROM openjson(@DeparmentId) WHERE value = 'HK') and @GroupType != '3')
				BEGIN
					-- vong lap theo ngay
					SET @DateCursor = CURSOR FOR 
					SELECT CAST(SP3002.Date as Date) as [Date]
					FROM SP3000
					LEFT JOIN SP3002 on SP3000.PaymentID = SP3002.PaymentID
					LEFT JOIN SP6000 on SP3000.Ma = SP6000.BillServiceId
					WHERE SP3000.ServiceId in (SELECT value FROM openjson(@ServiceId))  
					and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = SP3000.PaymentID),'') not in (select Ma from SP1326 where HTMienPhi = 1)
					and (SP6000.FOCType = 0 or SP6000.FOCType is null)
					and SP3002.Date between @DateFrom and @DateTo
					and SP3000.Edit = 0
					GROUP BY SP3002.Date 
					ORDER BY [Date]

					OPEN @DateCursor
					FETCH NEXT FROM @DateCursor INTO @Ngay
					WHILE @@FETCH_STATUS = 0
					BEGIN
						SET @SoLuongNgay = 0
						SET @GiaBanNgay = 0
						SET @GiaTruocGiamTangNgay = 0
						SET @PhiPVNgay = 0
						SET @ThueNgay = 0
						SET @GiamGiaNgay = 0
						SET @TangGiaNgay = 0
						SET @ThanhTienNgay = 0

						SET @DoanhThuDaThuNgay = 0
						SET @DoanhThuChuaThuNgay = 0
						-- insert into ngay
						IF NOT EXISTS (select * FROM #RevenueTemp where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID)
							BEGIN
								INSERT INTO #RevenueTemp (Ngay, [Type], BillCode, IsBold, IsPrint, Color, CotNgay) VALUES (@Ngay, 'DATE', @LineID, @IsBold, @IsPrint, @Color, CONVERT(varchar(30), @Ngay, 103))
							END
						-- vong lap theo hoa don
						SET @BillCursor = CURSOR FOR
						SELECT SP3000.Ma as Ma, 
						(select STRING_AGG(PaymentMethod,',') from SP3002  where PaymentID = SP3000.PaymentID) as HTTT,
						(case when SP3000.PaymentID is not null then SP3000.Amount else 0 end) as DoanhThuDaThu,
						(case when SP3000.PaymentID is null then SP3000.Amount else 0 end) as DoanhThuChuaThu
						FROM SP3000
						left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = SP3000.PaymentID order by Date desc)
						LEFT JOIN SP6000 on SP3000.Ma = SP6000.BillServiceId
						WHERE SP3000.ServiceId in (SELECT value FROM openjson(@ServiceId)) 
						and ISNULL((select top 1 PaymentMethod from SP3002 where PaymentID = SP3000.PaymentID),'') not in (select Ma from SP1326 where HTMienPhi = 1)
						and (SP6000.FOCType = 0 or SP6000.FOCType is null)
						and SP3000.Edit = 0
						and tt.Date = @Ngay

						OPEN @BillCursor
						FETCH NEXT FROM @BillCursor INTO @BillCode,@HTTT,@DoanhThuDaThu,@DoanhThuChuaThu
						WHILE @@FETCH_STATUS = 0
						BEGIN
							SET @SoLuongBill = 0
							SET @GiaBanBill = 0
							SET @GiaTruocGiamTangBill = 0
							SET @PhiPVBill = 0
							SET @ThueBill = 0
							SET @GiamGiaBill = 0
							SET @TangGiaBill = 0
							SET @ThanhTienBill = 0
							-- insert into bill
							IF(@GroupType in ('0','1'))
								INSERT INTO #RevenueTemp (Ngay, SoHD, HTTT, [Type], BillCode, IsBold, IsPrint, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien,DoanhThuDaThu,DoanhThuChuaThu) VALUES (@Ngay, @BillCode, @HTTT, 'BILL', @BillCode, @IsBold, @IsPrint,0,0,0,0,0,0,0,@DoanhThuDaThu,@DoanhThuChuaThu)
							-- insert into detail
							IF(@GroupType = '0')
								BEGIN
									INSERT INTO #RevenueTemp (Ngay, ProductId, ProductName, SoLuong, DVT, GiaBan, GiaTruocGiamTang, PhiPV, Thue, GiamGia, TangGia, ThanhTien, [Type], BillCode, IsBold, IsPrint) SELECT @Ngay, SP6001.MaProduct, SP6001.Product, ISNULL(SP6001.Quantity, 0), SP5401.Name, ISNULL(SP6001.Rate, 0), ISNULL(SP6001.Rate, 0), 0, 0, ISNULL(SP6001.DiscountAmount, 0), ISNULL(SP6001.IncreaseAmount, 0), ISNULL(SP6001.TotalAmount, 0), 'DETAIL', @BillCode, 0, 1
										FROM SP6001
										LEFT JOIN SP6005 on SP6005.Ma = SP6001.MaProduct
										LEFT JOIN SP5401 on SP5401.Id = SP6005.Currency
										WHERE BillId = @BillCode
								END
							
							------------------------------------
							-- tinh toan tien detail + vao bill
							SELECT @SoLuongBill = D.Quantity, 
								   @GiaBanBill =  D.Rate,  
								   @GiaTruocGiamTangBill = D.TotalAmount,  
								   @PhiPVBill = D.ServiceChargeAmount,  
								   @ThueBill = D.TaxAmount,  
								   @GiamGiaBill = D.DiscountAmount,  
								   @TangGiaBill = D.IncreaseAmount,  
								   @ThanhTienBill = D.TotalAmount
								   FROM (select SUM(ISNULL((SP3000.Quantity), 0)) as Quantity, SUM(ISNULL(SP3001.OriginalRate, 0)) as Rate, 0 as DiscountAmount, 0 as IncreaseAmount,SUM(ISNULL( SP3001.ServiceChargeAmount,0)) as ServiceChargeAmount, SUM(ISNULL(SP3001.TaxAmount,0)) as TaxAmount, SUM(ISNULL(SP3001.Amount, 0)) as TotalAmount 
								   from SP3001
								   left join SP3000 on SP3000.Ma = SP3001.BillServiceId
								   where SP3001.BillServiceId = @BillCode
								   ) as D
								   
							SET	@SoLuongNgay += @SoLuongBill
							SET	@GiaBanNgay += @GiaBanBill
							SET	@GiaTruocGiamTangNgay += @GiaTruocGiamTangBill
							SET	@PhiPVNgay += @PhiPVBill
							SET	@ThueNgay += @ThueBill
							SET	@GiamGiaNgay += @GiamGiaBill
							SET	@TangGiaNgay += @TangGiaBill
							SET	@ThanhTienNgay += @ThanhTienBill

							UPDATE #RevenueTemp SET SoLuong = @SoLuongBill, GiaBan = @GiaBanBill, GiaTruocGiamTang = @GiaTruocGiamTangBill, PhiPV = @PhiPVBill, Thue = @ThueBill, GiamGia = @GiamGiaBill, TangGia = @TangGiaBill , ThanhTien = @ThanhTienBill
							where Ngay = @Ngay and [Type] = 'BILL' and BillCode = @BillCode
							------------------------------------
							SET @DoanhThuDaThuNgay += @DoanhThuDaThu
							SET @DoanhThuChuaThuNgay += @DoanhThuChuaThu
							
							FETCH NEXT FROM @BillCursor INTO @BillCode,@HTTT,@DoanhThuDaThu,@DoanhThuChuaThu
						END
						CLOSE @BillCursor
						DEALLOCATE @BillCursor

						-- update tien tong bill trong ngay
						SET	@SoLuongDong += @SoLuongNgay
						SET	@GiaBanDong += @GiaBanNgay
						SET	@GiaTruocGiamTangDong += @GiaTruocGiamTangNgay
						SET	@PhiPVDong += @PhiPVNgay
						SET	@ThueDong += @ThueNgay
						SET	@GiamGiaDong += @GiamGiaNgay
						SET	@TangGiaDong += @TangGiaNgay
						SET	@ThanhTienDong += @ThanhTienNgay

						SET @DoanhThuDaThuDong += @DoanhThuDaThuNgay
						SET @DoanhThuChuaThuDong += @DoanhThuChuaThuNgay

						UPDATE #RevenueTemp SET SoLuong = @SoLuongNgay, GiaBan = @GiaBanNgay, GiaTruocGiamTang = @GiaTruocGiamTangNgay, PhiPV = @PhiPVNgay, Thue = @ThueNgay, GiamGia = @GiamGiaNgay, TangGia = @TangGiaNgay, ThanhTien = @ThanhTienNgay, DoanhThuDaThu = @DoanhThuDaThuNgay, DoanhThuChuaThu  = @DoanhThuChuaThuNgay
							where Ngay = @Ngay and [Type] = 'DATE' and BillCode = @LineID

						FETCH NEXT FROM @DateCursor INTO @Ngay
					END
					CLOSE @DateCursor
					DEALLOCATE @DateCursor
				END

			-- cap nhat lai so tien cac ngay vao dong code
			UPDATE #RevenueTemp SET SoLuong = @SoLuongDong, GiaBan = @GiaBanDong, GiaTruocGiamTang = @GiaTruocGiamTangDong, PhiPV = @PhiPVDong, Thue = @ThueDong, GiamGia = @GiamGiaDong, TangGia = @TangGiaDong, ThanhTien = @ThanhTienDong, TTTienMat = @TTTienMatDong, TTChuyenKhoan = @TTChuyenKhoanDong,TTNo = @TTNoDong,TTKhac = @TTKhacDong,DoanhThuDaThu = @DoanhThuDaThuDong, DoanhThuChuaThu = @DoanhThuChuaThuDong
							where [Type] = 'LINE' and BillCode = @LineID

			FETCH NEXT FROM @SetupCursor INTO @LineID, @LineCode, @LineDescription, @LevelID, @Sign, @AccuLineID, @IsPrint, @IsBold, @DeparmentId, @OutletId, @LocationId, @ServiceId, @Color
		END
		CLOSE @SetupCursor
		DEALLOCATE @SetupCursor
	END


	---vong lap cap nhat so tien theo levelid
	declare @LevelIDCursor int
	SET @LevelCursor = CURSOR FOR 
					   SELECT  LevelID
					   FROM AT7621 
					   WHERE ReportCode = @reportCode
					   group by LevelID order by LevelID desc

						OPEN @LevelCursor
						FETCH NEXT FROM @LevelCursor INTO @LevelIDCursor
						WHILE @@FETCH_STATUS = 0
							BEGIN

							UPDATE #RevenueTemp SET SoLuong = A.SoLuong, 
							GiaBan = A.GiaBan, 
							GiaTruocGiamTang = A.GiaTruocGiamTang,
							PhiPV = A.PhiPV,
							Thue = A.Thue,
							GiamGia = A.GiamGia,
							TangGia = A.TangGia,
							ThanhTien = A.ThanhTien,
							TTTienMat = A.TTTienMat,
							TTChuyenKhoan = A.TTChuyenKhoan,
							TTNo = A.TTNo,
							TTKhac = A.TTKhac,
							DoanhThuDaThu = A.DoanhThuDaThu,
							DoanhThuChuaThu = A.DoanhThuChuaThu
						
												FROM (SELECT SUM(ISNULL(SoLuong,0)) as SoLuong, 
															 SUM(ISNULL(GiaBan,0)) as GiaBan, 
															 SUM(ISNULL(GiaTruocGiamTang,0)) as GiaTruocGiamTang, 
															 SUM(ISNULL(PhiPV,0)) as PhiPV, 
															 SUM(ISNULL(Thue,0)) as Thue, 
															 SUM(ISNULL(GiamGia,0)) as GiamGia, 
															 SUM(ISNULL(TangGia,0)) as TangGia, 
															 SUM(ISNULL(ThanhTien,0)) as ThanhTien, 
															 SUM(ISNULL(TTTienMat,0)) as TTTienMat,
															 SUM(ISNULL(TTChuyenKhoan,0)) as TTChuyenKhoan,
															 SUM(ISNULL(TTNo,0)) as TTNo,
															 SUM(ISNULL(TTKhac,0)) as TTKhac,
															 SUM(ISNULL(DoanhThuDaThu,0)) as DoanhThuDaThu,
															 SUM(ISNULL(DoanhThuChuaThu,0)) as DoanhThuChuaThu,
															 AccuLineID 
																FROM #RevenueTemp 
																WHERE AccuLineID IS NOT NULL 
																	  and AccuLineID != '' 
																	  and LevelID = @LevelIDCursor
																	  GROUP BY AccuLineID  ) as A
																	  WHERE LineID = A.AccuLineID

							FETCH NEXT FROM @LevelCursor INTO @LevelIDCursor
							END
						CLOSE @LevelCursor
						DEALLOCATE @LevelCursor
	

	SELECT DISTINCT * FROM #RevenueTemp ORDER BY STT
											  
END
-- exec "dbo"."sp_TotalRevenueFromReportSetup" @reportCode='DTHTTT',@DateFrom='2023-09-05',@DateTo='2023-09-05',@GroupType='3'
--select * from SP5000 where CreateDateBill = '2023-08-04'
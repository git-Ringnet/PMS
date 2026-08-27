USE [ProVistaGalliot]
GO
/****** Object:  UserDefinedFunction [dbo].[func_052]    Script Date: 8/20/2026 2:35:14 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER FUNCTION [dbo].[func_052](@fromDate Date, @toDate Date, @registrationID varchar(max) = '')
RETURNS @re TABLE (
    [RentalRoomId] varchar(10),
    [BookingId]    bigint,
    [BillIdService] bigint,
    [ServiceId]    varchar(10),
    [RoomRateCode] varchar(30),
    [Date]         date,
    [Quantity]     float,
    [Total]        decimal(18,2),
    [IsRoom]       smallint,
    [DetailId]     int,
    [DepartmentId] varchar(5),
    [VoucherCode]  varchar(50)  DEFAULT NULL,
    [Promotion]    varchar(500) DEFAULT NULL,
    -- CH?T CH?N V?T LÝ: Giúp các câu Join/Update sau này nhanh g?p 100 l?n
    INDEX IX_Re_Main CLUSTERED ([RentalRoomId], [ServiceId], [Date]) 
)
AS
BEGIN
    DECLARE @DateHT  Date = (SELECT TOP 1 SystemDate FROM SP1500);
    DECLARE @fDate1  Date, @tDate1 Date, @fDate2 Date, @tDate2 Date;
    DECLARE @ChildBFService char(2) = 'BD';

    IF EXISTS (SELECT 1 FROM SP1600 WHERE Parameter = 'Booking_BFChildSetServiceId')
    BEGIN
        DECLARE @ServiceConfig varchar(10) = (SELECT Value FROM SP1600 WHERE Parameter = 'Booking_BFChildSetServiceId');
        IF @ServiceConfig != '' AND @ServiceConfig != '0' SET @ChildBFService = @ServiceConfig;
    END

    SET @fDate1 = @fromDate;
    SET @tDate1 = CASE WHEN @toDate < @DateHT THEN @toDate ELSE DATEADD(day,-1,@DateHT) END;
    SET @fDate2 = CASE WHEN @DateHT <= @fromDate THEN @fromDate ELSE @DateHT END;
    SET @tDate2 = @toDate;

    DECLARE @RegIds TABLE (Id varchar(20) INDEX IX_R CLUSTERED);
    IF @registrationID != '' INSERT INTO @RegIds SELECT DISTINCT value FROM string_split(@registrationID, ',');

    -- =========================================================
    -- SECTION 1: Historical (fDate1..tDate1) - Gi? nguyên Set-based g?c
    -- =========================================================
    IF @fDate1 <= @tDate1
    BEGIN
        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DepartmentId],[Quantity],[Promotion])
        SELECT ISNULL(pt.Ma, pt2.Ma), CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END, h.Ma, h.ServiceId, h.Date, TotalAmount0, hdtp.RateCode, ISNULL(dk.WalkIn,1), h.DepartmentId, h.Quantity, ISNULL(ptdvtd.Promotion, ISNULL(pt.Pack3, pt2.Pack3))
        FROM SP3004 hdtp LEFT JOIN SP3000 h ON hdtp.BillId = h.Ma LEFT JOIN SP2100 pt2 ON pt2.Ma = h.RentalRoomId2 LEFT JOIN SP2100 pt ON pt.Ma = h.RentalRoomId1 LEFT JOIN SP2000 dk ON dk.Ma = h.RegisterID2 LEFT JOIN Sp2102 ptdvtd ON ptdvtd.RentalRoomId = ISNULL(pt.Ma, pt2.Ma) AND ptdvtd.FromDate = h.Date AND ptdvtd.ServiceId = 'RM'
        WHERE h.Edit = 0 AND h.Date BETWEEN @fDate1 AND @tDate1 AND (@registrationID = '' OR CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END IN (SELECT Id FROM @RegIds));

        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DepartmentId],[Quantity])
        SELECT DISTINCT CASE WHEN pt.Ma IS NOT NULL THEN pt.Ma ELSE pt2.Ma END, CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END, h.Ma, h.ServiceId, h.Date, TotalAmount0, CASE WHEN pt.Ma IS NOT NULL THEN pt.RoomRateCode ELSE pt2.RoomRateCode END, ISNULL(dk.WalkIn,1), h.DepartmentId, h.Quantity
        FROM SP3000 h LEFT JOIN SP2100 pt2 ON pt2.Ma = h.RentalRoomId2 LEFT JOIN SP2100 pt ON pt.Ma = h.RentalRoomId1 LEFT JOIN SP2000 dk ON dk.Ma = h.RegisterID2 INNER JOIN SP2102 ptdvtd ON ptdvtd.RentalRoomId = CASE WHEN pt.Ma IS NOT NULL THEN pt.Ma ELSE pt2.Ma END AND ptdvtd.FromDate = h.Date AND ptdvtd.ServiceId = h.ServiceId
        WHERE h.Edit = 0 AND h.Date BETWEEN @fDate1 AND @tDate1 AND h.ServiceId <> 'RM' AND (@registrationID = '' OR CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END IN (SELECT Id FROM @RegIds)) AND h.RentalRoomId1 IN (SELECT pt.Ma FROM SP2100 pt WHERE pt.Status IN (2,4,100) AND (pt.Room IS NULL OR pt.Room NOT LIKE '0%%'));

        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DepartmentId],[Quantity])
        SELECT DISTINCT CASE WHEN pt.Ma IS NOT NULL THEN pt.Ma ELSE pt2.Ma END, CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END, h.Ma, h.ServiceId, h.Date, TotalAmount0, CASE WHEN pt.Ma IS NOT NULL THEN pt.RoomRateCode ELSE pt2.RoomRateCode END, ISNULL(dk.WalkIn,1), h.DepartmentId, h.Quantity
        FROM SP3000 h LEFT JOIN SP2100 pt2 ON pt2.Ma = h.RentalRoomId2 LEFT JOIN SP2100 pt ON pt.Ma = h.RentalRoomId1 LEFT JOIN SP2000 dk ON dk.Ma = h.RegisterID2 INNER JOIN SP2102 ptdvtd ON ptdvtd.RentalRoomId = CASE WHEN pt.Ma IS NOT NULL THEN pt.Ma ELSE pt2.Ma END AND ptdvtd.FromDate = h.Date AND ptdvtd.ServiceId = h.ServiceId
        WHERE h.Edit = 0 AND h.Date BETWEEN @fDate1 AND @tDate1 AND h.ServiceId <> 'RM' AND (@registrationID = '' OR CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE dk.Ma END IN (SELECT Id FROM @RegIds)) AND h.RentalRoomId1 IN (SELECT pt.Ma FROM SP2100 pt WHERE pt.Status IN (0,1) AND (pt.Room IS NULL OR pt.Room NOT LIKE '0%%'));

        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DepartmentId],[Quantity])
        SELECT DISTINCT pt.Ma, pt.BookingId, hddv.Ma, ISNULL(hddv.ServiceId, @ChildBFService), bf.Date, bf.Rate, pt.RoomRateCode, ISNULL(bf.PostToRoom,0), 'FO', 1
        FROM SP2401 bf JOIN SP2100 pt ON pt.Ma = bf.RentalRoomId JOIN SP3000 hddv ON hddv.Date = bf.Date AND hddv.Amount = bf.Rate AND hddv.NotPrint = 1 AND hddv.RentalRoomId1 = pt.Ma AND hddv.Edit = 0
        WHERE bf.Breakfast = 1 AND bf.ExtraBreakfast = 1 AND bf.Date BETWEEN @fDate1 AND @tDate1 AND (@registrationID = '' OR pt.BookingId IN (SELECT Id FROM @RegIds));

        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[DepartmentId],[Quantity])
        SELECT CASE WHEN pt.Ma IS NOT NULL THEN pt.Ma ELSE pt2.Ma END, '', h.Ma, h.ServiceId, h.Date, TotalAmount0, CASE WHEN pt.Ma IS NOT NULL THEN pt.RoomRateCode ELSE pt2.RoomRateCode END, h.DepartmentId, h.Quantity
        FROM SP3000 h LEFT JOIN SP2100 pt2 ON pt2.Ma = h.RentalRoomId2 LEFT JOIN SP2100 pt ON pt.Ma = h.RentalRoomId1
        WHERE h.Edit = 0 AND h.Date BETWEEN @fDate1 AND @tDate1 AND h.ServiceId <> 'RM' AND h.RentalRoomId1 IN (SELECT ma FROM SP2100 WHERE Room LIKE '0%') AND (@registrationID = '' OR CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE '' END IN (SELECT Id FROM @RegIds));
    END

   -- =========================================================
    -- SECTION 2: Future/Current (fDate2..tDate2) - ZERO CURSORS (GRAIN: MA)
    -- =========================================================
    IF @fDate2 <= @tDate2
    BEGIN
        DECLARE @LateIn TABLE (RentalRoomId1 varchar(10) PRIMARY KEY, LateCheckInDate date);
        INSERT INTO @LateIn SELECT RentalRoomId1, MIN(SP3000.Date) FROM SP3000 INNER JOIN SP3004 ON SP3004.BillId = SP3000.Ma AND SP3004.IsRoomNight = 1 GROUP BY RentalRoomId1;

        DECLARE @ServiceMax TABLE (RentalRoomId1 varchar(10) PRIMARY KEY, ServiceDate date);
        INSERT INTO @ServiceMax SELECT RentalRoomId1, MAX(SP3000.Date) FROM SP3000 INNER JOIN SP3004 ON SP3004.BillId = SP3000.Ma AND SP3004.IsRoomNight = 1 GROUP BY RentalRoomId1;

        DECLARE @Bookings TABLE (
            Ma varchar(10) PRIMARY KEY CLUSTERED, -- B?t bu?c Ma là h?t duy nh?t
            BookingId bigint, ArrivalDate date, DepartureDate date, Rate decimal(18,2), ActualNumOfDays int, DayUse varchar(10), RoomType smallint, RoomKind smallint, Pack2 varchar(20), Pack3 nvarchar(500), RoomRateCode varchar(20), VoucherInfo varchar(max), ExtraBed int, ExtraBedRate money, Child int, ActualArrivalDate date
        );

        INSERT INTO @Bookings
        SELECT pt.Ma, pt.BookingId,
            ISNULL(li.LateCheckInDate, pt.ArrivalDate),
            ISNULL(sm.ServiceDate, DATEADD(day, CASE WHEN pt.ActualNumOfDays=0 THEN 0 ELSE pt.ActualNumOfDays-1 END, pt.ArrivalDate)),
            pt.Rate, pt.ActualNumOfDays, pt.DayUse, pt.RoomType, pt.RoomKind, pt.Pack2, pt.Pack3, pt.RoomRateCode, dk.VoucherInfo, pt.ExtraBed, pt.ExtraBedRate, pt.Child, pt.ArrivalDate
        FROM SP2100 pt LEFT JOIN SP2000 dk ON dk.Ma = pt.BookingId LEFT JOIN @LateIn li ON pt.Ma = li.RentalRoomId1 AND li.LateCheckInDate < pt.ArrivalDate LEFT JOIN @ServiceMax sm ON pt.Ma = sm.RentalRoomId1 AND sm.ServiceDate > DATEADD(day, CASE WHEN pt.ActualNumOfDays=0 THEN 0 ELSE pt.ActualNumOfDays-1 END, ISNULL(li.LateCheckInDate, pt.ArrivalDate))
        WHERE pt.Status IN (0,1,2,4,100) AND (pt.Room IS NULL OR pt.Room NOT LIKE '0%%') AND (@registrationID = '' OR pt.BookingId IN (SELECT Id FROM @RegIds))
          AND ISNULL(li.LateCheckInDate, pt.ArrivalDate) <= @tDate2 AND ISNULL(sm.ServiceDate, DATEADD(day, CASE WHEN pt.ActualNumOfDays=0 THEN 0 ELSE pt.ActualNumOfDays-1 END, pt.ArrivalDate)) >= @fDate1
          AND (DATEDIFF(day, ISNULL(li.LateCheckInDate, pt.ArrivalDate), ISNULL(sm.ServiceDate, DATEADD(day, pt.ActualNumOfDays, pt.ArrivalDate))) != 0 OR EXISTS (SELECT 1 FROM SP3000 h2 INNER JOIN SP3004 hd2 ON hd2.BillId = h2.Ma AND hd2.IsRoomNight = 1 WHERE h2.RentalRoomId1 = pt.Ma AND h2.Date = pt.ArrivalDate) OR pt.DayUse = '1');

        -- Virtual-room RM services
        INSERT INTO @re ([RentalRoomId],[BookingId],[BillIdService],[ServiceId],[Date],[Total],[RoomRateCode],[DepartmentId],[Quantity])
        SELECT CASE WHEN pt2.Ma IS NOT NULL THEN pt2.Ma ELSE pt.Ma END, '', h.Ma, h.ServiceId, h.Date, TotalAmount0, CASE WHEN pt2.Ma IS NOT NULL THEN pt2.RoomRateCode ELSE pt.RoomRateCode END, h.DepartmentId, h.Quantity
        FROM SP3000 h LEFT JOIN SP2100 pt2 ON pt2.Ma = h.RentalRoomId2 LEFT JOIN SP2100 pt ON pt.Ma = h.RentalRoomId1
        WHERE h.Edit = 0 AND h.Date BETWEEN @fDate2 AND @tDate2 AND h.ServiceId = 'RM' AND (@registrationID = '' OR CASE WHEN pt.Ma IS NOT NULL THEN pt.BookingId ELSE '' END IN (SELECT Id FROM @RegIds)) AND h.RentalRoomId1 IN (SELECT ma FROM SP2100 WHERE Room LIKE '0%');

        -- 3 B?NG CACHE KÈM QUANTITY C?A 2102
        DECLARE @Cache76 TABLE (Ma varchar(10), [Date] date, Rate decimal(18,2), RoomRateCode varchar(20), INDEX IX CLUSTERED (Ma, [Date]));
        INSERT INTO @Cache76 SELECT b.Ma, f.[Date], f.Rate, f.RoomRateCode FROM @Bookings b CROSS APPLY dbo.func_076(b.Ma, b.ArrivalDate, b.DepartureDate, b.RoomRateCode, b.RoomType, b.RoomKind) f WHERE f.[Date] BETWEEN @fDate2 AND @tDate2;

        DECLARE @Cache77 TABLE (Ma varchar(10), [Date] date, Rate decimal(18,2), PackageCode varchar(20), ServiceId varchar(10), DepartmentId varchar(5), Quantity float, INDEX IX CLUSTERED (Ma, [Date]));
        INSERT INTO @Cache77 SELECT b.Ma, f.[Date], f.Rate, f.PackageCode, f.ServiceId, f.DepartmentId, f.Quantity FROM @Bookings b CROSS APPLY dbo.func_077(b.Ma, b.ArrivalDate, b.DepartureDate, b.Pack2, b.RoomType, b.RoomKind) f WHERE f.[Date] BETWEEN @fDate2 AND @tDate2;

        DECLARE @Cache2102 TABLE (RentalRoomId varchar(10), ServiceId varchar(10), FromDate date, ToDate date, Total decimal(18,2), RoomRateCode varchar(30), IsRoom smallint, Ma int, Promotion varchar(500), Quantity float, INDEX IX CLUSTERED (RentalRoomId, ServiceId, FromDate, ToDate));
        INSERT INTO @Cache2102 SELECT RentalRoomId, ServiceId, FromDate, ToDate, Total, RoomRateCode, IsRoom, Ma, Promotion, Quantity FROM SP2102 WHERE RentalRoomId IN (SELECT Ma FROM @Bookings) AND ToDate >= @fDate2 AND FromDate <= @tDate2;

        -- MASTER GRID
        DECLARE @MasterGrid TABLE (Ma varchar(10), BookingId bigint, [Date] date, RoomRateCode varchar(20), ExtraBed int, ExtraBedRate money, Child int, ActualArrivalDate date, ActualNumOfDays int, DayUse varchar(10), Total decimal(18,2), Pack3 nvarchar(500), INDEX IX CLUSTERED (Ma, [Date]));
        
        ;WITH Tally(n) AS (SELECT TOP (DATEDIFF(DAY, @fDate1, @tDate2) + 1) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) FROM sys.all_objects)
        INSERT INTO @MasterGrid
        SELECT b.Ma, b.BookingId, DATEADD(DAY, t.n-1, b.ArrivalDate), b.RoomRateCode, b.ExtraBed, b.ExtraBedRate, b.Child, b.ActualArrivalDate, b.ActualNumOfDays, b.DayUse, b.Rate, b.Pack3
        FROM @Bookings b JOIN Tally t ON DATEADD(DAY, t.n-1, b.ArrivalDate) <= b.DepartureDate WHERE DATEADD(DAY, t.n-1, b.ArrivalDate) BETWEEN @fDate2 AND @tDate2;

        -- =====================================================================
        -- S?A CH?A L?N: BÓC VOUCHER VÀ PACK3 L?Y "MÃ PHÒNG" (Ma) LÀM G?C B?N L?
        -- =====================================================================
        DECLARE @ParsedVouchers TABLE (Ma varchar(10), BookingId bigint, ServiceId varchar(10), VoucherCode varchar(50), ValueType varchar(15), VoucherAmount float, TargetDate date, INDEX IX CLUSTERED (Ma, ServiceId, TargetDate));
        INSERT INTO @ParsedVouchers
        SELECT b.Ma, b.BookingId, ISNULL(j.ServiceId, 'RM'), j.VoucherCode, CASE WHEN j.DiscountType=1 THEN 'percent' ELSE 'amount' END, j.VoucherAmount, CAST(SUBSTRING(d.Value, LEN(d.Value) - CHARINDEX('|', REVERSE(d.Value)) + 2, LEN(d.Value)) AS date)
        FROM @Bookings b CROSS APPLY OPENJSON(b.VoucherInfo) WITH (VoucherCode varchar(50) '$.VoucherCode', DiscountType int '$.DiscountType', VoucherAmount float '$.VoucherAmount', ServiceId varchar(10) '$.ServiceId', ListDays nvarchar(max) '$.ListDays' AS JSON) j CROSS APPLY OPENJSON(j.ListDays) WITH (Value varchar(50) '$.Value', Amount decimal '$.Amount') d
        WHERE b.VoucherInfo IS NOT NULL AND SUBSTRING(d.Value, 0, CHARINDEX('|', d.Value, 0)) = b.Ma;

        -- Khóa chính bây gi? là Mã Phòng thuê (Ma)
        DECLARE @ParsedPack3 TABLE (Ma varchar(10) PRIMARY KEY CLUSTERED, BookingId bigint, ValueType varchar(10), [Type] varchar(10), PromotionValue float);
        INSERT INTO @ParsedPack3
        SELECT b.Ma, b.BookingId, ISNULL(JSON_VALUE(b.Pack3, '$.ValueType'), 'percent'), ISNULL(JSON_VALUE(b.Pack3, '$.Type'), 'surcharge'), ISNULL(CAST(JSON_VALUE(b.Pack3, '$.PromotionValue') AS float), 0.0)
        FROM @Bookings b WHERE b.Pack3 IS NOT NULL;

        -- THE BIG RM INSERT (Join qua p3.Ma và v.Ma)
        INSERT INTO @re ([RentalRoomId],[BookingId],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DetailId],[DepartmentId],[Quantity],[Promotion],[VoucherCode])
        SELECT 
            mg.Ma, mg.BookingId, ISNULL(CASE WHEN ISNULL(c77.DepartmentId,'') IN ('FB','HK') THEN (SELECT TOP 1 Services FROM SP5409 WHERE OutletId=c77.ServiceId) ELSE c77.ServiceId END, 'RM'), mg.[Date],
            Calc.FinalRate, ISNULL(c2102.RoomRateCode, ISNULL(c76.RoomRateCode, c77.PackageCode)), ISNULL(c2102.IsRoom, 1), c2102.Ma, ISNULL(c77.DepartmentId, 'FO'), ISNULL(c77.Quantity, 1), ISNULL(c2102.Promotion, mg.Pack3), v.VoucherCode
        FROM @MasterGrid mg
        LEFT JOIN @Cache76 c76 ON c76.Ma = mg.Ma AND c76.[Date] = mg.[Date]
        LEFT JOIN @Cache77 c77 ON c77.Ma = mg.Ma AND c77.[Date] = mg.[Date]
        LEFT JOIN @Cache2102 c2102 ON c2102.RentalRoomId = mg.Ma AND c2102.ServiceId = 'RM' AND mg.[Date] BETWEEN c2102.FromDate AND c2102.ToDate
        LEFT JOIN @ParsedPack3 p3 ON p3.Ma = mg.Ma -- <--- S?A JOIN THEO PHÒNG
        LEFT JOIN @ParsedVouchers v ON v.Ma = mg.Ma AND v.ServiceId = 'RM' AND v.TargetDate = mg.[Date] -- <--- S?A JOIN THEO PHÒNG
        CROSS APPLY (SELECT RawRate = CASE WHEN mg.ActualNumOfDays=0 AND ISNULL(mg.DayUse,'0')='0' THEN ISNULL(mg.Total,0) ELSE CASE WHEN c2102.Ma IS NOT NULL THEN ISNULL(c2102.Total,0) ELSE ISNULL(c77.Rate, ISNULL(c76.Rate, mg.Total)) END END) R1
        CROSS APPLY (
            SELECT PromoRate = CASE 
                WHEN c2102.Promotion IS NOT NULL THEN 
                    CASE WHEN JSON_VALUE(c2102.Promotion,'$.ValueType')='percent' THEN R1.RawRate * (1.0 + (CASE WHEN JSON_VALUE(c2102.Promotion,'$.Type')='discount' THEN -1.0 ELSE 1.0 END) * (CASE WHEN CAST(JSON_VALUE(c2102.Promotion,'$.PromotionValue') AS float) BETWEEN 0 AND 100 THEN CAST(JSON_VALUE(c2102.Promotion,'$.PromotionValue') AS float) ELSE 0.0 END)/100.0)
                         ELSE CASE WHEN JSON_VALUE(c2102.Promotion,'$.Type')='discount' AND R1.RawRate < CAST(JSON_VALUE(c2102.Promotion,'$.PromotionValue') AS float) THEN 0.0 ELSE R1.RawRate + (CASE WHEN JSON_VALUE(c2102.Promotion,'$.Type')='discount' THEN -1.0 ELSE 1.0 END) * CAST(JSON_VALUE(c2102.Promotion,'$.PromotionValue') AS float) END END
                ELSE 
                    CASE WHEN ISNULL(p3.ValueType, 'percent')='percent' THEN R1.RawRate * (1.0 + (CASE WHEN ISNULL(p3.[Type],'surcharge')='discount' THEN -1.0 ELSE 1.0 END) * (CASE WHEN ISNULL(p3.PromotionValue,0) BETWEEN 0 AND 100 THEN ISNULL(p3.PromotionValue,0) ELSE 0.0 END)/100.0)
                         ELSE CASE WHEN (CASE WHEN ISNULL(p3.[Type],'surcharge')='discount' THEN -1.0 ELSE 1.0 END)=-1.0 AND R1.RawRate < ISNULL(p3.PromotionValue,0) THEN 0.0 ELSE R1.RawRate + (CASE WHEN ISNULL(p3.[Type],'surcharge')='discount' THEN -1.0 ELSE 1.0 END) * ISNULL(p3.PromotionValue,0) END END 
            END
        ) R2
        CROSS APPLY (
            SELECT FinalRate = CASE 
                WHEN v.VoucherCode IS NOT NULL THEN
                    CASE WHEN v.ValueType='amount' THEN CASE WHEN R2.PromoRate >= v.VoucherAmount THEN R2.PromoRate - v.VoucherAmount ELSE 0 END
                         ELSE R2.PromoRate * (1.0 - (CASE WHEN v.VoucherAmount BETWEEN 0 AND 100 THEN v.VoucherAmount ELSE 0 END)/100.0) END
                ELSE R2.PromoRate END
        ) Calc;

        -- Extra Services
        INSERT INTO @re ([RentalRoomId],[BookingId],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DetailId],[DepartmentId],[Quantity])
        SELECT mg.Ma, mg.BookingId, c.ServiceId, mg.[Date], SUM(c.Total), MIN(mg.RoomRateCode), MIN(c.IsRoom), MIN(c.Ma), 'FO', SUM(c.Quantity)
        FROM @MasterGrid mg JOIN @Cache2102 c ON mg.Ma = c.RentalRoomId AND mg.[Date] BETWEEN c.FromDate AND c.ToDate WHERE c.ServiceId NOT IN ('RM','EB') GROUP BY mg.Ma, mg.BookingId, c.ServiceId, mg.[Date];

        INSERT INTO @re ([RentalRoomId],[BookingId],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DetailId],[DepartmentId],[Quantity])
        SELECT mg.Ma, mg.BookingId, 'EB', mg.[Date], ISNULL(c.Total, mg.ExtraBed * mg.ExtraBedRate), mg.RoomRateCode, ISNULL(c.IsRoom,0), c.Ma, 'FO', ISNULL(c.Quantity, mg.ExtraBed)
        FROM @MasterGrid mg LEFT JOIN @Cache2102 c ON c.RentalRoomId = mg.Ma AND c.ServiceId = 'EB' AND mg.[Date] = c.FromDate WHERE (mg.ExtraBed > 0 OR c.Ma IS NOT NULL);

        INSERT INTO @re ([RentalRoomId],[BookingId],[ServiceId],[Date],[Total],[RoomRateCode],[IsRoom],[DepartmentId],[Quantity])
        SELECT mg.Ma, mg.BookingId, @ChildBFService, mg.[Date], ct.Rate, mg.RoomRateCode, ISNULL(ct.PostToRoom,0), 'FO', 1
        FROM @MasterGrid mg JOIN SP2500 ch ON ch.RentalRoomId = mg.Ma AND ch.Status IN (0,1) JOIN SP2401 ct ON ct.RentalRoomId = mg.Ma AND ct.ChildID = ch.ChildID AND ct.[Date] = mg.[Date] WHERE mg.Child > 0 AND ct.Breakfast = 1 AND ct.ExtraBreakfast = 1;

        INSERT INTO @re ([RentalRoomId],[BookingId],[ServiceId],[Date],[Total],[RoomRateCode],[DepartmentId],[Quantity])
        SELECT mg.Ma, mg.BookingId, pd.ServiceCode, mg.[Date], pd.Total, mg.RoomRateCode, 'FO', pd.Quantity
        FROM @MasterGrid mg JOIN (SELECT mgp.PackageCode, mgp.ServiceCode, mgp.OrderDay, ISNULL(mgp.Total,0) AS Total, pkg.BeginDate, pkg.EndDate, mgp.Quantity FROM SP1317 mgp LEFT JOIN SP1323 pkg ON mgp.PackageCode=pkg.Ma WHERE mgp.Status<>3 AND mgp.ServiceCode<>'RM') pd ON mg.RoomRateCode = pd.PackageCode AND DATEDIFF(Day, mg.ActualArrivalDate, mg.[Date]) = pd.OrderDay - 1 WHERE mg.[Date] BETWEEN pd.BeginDate AND pd.EndDate;

        -- Update Non-RM Vouchers (Join kh?p chính xác qua RentalRoomId = v.Ma)
        UPDATE r SET 
            Total = CASE WHEN v.ValueType='amount' THEN CASE WHEN r.Total >= v.VoucherAmount THEN r.Total - v.VoucherAmount ELSE 0 END ELSE r.Total * (1.0 - v.VoucherAmount/100.0) END,
            VoucherCode = v.VoucherCode
        FROM @re r JOIN @ParsedVouchers v ON r.RentalRoomId = v.Ma AND r.ServiceId = v.ServiceId AND r.[Date] = v.TargetDate
        WHERE r.BillIdService IS NULL AND r.ServiceId <> 'RM';
    END

    RETURN;
END
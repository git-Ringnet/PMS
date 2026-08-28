USE [ProVistaDTXHotel]
GO

/****** Object:  UserDefinedFunction [dbo].[func_052]    Script Date: 8/27/2026 4:38:04 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE   function [dbo].[func_052](@fromDate Date, @toDate Date, @registrationID varchar(max) = '')
returns @re table(
		[RentalRoomId] varchar(10),
		[BookingId] bigint,
		[BillIdService] bigint,
		[ServiceId] varchar(10),
		[RoomRateCode] varchar(30),
		[Date] date,
		[Quantity] float,
		[Total] decimal(18,2),
		[IsRoom] smallint,
		[DetailId] int, 
		[DepartmentId] varchar(5),
		[VoucherCode] varchar(50) default null,
		[Promotion] varchar(500) default null
	)

as
begin
	declare @DateHT Date = (select top 1 SystemDate from SP1500)
	declare @fDate1 Date, @tDate1 Date, @fDate2 Date, @tDate2 Date;

	declare @ChildBFService char(2) = 'BD'

	if exists (select Value from SP1600 where Parameter = 'Booking_BFChildSetServiceId')
	begin		
		declare @ServiceConfig varchar(10) = (select Value from SP1600 where Parameter = 'Booking_BFChildSetServiceId')
		if(@ServiceConfig != '' and @ServiceConfig != '0')
			set @ChildBFService = @ServiceConfig
	end
	
	set @fDate1 = @fromDate;
	if(@toDate < @DateHT)
		set @tDate1 = @toDate;
	else
		set @tDate1 = dateadd(day,-1,@DateHT);
	if(@DateHT <= @fromDate)
		set @fDate2 = @fromDate;
	else
		set @fDate2 = @DateHT;
	set @tDate2 = @toDate;

	declare @TempVoucherRM table (VoucherValue varchar(30), DiscountAmount decimal)
	declare @TempVoucherService table (VoucherValue varchar(30), DiscountAmount decimal)
	

	declare @Ma varchar(10), 
		@BookingId bigint, 
		@ArrivalDate date, 
		@DepartureDate date,
		@ServiceId varchar(10), 
		@RoomRateCode varchar(20),  
		@Total decimal(18,2),
		@ActualNumOfDays int,
		@DayUse varchar(10),
		@IsRoom smallint,
		@ExtraBed int,
		@ExtraBedRate money,
		@Child int,
		@ChildID varchar(10),
		@DepartmentId varchar(5),
		@ActualArrivalDate date,
		@RoomType smallint,
		@RoomKind smallint,
		@Pack2 varchar(20),
		@Pack3 nvarchar(500),
		@Type varchar(15),
		@ValueType varchar(15),
		@PromotionValue float,
		@PlusOrSub decimal,
		@Service char(2),
		@VoucherAmount float,
		@VoucherCode varchar(50),
		@VoucherInfo varchar(max),
		@ListDays nvarchar(max),
		@VoucherValue varchar(30),
		@DiscountAmount decimal,
		@id int;


	if(@fDate1 <= @tDate1)
	begin
		insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DepartmentId], [Quantity], [Promotion])
		select T.RentalRoomId, T.BookingId, T.[BillIdService], T.ServiceId,T.Date, T.Tien, T.[RoomRateCode], T.IsRoom, T.DepartmentId, T.Quantity, T.Promotion
		from (select 
		isnull(pt.Ma, pt2.Ma) as RentalRoomId,
		case when pt.Ma is not null then pt.BookingId else dk.Ma end as BookingId,
		h.Ma as [BillIdService], h.ServiceId,h.Date, TotalAmount0 Tien,
		hdtp.RateCode as RoomRateCode,
		isnull(dk.WalkIn, 1) as IsRoom,
		h.DepartmentId, hdtp.IsRoomNight, h.Quantity, isnull(ptdvtd.Promotion, isnull(pt.Pack3, pt2.Pack3)) Promotion
		from SP3004 hdtp
		left join SP3000 h on hdtp.BillId = h.Ma
		left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
		left join SP2100 as pt on pt.Ma = h.RentalRoomId1
		left join SP2000 as dk on dk.Ma =  h.RegisterID2 
		left join Sp2102 as ptdvtd on ptdvtd.RentalRoomId = isnull(pt.Ma, pt2.Ma) and ptdvtd.FromDate = h.Date and ptdvtd.ServiceId = 'RM'
		where h.Edit=0 and h.Date between @fDate1 and @tDate1) as T
		where (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,',')))

		--insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DepartmentId], [Quantity])
		--select T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId,T.Date, T.Tien, T.[RoomRateCode], T.IsRoom, T.DepartmentId, T.Quantity
		--from (select 
		--case when pt.Ma is not null then pt.Ma else pt2.Ma end as RentalRoomId,
		--pt.BookingId BookingId,
		--h.RentalRoomId as RentalRoomId1, h.Ma as [BillIdService], h.ServiceId,h.FromDate as Date, Total Tien,
		--case when pt.Ma is not null then pt.RoomRateCode else pt2.RoomRateCode end as RoomRateCode,
		--IsRoom,
		--'FO' as DepartmentId, h.Quantity
		--from SP2102 h 
		--left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId
		--left join SP2100 as pt on pt.Ma = h.RentalRoomId
		--where h.FromDate between  @fDate1 and @tDate1 and h.ServiceId <> 'RM' ) as T
		--where (@registrationID = '' or T.BookingId = @registrationID) and T.RentalRoomId1 in (select pt.Ma
		--from SP2100 as pt
		--where pt.Status in (0,1,2,4,100) and (pt.Room is null or pt.Room not like '0%%'))

		
		insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DepartmentId], [Quantity])
		select distinct T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId,T.Date, T.Tien, T.[RoomRateCode], T.IsRoom, T.DepartmentId, T.Quantity
		from (select 
		case when pt.Ma is not null then pt.Ma else pt2.Ma end as RentalRoomId,
		case when pt.Ma is not null then pt.BookingId else dk.Ma end as BookingId,
		h.RentalRoomId1, h.Ma as [BillIdService], h.ServiceId,h.Date, TotalAmount0 Tien,
		case when pt.Ma is not null then pt.RoomRateCode else pt2.RoomRateCode end as RoomRateCode,
		isnull(dk.WalkIn, 1) as IsRoom,
		h.DepartmentId, h.Quantity
		from SP3000 h 
		left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
		left join SP2100 as pt on pt.Ma = h.RentalRoomId1
		left join SP2000 as dk on dk.Ma =  h.RegisterID2 
		where h.Edit=0 and h.Date between  @fDate1 and @tDate1 and h.ServiceId <> 'RM') as T	
		inner join SP2102 ptdvtd on ptdvtd.RentalRoomId = T.RentalRoomId and ptdvtd.FromDate = T.Date and ptdvtd.ServiceId = T.ServiceId
		where (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,','))) and T.RentalRoomId1 in (select pt.Ma
		from SP2100 as pt
		where pt.Status in (2,4,100) and (pt.Room is null or pt.Room not like '0%%'))

		
		insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DepartmentId], [Quantity])
		select distinct T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId, T.Date, T.Tien, T.[RoomRateCode], T.IsRoom, T.DepartmentId, T.Quantity
		from (select 
		case when pt.Ma is not null then pt.Ma else pt2.Ma end as RentalRoomId,
		case when pt.Ma is not null then pt.BookingId else dk.Ma end as BookingId,
		h.RentalRoomId1, h.Ma as [BillIdService], h.ServiceId,h.Date,TotalAmount0 Tien,
		case when pt.Ma is not null then pt.RoomRateCode else pt2.RoomRateCode end as RoomRateCode,
		isnull(dk.WalkIn, 1) as IsRoom,
		h.DepartmentId, h.Quantity
		from SP3000 h 
		left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
		left join SP2100 as pt on pt.Ma = h.RentalRoomId1
		left join SP2000 as dk on dk.Ma =  h.RegisterID2 
		where h.Edit=0 and h.Date between @fDate1 and @tDate1 and h.ServiceId <> 'RM') as T
		left join SP2100 as pt on pt.Ma = T.RentalRoomId
		inner join SP2102 ptdvtd on ptdvtd.RentalRoomId = T.RentalRoomId and ptdvtd.FromDate = T.Date and ptdvtd.ServiceId = T.ServiceId
		where (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,','))) and T.RentalRoomId1 in (select pt.Ma
		from SP2100 as pt
		where pt.Status in (0,1) and (pt.Room is null or pt.Room not like '0%%'))

		-- breakfastchild
        Insert Into @re ([RentalRoomId], [BookingId],[BillIdService], [ServiceId], [Date], [Total],[RoomRateCode],[IsRoom], [DepartmentId], [Quantity])
		SELECT pt.Ma, pt.BookingId, hddv.Ma, isnull(hddv.ServiceId, @ChildBFService), bf.Date, bf.Rate, pt.RoomRateCode, isnull(bf.PostToRoom, 0), 'FO' DepartmentId, 1 Quantity
		from SP2401 bf
		left join SP2100 pt on pt.Ma = bf.RentalRoomId
		inner join SP3000 hddv on hddv.Date = bf.Date and hddv.Amount = bf.Rate and hddv.NotPrint = 1 and RentalRoomId1 = pt.Ma and edit = 0
		where bf.Breakfast = 1 and bf.ExtraBreakfast = 1 and bf.Date between @fDate1 and @tDate1
		and (@registrationID = '' or pt.BookingId in (select value from string_split(@registrationID,',')))

		
		insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [DepartmentId], [Quantity])
		select T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId, T.Date, T.Tien, T.[RoomRateCode], T.DepartmentId, T.Quantity
		from (select 
		case when pt.Ma is not null then pt.Ma else pt2.Ma end as RentalRoomId,'' as  BookingId,
		h.RentalRoomId1, h.Ma as [BillIdService], h.ServiceId, h.Date, TotalAmount0 Tien,
		case when pt.Ma is not null then pt.RoomRateCode else pt2.RoomRateCode end as RoomRateCode,
		h.DepartmentId, h.Quantity
		from SP3000 h 
		left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
		left join SP2100 as pt on pt.Ma = h.RentalRoomId1
		where h.Edit=0 and h.Date between  @fDate1 and @tDate1 and h.ServiceId <> 'RM') as T
		where T.RentalRoomId1 in (select ma from SP2100 where Room like '0%')
		and (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,',')))
	end
	
	--Feature
	if(@fDate2 <= @tDate2)
	begin
		
		DECLARE db_cursor CURSOR 
		FOR 
			
			select pt.Ma, pt.BookingId, 
			case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end ArrivalDate, 
			case when Service.Date is null then dateadd(day, (case when ActualNumOfDays = 0 then ActualNumOfDays else (ActualNumOfDays-1) end),  case when (latein.LateCheckInDate is not null and pt.ArrivalDate = pt.CheckoutDate) then latein.LateCheckInDate else pt.ArrivalDate end) else Service.Date end as DepartureDate,
			'RM' as ServiceId, pt.RoomRateCode,
			--(case when pt.RoomRateCode is not null then (case when mgp.IsPackage = 0 then pt.Rate else 0 end) else pt.Rate end) as Total,
			pt.Rate as Total,
			pt.ActualNumOfDays, pt.DayUse, pt.RoomType, pt.RoomKind, pt.Pack2, pt.Pack3, dk.VoucherInfo
			from SP2100 as pt
			left join SP2000 dk on dk.Ma = pt.BookingId
			left join (select RentalRoomId1, Min(SP3000.Date) LateCheckInDate from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 group by RentalRoomId1) latein on pt.Ma = latein.RentalRoomId1 and latein.LateCheckInDate < pt.ArrivalDate
			left join (select RentalRoomId1, Max(SP3000.Date) Date from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 group by RentalRoomId1) Service on pt.Ma = Service.RentalRoomId1 and Service.Date > dateadd(day, (case when ActualNumOfDays = 0 then ActualNumOfDays else (ActualNumOfDays-1) end), case when (latein.LateCheckInDate is not null and pt.ArrivalDate = pt.CheckoutDate) then latein.LateCheckInDate else pt.ArrivalDate end)
			left join SP1323 as mgp on pt.RoomRateCode = mgp.Ma
			where pt.Status in (0,1,2,4,100) 
			and (pt.Room is null or pt.Room not like '0%%')
			and (
				(@fDate1 between (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end)
				or
				(@tDate2 between (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end)
				or
				((case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) >= @fDate1 and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end <= @tDate2)
			) 
			and (DATEDIFF(day, (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end), case when Service.Date is null then dateadd(day, pt.ActualNumOfDays, pt.ArrivalDate) else Service.Date end) != 0 or 
			(select Count(*) from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 and RentalRoomId1 = pt.Ma and SP3000.Date = pt.ArrivalDate) > 0 or pt.DayUse = '1')
		and (@registrationID = '' or pt.BookingId in (select value from string_split(@registrationID,',')))
			
			insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [DepartmentId], [Quantity], [Promotion])
			select T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId,T.Date, T.Tien, T.[RoomRateCode], T.DepartmentId, T.Quantity, T.Promotion
			from (select 
			case when pt2.Ma is not null then pt2.Ma else pt.Ma end as RentalRoomId,
			'' as  BookingId,
			h.RentalRoomId1, h.Ma as [BillIdService], h.ServiceId,h.Date,TotalAmount0 Tien,
			case when pt2.Ma is not null then pt2.RoomRateCode else pt.RoomRateCode end as RoomRateCode,
			h.DepartmentId, h.Quantity, isnull(pt.Pack3, pt2.Pack3) Promotion
			from SP3000 h 
			left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
			left join SP2100 as pt on pt.Ma = h.RentalRoomId1
			where h.Edit=0 and h.Date between  @fDate2 and @tDate2 and h.ServiceId = 'RM') as T
		where (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,','))) and T.RentalRoomId1 in (select ma from SP2100 where Room like '0%')
		
		set @id = 1
		OPEN db_cursor;
		FETCH NEXT FROM db_cursor INTO @Ma, @BookingId, @ArrivalDate, @DepartureDate, @ServiceId, @RoomRateCode, @Total,@ActualNumOfDays, @DayUse, @RoomType, @RoomKind, @Pack2, @Pack3, @VoucherInfo;
		WHILE @@FETCH_STATUS = 0  
		BEGIN  
			
			if(@ArrivalDate <= @DepartureDate)
			begin
				select top 1 @Service = isnull(Service, '') from openjson(@VoucherInfo) with (VoucherCode varchar(50) '$.VoucherCode', DiscountType int '$.DiscountType', VoucherAmount float '$.VoucherAmount', Service char(2) '$.ServiceId') where Service = 'RM'

				if(isnull(@Service, '') = 'RM')
					select top 1 @ValueType = case when DiscountType = 1 then 'percent' else 'amount' end, @Type = 'discount', @PromotionValue = 0, @VoucherAmount = VoucherAmount, @VoucherCode = VoucherCode, @ListDays = ListDays from openjson(@VoucherInfo) with (VoucherCode varchar(50) '$.VoucherCode', DiscountType int '$.DiscountType', VoucherAmount float '$.VoucherAmount', Service char(2) '$.ServiceId', ListDays nvarchar(max) '$.ListDays' as JSON) where Service = 'RM'
				else
					select @ValueType = ValueType, @Type = Type, @PromotionValue = PromotionValue from openjson(isnull(@Pack3, '{"Type":"surcharge","ValueType":"percent","PromotionValue":0.0}')) with (ValueType varchar(10) '$.ValueType', Type varchar(10) '$.Type', PromotionValue float '$.PromotionValue')

				set @PlusOrSub = case when isnull(@Type, '') = 'discount' then -1 else 1 end

				declare @last int = (DATEDIFF(DAY, @ArrivalDate, @DepartureDate) + 1)

				;WITH n AS 
				(
				  SELECT TOP (DATEDIFF(DAY, @ArrivalDate, @DepartureDate) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
				  FROM sys.all_objects
				)

				Insert Into @re ([RentalRoomId], [BookingId], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DetailId], [DepartmentId], [Quantity], [VoucherCode],[Promotion])
				select Ma, BookingID, ServiceId, Date, 
				case when A.Promotion is not null 
				then case when JSON_VALUE(A.Promotion, '$.ValueType') = 'percent' then RoomRate * (1 + (case when JSON_VALUE(A.Promotion, '$.Type') = 'discount' then -1 else 1 end) * (case when cast(JSON_VALUE(A.Promotion, '$.PromotionValue') as float) between 0 and 100 then cast(JSON_VALUE(A.Promotion, '$.PromotionValue') as float) else 0 end) / 100) else case when (case when JSON_VALUE(A.Promotion, '$.Type') = 'discount' then -1 else 1 end) = -1 and RoomRate < JSON_VALUE(A.Promotion, '$.PromotionValue') then 0 else RoomRate + (case when JSON_VALUE(A.Promotion, '$.Type') = 'discount' then -1 else 1 end) * JSON_VALUE(A.Promotion, '$.PromotionValue') end end
				else case when isnull(@ValueType, '') = 'percent' then RoomRate * (1 + @PlusOrSub * (case when @PromotionValue between 0 and 100 then @PromotionValue else 0 end) / 100) else case when @PlusOrSub = -1 and RoomRate < isnull(@PromotionValue, 0) then 0 else RoomRate + @PlusOrSub * isnull(@PromotionValue, 0) end end end RoomRate,
				RateCode, IsRoom, DetailId, DepartmentId, Quantity, @VoucherCode, isnull(A.Promotion, @Pack3) from (
				select @Ma as Ma, @BookingId as BookingID, isnull(case when isnull(f_077.DepartmentId, '') in ('FB', 'HK') then (select SP5409.Services from SP5409 where OutletId = f_077.ServiceId) else f_077.ServiceId end, @ServiceId) as ServiceId, DATEADD(DAY, n-1, @ArrivalDate) as Date, 
				--Room Rate
				case when @ActualNumOfDays = 0 and isnull(@DayUse,'0') = '0' then isnull(@Total,0) else 
				(
					case when exists(select RentalRoomId from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM' and (f_077.ServiceId is null or f_077.ServiceId = 'RM'))
					then isnull((select sum(SP2102.Total)
							from SP2102 
							left join SP2100 on SP2100.Ma = SP2102.RentalRoomId 
							where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and ToDate and ServiceId = 'RM'),0)
					else isnull(f_077.Rate,isnull(f_076.Rate, @Total)) end
				) end as RoomRate,
				--RateCode
				isnull((select Top 1 RoomRateCode from SP2102
						where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM' and (f_077.ServiceId is null or f_077.ServiceId = 'RM')), isnull(f_076.RoomRateCode, f_077.PackageCode)) as RateCode, 
				--IsRoom
				case when exists(select RentalRoomId from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM')
				then (select Top 1 IsRoom from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM') else (select WalkIn from SP2000 dk inner join SP2100 pt on pt.BookingId = dk.Ma and pt.Ma = @Ma) end IsRoom, 
				(select Top 1 Ma from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM' and (f_077.ServiceId is null or f_077.ServiceId = 'RM')) DetailId,
				isnull(f_077.DepartmentId, 'FO') DepartmentId, isnull(f_077.Quantity, 1) Quantity,
				case when exists(select RentalRoomId from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM')
				then (select Top 1 Promotion from SP2102 where RentalRoomId = @Ma and DATEADD(DAY, n-1, @ArrivalDate) between FromDate and	ToDate and ServiceId = 'RM') else null end as Promotion
				from n 
					left join dbo.func_076(@Ma, @ArrivalDate, @DepartureDate, @RoomRateCode, @RoomType, @RoomKind) f_076 on DATEADD(DAY, n-1, @ArrivalDate) = f_076.Date
					left join dbo.func_077(@Ma, @ArrivalDate, @DepartureDate, @Pack2, @RoomType, @RoomKind) f_077 on DATEADD(DAY, n-1, @ArrivalDate) = f_077.Date
				where DATEADD(DAY, n-1, @ArrivalDate) between @fDate2 and @tDate2
				) A


				if(isnull(@Service, '') = 'RM' and isnull(@VoucherCode, '') != '' and isnull(@ListDays, '') != '')
				begin
					
					delete @TempVoucherRM
					
					insert into @TempVoucherRM
					select Value, Amount 
					from openjson(@ListDays) with (Value varchar(50) '$.Value', Amount decimal '$.Amount')
					
					if(@ValueType = 'amount')
					begin									
						--declare @totalRoom decimal(18,2) = (select sum(Total) from func_078(@fromDate, @toDate, @BookingId) where BookingId = @BookingId) 
						--declare @AmountRoom decimal(18,0) = @VoucherAmount / (select sum(case when ActualNumOfDays = 0 then 1 else ActualNumOfDays end) from sp2100 where BookingId = @BookingId and Status in (0,1,2,100)) * (select sum(case when ActualNumOfDays = 0 then 1 else ActualNumOfDays end) from sp2100 where  Ma = @Ma)

						--declare @AmountRoom decimal(18,0) = (@VoucherAmount - (select sum(DiscountAmount) from @TempVoucherRM)) / (select case when count(*) = 0 then 1 else count(*) end from @TempVoucherRM where DiscountAmount = 0)

						update @re set Total = case when Total >= @VoucherAmount then Total - @VoucherAmount else 0 end where RentalRoomId = @Ma and isnull(@VoucherCode, '') != '' and BillIdService is null and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherRM where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)


						--declare @totalRoomAfter decimal(18,2)
					
						--if(@id = @last)
						--begin
						--	set @totalRoomAfter = (select sum(Total) from @re where ServiceId = 'RM' and BookingId = @BookingId) 

						--	if(@totalRoom - @totalRoomAfter - @VoucherAmount > 0)
						--	begin
						--		;with cte as (
						--			select top 1 Total from @re where BookingId = @BookingId and BillIdService is null
						--		)
						--		update cte set Total = Total - (@totalRoom - @totalRoomAfter - @VoucherAmount)
						--	end
	
						--	if(@totalRoom - @totalRoomAfter - @VoucherAmount < 0)
						--	begin
						--		;with cte as (
						--			select top 1 Total from @re where BookingId = @BookingId and BillIdService is null
						--		)
						--		update cte set Total = Total + (@totalRoom - @totalRoomAfter - @VoucherAmount)
						--	end
						--end
					end
					else 
					begin
						update @re set Total =  Total * (1 + -1 * (case when @VoucherAmount between 0 and 100 then @VoucherAmount else 0 end) / 100) where RentalRoomId = @Ma and isnull(@VoucherCode, '') != ''  and BillIdService is null and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherRM where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
					end

					
						update @re set VoucherCode = '' where RentalRoomId = @Ma and ServiceId = 'RM'
						update @re set VoucherCode = @VoucherCode where RentalRoomId = @Ma and ServiceId = 'RM' and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherRM where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
				end

				

			end
			set @id = @id + 1
			FETCH NEXT FROM db_cursor INTO @Ma, @BookingId, @ArrivalDate, @DepartureDate, @ServiceId, @RoomRateCode, @Total,@ActualNumOfDays, @DayUse, @RoomType, @RoomKind, @Pack2, @Pack3, @VoucherInfo;
		END;
		CLOSE db_cursor;
		DEALLOCATE db_cursor;

		
		DECLARE db_cursor CURSOR 
		FOR 
			select pt.Ma, pt.BookingId, case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end ArrivalDate, 
			case when Service.Date is null then dateadd(day, (case when ActualNumOfDays = 0 then ActualNumOfDays else (ActualNumOfDays-1) end), pt.ArrivalDate)  else Service.Date end as DepartureDate,
			'' as ServiceId, pt.RoomRateCode,
			pt.ActualNumOfDays, pt.DayUse, pt.ExtraBed, pt.ExtraBedRate, pt.Child, pt.ArrivalDate ActualArrivalDate, dk.VoucherInfo
			from SP2100 as pt
			left join SP2000 dk on dk.Ma = pt.BookingId
			left join (select RentalRoomId1, Min(SP3000.Date) LateCheckInDate from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 group by RentalRoomId1) latein on pt.Ma = latein.RentalRoomId1 and latein.LateCheckInDate < pt.ArrivalDate
			left join (select RentalRoomId1, Max(SP3000.Date) Date from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 group by RentalRoomId1) Service on pt.Ma = Service.RentalRoomId1 and Service.Date > dateadd(day, (case when ActualNumOfDays = 0 then ActualNumOfDays else (ActualNumOfDays-1) end), pt.ArrivalDate)
			where pt.Status in (0,1,2,4,100) and (pt.Room is null or pt.Room not like '0%%')
			and (
				(@fDate1 between (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end)
				or
				(@tDate2 between (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end)
				or
				((case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end) >= @fDate1 and case when Service.Date is null then dateadd(day,(case when pt.ActualNumOfDays = 0 then pt.ActualNumOfDays else (pt.ActualNumOfDays-1) end),pt.ArrivalDate) else Service.Date end <= @tDate2)
			) 
			and (DATEDIFF(day, (case when latein.LateCheckInDate is not null then latein.LateCheckInDate else pt.ArrivalDate end), case when Service.Date is null then dateadd(day, pt.ActualNumOfDays, pt.ArrivalDate) else Service.Date end) != 0 or (select Count(*) from SP3000 inner join SP3004 on Ma = BillId and IsRoomNight = 1 and RentalRoomId1 = pt.Ma and SP3000.Date = pt.ArrivalDate) > 0 or pt.DayUse = '1')
		and (@registrationID = '' or pt.BookingId in (select value from string_split(@registrationID,',')))

			
			insert into @re([RentalRoomId], [BookingId], [BillIdService], [ServiceId], [Date], [Total], [RoomRateCode], [DepartmentId], [Quantity])
			select T.RentalRoomId, T.BookingId, T.[BillIdService],T.ServiceId, T.Date, T.Tien, T.[RoomRateCode], T.DepartmentId, T.Quantity
			from (select 
			case when pt2.Ma is not null then pt2.Ma else pt.Ma end as RentalRoomId,'' as  BookingId,
			h.RentalRoomId1, h.Ma as [BillIdService], h.ServiceId, h.Date, TotalAmount0 Tien,
			case when pt2.Ma is not null then pt2.RoomRateCode else pt.RoomRateCode end as RoomRateCode,
			h.DepartmentId, h.Quantity
			from SP3000 h 
			left join SP2100 as pt2 on pt2.Ma = h.RentalRoomId2
			left join SP2100 as pt on pt.Ma = h.RentalRoomId1
			where h.Edit=0 and h.Date between  @fDate1 and @tDate2 and h.ServiceId <> 'RM') as T
			where T.RentalRoomId1 in (select ma from SP2100 where Room like '0%')
		and (@registrationID = '' or T.BookingId in (select value from string_split(@registrationID,',')))

		OPEN db_cursor;
		FETCH NEXT FROM db_cursor INTO @Ma, @BookingId, @ArrivalDate, @DepartureDate, @ServiceId, @RoomRateCode,@ActualNumOfDays,@DayUse,@ExtraBed,@ExtraBedRate,@Child, @ActualArrivalDate, @VoucherInfo;
		WHILE @@FETCH_STATUS = 0  
		BEGIN  
			if(@ArrivalDate <= @DepartureDate)
			begin
				select top 1 @Service = isnull(Service, '') from openjson(@VoucherInfo) with (VoucherCode varchar(50) '$.VoucherCode', DiscountType int '$.DiscountType', VoucherAmount float '$.VoucherAmount', Service char(2) '$.ServiceId') where Service != 'RM'

				set @ValueType = null
				set @VoucherCode = null
				set @PromotionValue = null
				set @VoucherAmount = null
				set @ListDays = null

				if(isnull(@Service, '') != 'RM' and isnull(@Service, '') != '')
					select top 1 @ValueType = case when DiscountType = 1 then 'percent' else 'amount' end, @Type = 'discount', @PromotionValue = 0, @VoucherAmount = VoucherAmount, @VoucherCode = VoucherCode, @ListDays = ListDays from openjson(@VoucherInfo) with (VoucherCode varchar(50) '$.VoucherCode', DiscountType int '$.DiscountType', VoucherAmount float '$.VoucherAmount', Service char(2) '$.ServiceId', ListDays nvarchar(max) '$.ListDays' as JSON) where Service != 'RM'
				
				;WITH n AS 
				(
				  SELECT TOP (DATEDIFF(DAY, @ArrivalDate, @DepartureDate) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
				  FROM sys.all_objects
				)

				Insert Into @re ([RentalRoomId], [BookingId], [ServiceId], [Date], [Total], [RoomRateCode], [IsRoom], [DetailId], [DepartmentId], [Quantity])
				select temp.Ma, temp.BookingId, ptdvtd.ServiceId, temp.Date, Sum(ptdvtd.Total) Total, Min(temp.RoomRateCode) RoomRateCode, 
				Min(ptdvtd.IsRoom) IsRoom, Min(ptdvtd.Ma) Ma, 'FO' DepartmentId, Sum(ptdvtd.Quantity) Quantity
				from (
					select @Ma as Ma, @BookingId as BookingId, @ServiceId as ServiceId, DATEADD(DAY, n-1, @ArrivalDate) as Date, 
					@ArrivalDate as ArrivalDate, @ActualNumOfDays as ActualNumOfDays, @RoomRateCode as RoomRateCode
					from n
					where DATEADD(DAY, n-1, @ArrivalDate) between @fDate2 and @tDate2
				) as temp 
				left join SP2102 ptdvtd on temp.Ma = ptdvtd.RentalRoomId and temp.Date between ptdvtd.FromDate and ptdvtd.ToDate
				where  ptdvtd.ServiceId is not null and ptdvtd.ServiceId not in ('RM', 'EB')
				group by temp.Ma, temp.BookingId, ptdvtd.ServiceId, temp.Date
				
				;WITH Dates AS
					 (SELECT @ArrivalDate as Date
					  UNION ALL
					  SELECT DATEADD(day, 1, Date)
						FROM Dates
					   WHERE DATEADD(day, 1, Date) <= @DepartureDate
					 )
				Insert Into @re ([RentalRoomId], [BookingId], [ServiceId], [Date], [Total],[RoomRateCode],[IsRoom], [DetailId], [DepartmentId], [Quantity])
				SELECT @Ma, @BookingId, 'EB', Dates.Date, isnull(Total, @ExtraBed * @ExtraBedRate) Total, @RoomRateCode, isnull(d.IsRoom, 0), d.Ma, 'FO' DepartmentId, isnull(d.Quantity, @ExtraBed)Quantity
				FROM Dates 
					left join SP2102 d on RentalRoomId = @Ma and ServiceId = 'EB' and Dates.Date = d.FromDate
				where isnull(Quantity, 0) > 0 and Dates.Date between @fDate2 and @tDate2
					and (@ExtraBed > 0 or d.Ma is not null)
				OPTION (MAXRECURSION 0);		
				
				if(isnull(@Service, '') != '' and isnull(@ListDays, '') != '' and isnull(@Service, '') != 'RM')
				begin
				
					delete @TempVoucherService

					insert into @TempVoucherService
					select Value, Amount 
					from openjson(@ListDays) with (Value varchar(50) '$.Value', Amount decimal '$.Amount')
					
					if(@ValueType = 'amount')
					begin
						--set @totalRoom = (select sum(Total) from @re where RentalRoomId = @Ma and ServiceId = @Service) 
						--set @AmountRoom = @VoucherAmount / (select count(*) from sp2102 where ServiceId = @Service and RentalRoomId in (select Ma from SP2100 where BookingId = @BookingId and Status in (0,1,2,100))) * (select count(*) from sp2102 where ServiceId = @Service and RentalRoomId = @Ma)

						--set @AmountRoom = (@VoucherAmount - (select sum(DiscountAmount) from @TempVoucherService)) / (select case when count(*) = 0 then 1 else count(*) end from @TempVoucherService where DiscountAmount = 0)

						update @re set Total = case when Total >= @VoucherAmount then Total - @VoucherAmount else 0 end where RentalRoomId = @Ma and ServiceId = @Service and BillIdService is null and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)

						update @re set VoucherCode = @VoucherCode where RentalRoomId = @Ma and ServiceId = @Service and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
					
						--set @totalRoomAfter  = (select sum(Total) from @re where RentalRoomId = @Ma and ServiceId = @Service) 

						--if(@totalRoom - @totalRoomAfter - @AmountRoom > 0)
						--begin
						--	;with cte as (
						--		select top 1 Total from @re where RentalRoomId = @Ma and BillIdService is null and ServiceId = @Service and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where DiscountAmount = 0 and SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
						--	)
						--	update cte set Total = Total + (@totalRoom - @totalRoomAfter - @AmountRoom)
						--end
	
						--if(@totalRoom - @totalRoomAfter - @AmountRoom < 0)
						--begin
						--	;with cte as (
						--		select top 1 Total from @re where RentalRoomId = @Ma and BillIdService is null and ServiceId = @Service and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where DiscountAmount = 0 and SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
						--	)
						--	update cte set Total = Total - (@totalRoom - @totalRoomAfter - @AmountRoom)
						--end
					end
					else
					begin
						update @re set Total =   Total / 100 *(100 - @VoucherAmount) where RentalRoomId = @Ma and ServiceId = @Service and BillIdService is null and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
						
						update @re set VoucherCode = @VoucherCode where RentalRoomId = @Ma and ServiceId = @Service and Date in (select SUBSTRING(VoucherValue, LEN(VoucherValue) - CHARINDEX('|', REVERSE(VoucherValue)) + 2, LEN(VoucherValue)) from @TempVoucherService where  SUBSTRING(VoucherValue, 0, CHARINDEX('|',VoucherValue,0)) = @Ma)
					end
				end
				
				if (@Child > 0)
				begin
					Declare ChildCursor CURSOR FOR    
					select ChildID from SP2500 where Status in (0, 1) and RentalRoomId = @Ma group by ChildID
					Open ChildCursor    
					Fetch next from ChildCursor into @ChildID
  
					while(@@FETCH_STATUS=0)  
					BEGIN
						;WITH Dates AS
							 (SELECT @ArrivalDate as Date
							  UNION ALL
							  SELECT DATEADD(day, 1, Date)
								FROM Dates
							   WHERE DATEADD(day, 1, Date) <= @DepartureDate
							 )
						Insert Into @re ([RentalRoomId], [BookingId], [ServiceId], [Date], [Total],[RoomRateCode],[IsRoom], [DepartmentId], [Quantity])
						SELECT @Ma, @BookingId, @ChildBFService, Dates.Date, ct.Rate, @RoomRateCode, isnull(ct.PostToRoom, 0), 'FO' DepartmentId, 1 Quantity
						FROM Dates 
							left join SP2401 ct on Dates.Date = ct.Date and ct.RentalRoomId = @Ma and ct.ChildID = @ChildID
						where ct.Breakfast = 1 and ct.ExtraBreakfast = 1 and Dates.Date between @fDate2 and @tDate2
						OPTION (MAXRECURSION 0);
					Fetch next from ChildCursor into @ChildID  
					END  
  
					Close ChildCursor   
  
					DEALLOCATE ChildCursor  
				end

				
				;WITH n AS 
				(
				  SELECT TOP (DATEDIFF(DAY, @ArrivalDate, @DepartureDate) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
				  FROM sys.all_objects
				)

				Insert Into @re ([RentalRoomId], [BookingId], [ServiceId], [Date], [Total],[RoomRateCode], [DepartmentId], [Quantity])
				select temp.Ma, temp.BookingId, pd.ServiceCode as ServiceId,temp.Date, pd.Total as Total, temp.RoomRateCode, 'FO' DepartmentId, pd.Quantity
				from (
					select @Ma as Ma, @BookingId as BookingId, @ServiceId as ServiceId, DATEADD(DAY, n-1, @ArrivalDate) as Date, 
					@ArrivalDate as ArrivalDate, @ActualNumOfDays as ActualNumOfDays, @RoomRateCode as RoomRateCode
					from n
					where DATEADD(DAY, n-1, @ArrivalDate) between @fDate1 and @tDate2
				) as temp 
				left join (
					select PackageCode, ServiceCode, OrderDay, DateAdd(day,OrderDay-1,@ArrivalDate) as ArrivalDate,  isnull(Total,0) as Total,
					BeginDate, EndDate, Quantity
					from (select SP1317.*,SP1323.BeginDate,SP1323.EndDate 
					from SP1317 left join SP1323 on SP1317.PackageCode = SP1323.Ma where SP1317.Status <> 3) as MGP 
					where ServiceCode <> 'RM' and PackageCode = @RoomRateCode
					) as pd on temp.RoomRateCode = pd.PackageCode and Datediff(Day, @ArrivalDate, temp.Date) = pd.OrderDay-1
				where pd.ServiceCode <> 'RM' and temp.Date between pd.BeginDate and pd.EndDate;
			End
			
			FETCH NEXT FROM db_cursor INTO @Ma, @BookingId, @ArrivalDate, @DepartureDate, @ServiceId, @RoomRateCode, @ActualNumOfDays, @DayUse,@ExtraBed,@ExtraBedRate,@Child, @ActualArrivalDate,@VoucherInfo;
		END;
		CLOSE db_cursor;
		DEALLOCATE db_cursor;
	end

	--update @re set Total = 0 where Total < 0

	return
end
GO


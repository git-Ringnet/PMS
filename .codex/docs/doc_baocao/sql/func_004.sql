create     function [dbo].[func_004](@fromDate Date, @toDate Date)
returns @re table(
		[Date] date,
		[RoomId] varchar(10),
		[BookingId] int
	)
 as
begin

	declare @Room varchar(50), @BookingCode varchar(50), @Arrival Date, @Departure Date;

	
	DECLARE db_cursor1 CURSOR 
	FOR 
		
		SELECT phTax.Ma, phTax.BookingId, phTax.ArrivalDate, dateadd(day,phTax.ActualNumOfDays - 1,phTax.ArrivalDate) as DepartureDate 
		FROM SP2100 as phTax
		left join (select * from SP1000 where RoomTypeId in (select Ma from SP1100 where IsDayUse = 1)) as ph on ph.Ma = phTax.Room
		left join SP2000 dk on dk.Ma = phTax.BookingId
		left join SP1302 ct on dk.TravelAgency = ct.Ma
		left join SP1308 mk on dk.MarketSegment = mk.Ma
		left join SP1311 ttdk on ttdk.BookingStatusId=dk.BookingStatus
		Where 
		phTax.ActualNumOfDays > 0 and ttdk.IsAvailability = 1 and isnull(phTax.DayUse,'') <> 1 and phTax.Status in (0,1,2,100)
		and (Room is null or Room not like '0%%')
		and (
			(@fromDate between phTax.ArrivalDate and dateadd(day,phTax.ActualNumOfDays - 1,phTax.ArrivalDate))
			or
			(@toDate between phTax.ArrivalDate and dateadd(day,phTax.ActualNumOfDays - 1,phTax.ArrivalDate))
			or
			(phTax.ArrivalDate >= @fromDate and dateadd(day,phTax.ActualNumOfDays - 1,phTax.ArrivalDate) <= @toDate)
		)
		and isnull(ph.RoomHouseUse,0) != 1
		union all
		
		SELECT phTax.Ma, phTax.BookingId, phTax.ArrivalDate, dateadd(day,phTax.ActualNumOfDays,phTax.ArrivalDate) as DepartureDate
		FROM SP2100 as phTax
		left join (select * from SP1000 where RoomTypeId in (select Ma from SP1100 where IsDayUse = 1)) as ph on ph.Ma = phTax.Room
		left join SP2000 dk on dk.Ma = phTax.BookingId
		left join SP1302 ct on dk.TravelAgency = ct.Ma
		left join SP1308 mk on dk.MarketSegment = mk.Ma
		left join SP1311 ttdk on ttdk.BookingStatusId = dk.BookingStatus
		Where 
		phTax.ActualNumOfDays = 0 and ttdk.IsAvailability = 1 and isnull(phTax.DayUse,'') = 1 and  phTax.Status in (0,1)
		and (Room is null or Room not like '0%%')
		and (
			(@fromDate between phTax.ArrivalDate and dateadd(day,phTax.ActualNumOfDays,phTax.ArrivalDate))
			or
			(@toDate between phTax.ArrivalDate and dateadd(day,phTax.ActualNumOfDays,phTax.ArrivalDate))
			or
			(phTax.ArrivalDate >= @fromDate and dateadd(day,phTax.ActualNumOfDays,phTax.ArrivalDate) <= @toDate)
		)
		and isnull(ph.RoomHouseUse,0) != 1

	OPEN db_cursor1;
	FETCH NEXT FROM db_cursor1 INTO @Room, @BookingCode, @Arrival, @Departure;
	WHILE @@FETCH_STATUS = 0  
	BEGIN  

		begin
			;WITH n AS 
			(
			  SELECT TOP (DATEDIFF(DAY, @Arrival, @Departure) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
			  FROM sys.all_objects
			)
			Insert Into @re
			SELECT DATEADD(DAY, n-1, @Arrival) as Date,@Room,@BookingCode
			FROM n 
			where DATEADD(DAY, n-1, @Arrival) between @fromDate and @toDate

		end
		
		FETCH NEXT FROM db_cursor1 INTO @Room, @BookingCode, @Arrival, @Departure;
	END;
	CLOSE db_cursor1;
	DEALLOCATE db_cursor1;

	 
	
	DECLARE db_cursor2 CURSOR 
	FOR 
		SELECT phTaxType.Ma, phTaxType.BookingId, phTaxType.ArrivalDate, dateadd(day,phTaxType.ActualNumOfDays - 1,phTaxType.ArrivalDate) as DepartureDate 
		FROM SP2106 as phTaxType
		left join SP2000 dk on dk.Ma = phTaxType.BookingId
		left join SP1311 ttdk on ttdk.BookingStatusId=dk.BookingStatus
		Where phTaxType.Status in (0,1,2,100)
		and RoomType in (select Ma from SP1100 where IsDayUse = 1) 
		and (
			(@fromDate between phTaxType.ArrivalDate and dateadd(day,phTaxType.ActualNumOfDays - 1,phTaxType.ArrivalDate))
			or
			(@toDate between phTaxType.ArrivalDate and dateadd(day,phTaxType.ActualNumOfDays - 1,phTaxType.ArrivalDate))
			or
			(phTaxType.ArrivalDate >= @fromDate and dateadd(day,phTaxType.ActualNumOfDays - 1,phTaxType.ArrivalDate) <= @toDate)
		)
	OPEN db_cursor2;
	FETCH NEXT FROM db_cursor2 INTO @Room, @BookingCode, @Arrival, @Departure;
	WHILE @@FETCH_STATUS = 0  
	BEGIN  
		;WITH n AS 
		(
		  SELECT TOP (DATEDIFF(DAY, @Arrival, @Departure) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
		  FROM sys.all_objects
		)
		Insert Into @re
			SELECT DATEADD(DAY, n-1, @Arrival) as Date,@Room,@BookingCode
			FROM n 
			where DATEADD(DAY, n-1, @Arrival) between @fromDate and @toDate
		
		FETCH NEXT FROM db_cursor2 INTO @Room, @BookingCode, @Arrival, @Departure;
	END;
	CLOSE db_cursor2;
	DEALLOCATE db_cursor2;


	return
end
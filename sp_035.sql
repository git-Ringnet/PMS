
/****** Object:  StoredProcedure [dbo].[sp_035]    Script Date: 10/08/2026 3:21:11 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



--exec sp_035 '20251201', '20251201', '','','', 1, 'breakfast', 1


CREATE   proc [dbo].[sp_035]
@FromDate date,
@ToDate date,
@Username nvarchar(50),
@SortBy nvarchar(50),
@SortType nvarchar(4),
@LateCheckin bit,
@DateSearchType varchar(20) = 'breakfast',
@ShowType int = 1 -- 0: all, 1: breakfast, 2: no breakfast
 as
begin
	if exists (select * from sys.tables where name='BreakfastVoucher')
	drop table BreakfastVoucher
	
	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	declare @SystemDate date=(select SystemDate from SP1500)
	if @Username='' or @Username is null 
		set @Username=''
	else
		set @Username='username like '''+@Username+''' and'
	if @SortBy='' or @SortBy is null
		set @SortType=''
	declare @RoomO nvarchar(50)=N'PHÒNG Ở THẬT'
	declare @RoomLate nvarchar(50)=N'PHÒNG LATE CHECK IN'
	declare @sql nvarchar(max)
	declare @fromDateOriginal date = @FromDate
	
	set @sql='create table BreakfastVoucher(ArrivalDate datetime,Room varchar(10),RentalRoom varchar(10),Adults smallint, ShowAdults smallint, Children smallint,ChildrenNK smallint,ChildrenNoBreakfast smallint, Status varchar(5), BookingId bigint, ArrivalDatePT datetime, FirstName nvarchar(500),
			CheckoutDate date, Nationality nvarchar(100), RoomType nvarchar(50), Description nvarchar(200), Quantity int, Amount money, Breakfast int, IsBreakfast int)'
	PRINT @sql
	exec sp_executesql @sql
	while @FromDate <= @ToDate
		begin
			set @sql='insert into BreakfastVoucher 
			select ''' + cast(@FromDate as varchar) + ''',
			pt.Room,
			pt.Ma,
			count(ptk.CustomerId) as Adult,
			Count(pt.Ma) as ShowAdults,
			(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''') WHERE Breakfast = 1 AND Rate > 0),
			(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''') WHERE Breakfast = 1 AND Rate = 0), 
			(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''') WHERE Breakfast = 0),
			pt.Status,
			pt.BookingId,
			pt.ArrivalDate as ArrivalDatePT,
			k2.FirstName,
			pt.CheckoutDate,
			qt.NationalityName,N'''+@RoomO+''',
			isnull(a.Description, ''''),
			isnull(a.Quantity,0),
			isnull(a.Amount,0),
			pt.Breakfast,
			case when (pt.Breakfast <> 0 or isnull(a.Amount,0)>0
				or (SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''') WHERE Breakfast = 1 AND Rate > 0) > 0) then 1 else 0 end as IsBreakfast 
			from SP2100 pt 
			left join vw_001 bk on bk.Ma = pt.BookingId
			left join SP2200 ptk on pt.Ma = ptk.RentalRoomId
			left join SP2300 k on ptk.CustomerId=k.Id
			left join SP2200 ptk2 on pt.Ma = ptk2.RentalRoomId and ptk2.IsMainGuest = 1
			left join SP2300 k2 on ptk2.CustomerId=k2.Id 
			left join SP8020 qt on k2.Nationality= qt.NationalityId
			left join (select ptdvtd.RentalRoomId, string_agg(dv.Service + '': '' + convert(varchar(10),ptdvtd.Quantity), char(13)) as Description, sum(Quantity) as Quantity, sum(Total) as Amount from		sp2102 ptdvtd
						left join sp1306 dv on dv.Ma = ptdvtd.ServiceId
						where FromDate = '''+ cast(dateadd(dd,-1,@FromDate) as varchar) + ''' and serviceid in (select * from string_split((select value  from sp1600 where Parameter = ''ServiceBreakfastReport''), '',''))
						group by ptdvtd.RentalRoomId) a on a.RentalRoomId = pt.Ma
			where (Room is null or Room not like ''0%'') 
				and pt.Status  in (0,1,2,100) 
				and ('''+@DateSearchType+''' = ''arrival'' or ('''+cast(@FromDate as varchar)+''' between ptk.ArrivalDate+1 and ptk.CheckoutDate)
				or ('''+cast(@FromDate as varchar)+''' = ptk.ArrivalDate and pt.ArrivalTime <= ''00:01'')
				)
				and ('''+@DateSearchType+''' = ''breakfast'' or pt.ArrivalDate = '''+cast(@FromDate as varchar)+''')
				and (pt.ArrivalDate != pt.CheckoutDate or '''+cast(@FromDate as varchar)+''' between ptk.ArrivalDate and ptk.CheckoutDate)
				-- and ptk.IsMainGuest = 1
				and pt.Ma not in (select Ma from SP4003 where ActualArrivalDate = '''+cast(@FromDate as varchar)+''') 
				and bk.IsAvailability = 1
				Group by  pt.Ma ,pt.Adult,pt.Room, pt.BookingId, pt.ArrivalDate,pt.Status,ptk.RentalRoomId, a.Description, a.Quantity, a.Amount, pt.Breakfast ,pt.CheckoutDate, qt.NationalityName, k2.FirstName ; '

			if(@LateCheckin = 1)
			begin
				set @sql += ' insert into BreakfastVoucher 
				select ''' + cast(@FromDate as varchar) + ''',
				pt.Room,
				pt.Ma,
				count(ptk.CustomerId) as Adult,
				Count(pt.Ma) as ShowAdults,
				(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''')  WHERE Breakfast = 1 AND Rate > 0),
				(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''')  where Breakfast = 1 and Rate = 0),
				(SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''')  where Breakfast = 0),
				pt.Status,
				pt.BookingId,
				pt.ArrivalDate,
				k2.FirstName,
				pt.CheckoutDate,
				qt.NationalityName , N'''+@RoomLate+''',	
				isnull(a.Description, ''''),
				isnull(a.Quantity,0),
				isnull(a.Amount,0),
				pt.Breakfast,
				case when (pt.Breakfast <> 0 or isnull(a.Amount,0)>0
				or (SELECT COUNT(RentalRoomId) FROM dbo.func_012(pt.Ma,'''+cast(@FromDate as varchar)+''') WHERE Breakfast = 1 AND Rate > 0) > 0) then 1 else 0 end as IsBreakfast 
				from SP2100 pt 
				left join vw_001 bk on bk.Ma = pt.BookingId
				left join SP2200 ptk on pt.Ma = ptk.RentalRoomId 
				left join SP2300 k on ptk.CustomerId=k.Id 
				left join SP2200 ptk2 on pt.Ma = ptk2.RentalRoomId and ptk2.IsMainGuest = 1
				left join SP2300 k2 on ptk2.CustomerId=k2.Id 
				left join SP8020 qt on k2.Nationality= qt.NationalityId 
				left join (select ptdvtd.RentalRoomId, string_agg(dv.Service + '': '' + convert(varchar(10),ptdvtd.Quantity), char(13)) as Description, sum(Quantity) as Quantity, sum(Total) as Amount from		sp2102 ptdvtd
						left join sp1306 dv on dv.Ma = ptdvtd.ServiceId
						where FromDate = '''+ cast(dateadd(dd,-1,@FromDate) as varchar) + ''' and serviceid in (select * from string_split((select value  from sp1600 where Parameter = ''ServiceBreakfastReport''), '',''))
						group by ptdvtd.RentalRoomId) a on a.RentalRoomId = pt.Ma
				where (pt.Room is null or pt.Room not like ''0%%'') 
					and pt.Status in (0,1,2,100)
					and (ActualArrivalDate < '''+ cast(@FromDate as varchar)+ ''' or pt.ArrivalTime <= ''09:30'')
					-- and ptk.IsMainGuest = 1
					and bk.IsAvailability = 1
					and pt.Ma in (select lci.Ma from SP4003 lci 
					inner join SP3000 hddv on  lci.Ma=hddv.RentalRoomId1 
					inner join SP3004 hdtp on hdtp.BillId=hddv.Ma 
					where ActualArrivalDate = '''+ cast(@FromDate as varchar)+ ''' and IsRoomNight=1
					) 
					
					Group by pt.Ma ,pt.Adult,pt.Room, pt.BookingId, pt.ArrivalDate,pt.Status,ptk.RentalRoomId, a.Description, a.Quantity, a.Amount, pt.Breakfast ,pt.CheckoutDate, qt.NationalityName, k2.FirstName ;'
			end

			PRINT @sql
			exec sp_executesql @sql
			set @FromDate=dateadd(dd,1,@FromDate)
		end
		if (@SortBy like '%ArrivalDate%')
		begin
			set @SQL='SELECT a.*
					,CASE
						WHEN dbo.SP2000.Provide2 = ''0'' THEN ''0''
						ELSE a.Children + a.Adults + a.ChildrenNK
					END AS INVAT,convert(nvarchar(20),dk.Ma)+''-''+dk.BookingName as InformationBK,SP1302.Company,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT top 1 convert(decimal,BreakfastAdultRate) FROM SP1322)*(case when SP2100.Breakfast = 0 then 0 else Adults end) end as BreakfatsAmountAdult,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT ISNULL(SUM(Rate), 0) FROM dbo.func_012 (a.RentalRoom,a.ArrivalDate) bf WHERE bf.Breakfast = 1 and bf.ExtraBreakfast = 0) end as BreakfatsAmountChild,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT ISNULL(SUM(Rate), 0) FROM dbo.func_012 (a.RentalRoom,a.ArrivalDate) bf WHERE bf.Breakfast = 1 and bf.ExtraBreakfast = 1) end as BreakfatsAmountChildExtra,'''+@prefix+''' + cast(a.BookingId as varchar(20)) as Booking

					 FROM  dbo.BreakfastVoucher a
						  LEFT JOIN SP2100 ON a.RentalRoom = SP2100.Ma
						  left join func_054('''+cast(dateadd(day, -1, @fromDateOriginal) as varchar)+''','''+cast(@ToDate as varchar)+''','''') rev on rev.RentalRoomId = a.RentalRoom and rev.ServiceId = ''RM'' and IsRoomNight = 1 and dateadd(day, -1,a.ArrivalDate) =  rev.Date
						  inner join SP2000 dk on dk.Ma=SP2100.BookingId
						  LEFT JOIN dbo.SP2000 ON SP2100.BookingId = SP2000.Ma
						  LEFT JOIN dbo.SP1302 ON SP2000.TRAVELAGENCY=SP1302.MA'

						  if(@ShowType = 1) set @sql += ' where IsBreakfast = 1'
						  if(@ShowType = 2) set @sql += ' where IsBreakfast = 0'
						  
						  set @sql+=' order by a.'+@SortBy+' '+@SortType
						  set @sql+=',a.Room '+@SortType
		end
		else
		begin
			set @SQL='SELECT a.*
					,CASE
						WHEN dbo.SP2000.Provide2 = ''0'' THEN ''0''
						ELSE a.Children + a.Adults + a.ChildrenNK
					END AS INVAT,convert(nvarchar(20),dk.Ma)+''-''+dk.BookingName as InformationBK,SP1302.Company,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT top 1 convert(decimal,BreakfastAdultRate) FROM SP1322)*(case when SP2100.Breakfast = 0 then 0 else Adults end) end as BreakfatsAmountAdult,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT ISNULL(SUM(Rate), 0) FROM dbo.func_012 (a.RentalRoom,a.ArrivalDate) bf WHERE bf.Breakfast = 1 and bf.ExtraBreakfast = 0) end as BreakfatsAmountChild,
					case when isnull(rev.Total, SP2100.Rate) = 0 then 0 else (SELECT ISNULL(SUM(Rate), 0) FROM dbo.func_012 (a.RentalRoom,a.ArrivalDate) bf WHERE bf.Breakfast = 1 and bf.ExtraBreakfast = 1) end as BreakfatsAmountChildExtra,'''+@prefix+''' + cast(a.BookingId as varchar(20)) as Booking

					 FROM  dbo.BreakfastVoucher a
						  LEFT JOIN SP2100 ON a.RentalRoom = SP2100.Ma
						  left join func_054('''+cast(dateadd(day, -1, @fromDateOriginal) as varchar)+''','''+cast(@ToDate as varchar)+''','''') rev on rev.RentalRoomId = a.RentalRoom and rev.ServiceId = ''RM'' and IsRoomNight = 1 and dateadd(day, -1,a.ArrivalDate) =  rev.Date
						  inner join SP2000 dk on dk.Ma=SP2100.BookingId
						  LEFT JOIN dbo.SP2000 ON SP2100.BookingId = SP2000.Ma
						  LEFT JOIN dbo.SP1302 ON SP2000.TRAVELAGENCY=SP1302.MA'
						  
						  if(@ShowType = 1) set @sql += ' where IsBreakfast = 1'
						  if(@ShowType = 2) set @sql += ' where IsBreakfast = 0'

						  if (@SortBy != '')
						  set @sql+=' order by cast(a.'+@SortBy+' as int) '+@SortType
		end


	PRINT @sql
	exec sp_executesql @sql
	if exists (select * from sys.tables
	 where name='BreakfastVoucher')
	drop table  BreakfastVoucher
END








GO



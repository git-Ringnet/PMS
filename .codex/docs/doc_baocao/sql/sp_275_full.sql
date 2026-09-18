--exec sp_275 '20250710', '20250710'





Create   procedure [dbo].[sp_275] (@fromdate date, @todate date)

as

begin

	create table #tempResult(

		Date date,

		Segment nvarchar(100),

		CheckinRoom int,

		CheckoutRoom int,

		InhouseRoom int,

		DayUseRoom int,

		BreakfastGuestNum int,

		NoBreakfastGuestNum int

	)



	select pt.Date, isnull(pt.NumOfRoom,0) DepRooms,

		MarketSegment

	into #tempDep

	from (select pt1.ArrivalDate+pt1.ActualNumOfDays as Date, COUNT(*) as NumOfRoom, mk.MarketSegment

	from SP2100 pt1

	left join vw_001 dk on pt1.BookingId = dk.Ma

	left join sp1302 ct on dk.TravelAgency = ct.Ma

	left join sp1308 mk on mk.Ma = ct.MarketSegment

	where pt1.ArrivalDate+pt1.ActualNumOfDays between dateadd(day, -1, @fromdate) and @todate

		and pt1.Status in(0,1,2)

		and (pt1.Room is null or pt1.Room not like '0%%')

		and dk.IsAvailability = 1

	group by pt1.ArrivalDate+pt1.ActualNumOfDays, mk.MarketSegment) pt



	select pt.Date,

	isnull(pt.NumOfRoom,0) as ArrRooms, MarketSegment

	into #tempArrival

	from (select pt.ArrivalDate as Date, isnull(count(*),0) as NumOfRoom, mk.MarketSegment

		from SP2100 pt

		left join vw_001 dk on pt.BookingId = dk.Ma

		left join sp2100 cp on pt.Ma = cp.MoveRoom

		left join sp1302 ct on dk.TravelAgency = ct.Ma

		left join sp1308 mk on mk.Ma = ct.MarketSegment

		where pt.ArrivalDate between dateadd(day, -1, @fromdate) and @todate and dk.IsAvailability = 1

		and pt.Status in (0, 1, 2, 100) and isnull(pt.Room,'') not like '0%'

		and (pt.Status != 100 or pt.ArrivalDate != pt.CheckoutDate)

		and (cp.Ma is null or cp.ActualArrivalDate = pt.ArrivalDate)

		--and pt.ActualNumOfDays != 0

	group by pt.ArrivalDate, mk.MarketSegment) pt



	select * into #tempBalance from func_054(dateadd(day, -1, @fromdate), @todate, '')





	select pt.Date,

	isnull(pt.NumOfRoom,0) as DayUseRooms, MarketSegment

	into #tempDayUse

	from (select pt.ArrivalDate as Date, isnull(count(*),0) as NumOfRoom, mk.MarketSegment

		from SP2100 pt

		left join vw_001 dk on pt.BookingId = dk.Ma

		left join sp1302 ct on dk.TravelAgency = ct.Ma

		left join sp1308 mk on mk.Ma = ct.MarketSegment

		where pt.ArrivalDate between @fromdate and @todate and dk.IsAvailability = 1

		and pt.Status in (0, 1, 2) and isnull(Room,'') not like '0%' and pt.ActualNumOfDays = 0

	group by pt.ArrivalDate, mk.MarketSegment) pt





	select MarketSegment,Date, Count(*) as OccRooms

	into #tempOCC

	from (

		select mk.MarketSegment,Date, RentalRoomId

		from (select * from #tempBalance

		) A inner join SP2100 on SP2100.Ma = A.RentalRoomId

		left join SP4003 on SP2100.Ma = SP4003.Ma and A.Date = SP4003.LateCheckInDate

		left join vw_001 dk on SP2100.BookingId = dk.Ma

		left join sp1302 ct on dk.TravelAgency = ct.Ma

		left join sp1308 mk on mk.Ma = ct.MarketSegment

		where ServiceId = 'RM' and IsRoomNight = 1 and dk.IsAvailability = 1 and Sp4003.ma is null

		and SP2100.CheckoutDate > Date

	) A group by Date,MarketSegment





	select pt.*, case when seg.MarketSegment in ('Travel Agent','FOC','Voucher') then N'Khách TA'

	when seg.MarketSegment = 'Online Travel Agent' then N'Khách OTA'

	when seg.MarketSegment = 'Corporate' then N'Khách Corp'

	when seg.MarketSegment in ('Walk-In', 'Free Individual Traveler', 'Fanpage') then N'Khách Walk-in, FIT, Fanpage'

	--when seg.MarketSegment not in ('Walk-In', 'Free Individual Traveler', 'Fanpage', 'Corporate', 'Online Travel Agent', 'Travel Agent','FOC','Voucher') then N'Khác'

	end MarketSegment into #tempRentalRoom

	from sp2100 pt

	left join vw_001 bk on bk.Ma = pt.BookingId

	left join sp1302 ct on ct.Ma = bk.TravelAgency

	left join Sp1308 seg on seg.Ma = ct.MarketSegment

	where (pt.ma in (select RentalRoomId from func_054(@fromdate, dateadd(day, 1,@todate), ''))

	or pt.ArrivalDate+ pt.ActualNumOfDays between @fromdate and dateadd(day, 1,@todate)) and pt.Status in (0,1,2,100) and bk.IsAvailability = 1



	select coalesce(arr.date, dep.date, occ.date, du.date) as Date, coalesce(arr.MarketSegment, dep.MarketSegment, occ.MarketSegment, du.MarketSegment) as MarketSegment, isnull(arr.ArrRooms, 0) arrNum, isnull(dep.DepRooms, 0) depNum, isnull(occ.OccRooms, 0)
occNum, isnull(du.DayUseRooms, 0) dayUseNum

	into #tempRoomInfo

	from #tempArrival arr

	full outer join #tempDep dep on arr.Date = dep.Date and arr.MarketSegment = dep.MarketSegment

	full outer join #tempOCC occ on arr.Date = occ.Date and arr.MarketSegment = occ.MarketSegment

	full outer join #tempDayUse du on arr.Date = du.Date and arr.MarketSegment = du.MarketSegment



	select Date, case when MarketSegment in ('Travel Agent','FOC','Voucher') then N'Khách TA'

	when MarketSegment = 'Online Travel Agent' then N'Khách OTA'

	when MarketSegment = 'Corporate' then N'Khách Corp'

	when MarketSegment in ('Walk-In', 'Free Individual Traveler', 'Fanpage') then N'Khách Walk-in, FIT, Fanpage'

	-- when MarketSegment not in ('Walk-In', 'Free Individual Traveler', 'Fanpage', 'Corporate', 'Online Travel Agent', 'Travel Agent') then N'Khác'

	end MarketSegment,

	sum(arrNum) arrNum, sum(depNum) depNum, sum(occNum) occNum, sum(dayUseNum) dayUseNum

	into #tempDetailSegment

	from #tempRoomInfo

	group by Date, case when MarketSegment in ('Travel Agent','FOC','Voucher') then N'Khách TA'

	when MarketSegment = 'Online Travel Agent' then N'Khách OTA'

	when MarketSegment = 'Corporate' then N'Khách Corp'

	when MarketSegment in ('Walk-In', 'Free Individual Traveler', 'Fanpage') then N'Khách Walk-in, FIT, Fanpage'

	 --when MarketSegment not in ('Walk-In', 'Free Individual Traveler', 'Fanpage', 'Corporate', 'Online Travel Agent', 'Travel Agent') then N'Khác'





	 end



	While (@fromdate <= @todate)

	begin



		select * into #tempDate

		from #tempRentalRoom

		where dateadd(day, 1, @fromdate) >= dateadd(day, 1, ArrivalDate) and dateadd(day, 1, @fromdate) <= CheckoutDate



		select pt.MarketSegment, sum(case when pt.Breakfast = 1 then 1 else 0 end) as TotalBreakfast, sum(case when pt.Breakfast = 0 then 1 else 0 end) as TotalNoBreakfast

		into #tempBreakfastAdult

		from #tempDate pt

		left join SP2200 ptk on pt.Ma = ptk.RentalRoomId

		where ptk.Status in (0,1,2, 100) and ptk.CheckoutDate >= dateadd(day, 1, @fromdate)

		group by pt.MarketSegment



		select pt.MarketSegment, sum(case when isnull(aste.Breakfast, ptte.Breakfast) = 1 then 1 else 0 end) as TotalBreakfast, sum(case when isnull(aste.Breakfast, ptte.Breakfast) = 0 then 1 else 0 end) as TotalNoBreakfast

		into #tempBreakfastChild

		from #tempDate pt

		left join SP2500 ptte on pt.Ma = ptte.RentalRoomId

		left join Sp2401 aste on aste.ChildID = ptte.ChildID and dateadd(day, 1,aste.Date) = dateadd(day, 1, @fromdate)

		where ptte.Status in (0,1,2, 100) and ptte.CheckoutDate >= dateadd(day, 1, @fromdate)

		group by pt.MarketSegment



		insert into #tempResult values (@fromdate, N'Khách TA', 0, 0,0,0,0, 0 )

		insert into #tempResult values (@fromdate, N'Khách OTA', 0, 0,0,0,0, 0 )

		insert into #tempResult values (@fromdate, N'Khách Corp', 0, 0,0,0,0, 0 )

		insert into #tempResult values (@fromdate, N'Khách Walk-in, FIT, Fanpage', 0, 0,0,0,0, 0 )

		--insert into #tempResult values (@fromdate, N'Khác', 0, 0,0,0,0, 0 )



		update temp set temp.CheckinRoom = isnull(tds.arrNum, 0), temp.CheckoutRoom = isnull(tds.depNum, 0)

		-- , temp.InhouseRoom = isnull(tds.occNum, 0)

		, temp.DayUseRoom = isnull(tds.dayUseNum, 0)

		from #tempResult temp

		left join #tempDetailSegment tds on tds.MarketSegment = temp.Segment and tds.Date = temp.Date

		where temp.date = @fromdate



		update temp set temp.InhouseRoom = isnull(tds.occNum, 0)  - temp.CheckoutRoom + temp.DayUseRoom

		from #tempResult temp

		left join #tempDetailSegment tds on tds.MarketSegment = temp.Segment and tds.Date =dateadd(day, -1,temp.Date)

		where temp.date = @fromdate



		update temp set temp.DayUseRoom = isnull(temp.CheckinRoom, 0) + isnull(temp.InhouseRoom, 0) - temp.DayUseRoom

		from #tempResult temp

		where temp.date = @fromdate



		update temp set BreakfastGuestNum = isnull(asnl.TotalBreakfast,0) + isnull(aste.TotalBreakfast, 0), NoBreakfastGuestNum = isnull(asnl.TotalNoBreakfast ,0) + isnull(aste.TotalNoBreakfast, 0)

		from #tempResult temp

		left join #tempBreakfastAdult asnl on asnl.MarketSegment = temp.Segment

		left join #tempBreakfastChild aste on aste.MarketSegment = temp.Segment

		where Date = @fromdate



		drop table #tempDate

		drop table #tempBreakfastAdult

		drop table #tempBreakfastChild



		set @fromdate = dateadd(day, 1, @fromdate)

	end



	select * from #tempResult



	drop table #tempArrival

	drop table #tempBalance

	drop table #tempDayUse

	drop table #tempDep

	drop table #tempOCC

	drop table #tempRentalRoom

	drop table #tempResult

	drop table #tempRoomInfo

	drop table #tempDetailSegment

end
USE [ProVistaDTXHotel]
GO

/****** Object:  StoredProcedure [dbo].[sp_041]    Script Date: 8/27/2026 4:30:43 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


--exec sp_041 '20230420','20230420',0


CREATE     proc [dbo].[sp_041](@fromDate date, @toDate date, @all bit)
 as
begin
    declare @systemDate date =(select SystemDate from SP1500);

	select  RentalRoomId 
	into #temp
	from dbo.func_054(@fromDate, @toDate, '')
	inner join SP2100 pt on RentalRoomId = pt.Ma
	where ServiceId = 'RM' and IsRoomNight = 1 and Date between pt.ArrivalDate and pt.CheckoutDate and (pt.Status = 1 or (Date < @systemDate and pt.Status in (1,2))) and pt.CheckoutDate != Date 


	select distinct
	pt.BookingId,
	k.FirstName,
	dk.BookingStatus, ttdk.BookingStatusName as NameStatusBooking,
	pt.Status as StatusRentalRoom, ttpt.English as NameStatusRentalRoom,
	dk.Status ,
	dk.BookingName,dk.BookingCode as TourCode,
	dk.TravelAgency as CompanyId,
	ct.Company as CompanyName,
	dk.Booker, pt.Ma as RoomId, pt.Room as NumOfRoom,pt.ArrivalDate,
	pt.CheckoutDate as DepartureDate, pt.ActualNumOfDays,pt.RoomRateCode as RateCode,
	pt.ExtraBed as Extrabed, pt.ExtraBedRate as EBRate, pt.Rate as Rate,
	pt.Adult, pt.Child, dk.Note as NoteBooking, isnull(spr.NotePT,'') + CHAR(13) + isnull(pt.Description,'') + (case when ptk.IsMainGuest = 1 then k.Note else '' end) as NotePT,
	isnull(tt.Amount,0) as TotalServices,isnull(tt1.Amount,0) as TotalServicesBK, isnull(pm.Amount,0) as Payment, isnull(pm2.Amount,0) as PaymentBK, pt.ArrivalTime, 
	case when isnull(pt.CheckoutTime,'') ='' then '12:30' else pt.CheckoutTime end as CheckoutTime,
	dk.BookingDate as RegDay,
	case when (1 = @all and pt.Status in (0)) or  pt.ArrivalDate between @fromDate and @toDate and pt.Status = 0 then 1 else 0 end as Arrival,
	case when (1 = @all and pt.Status in (1))or  pt.CheckoutDate between @fromDate and @toDate and pt.Status in(1,0) then 1 else 0 end Departure,
	case when (1 = @all and pt.Status in (1,2,100)) or  pt.ArrivalDate between @fromDate and @toDate and pt.Status in (1,2) then 1 else 0 end CheckIn,
	case when (1 = @all and pt.Status in (2,100)) or  ( 0 = @all and pt.CheckoutDate between @fromDate and @toDate and pt.Status in (2)) then 1 else 0 end CheckOut,
	case when (1 = @all or (@fromDate = @toDate and @toDate = @systemDate)) and pt.Status in (1) then 1 else  
			case when pt.Ma in (select * from #temp) then 1 else 0 end end InHouse,
	case when (1 = @all and pt.Status in (3)) or  (hp.CancelDate between @fromDate and @toDate and pt.Status in (3)) then 1 else 0 end Cancel,
	dk.Contact, mk.MarketSegment, sc.SourceCode,
	isnull(case when pt.Status = 0 and pt.ArrivalDate = @systemDate then case when (select count(*) from SP2100 where room = pt.Room and CheckoutDate = @systemDate and Status = 1) > 0 then Replace(ttp.English, 'Vacant', 'Occupied') else ttp.English end else '' end, '') as RoomStatus

	from SP2100 pt
		left join SP2200 ptk on ptk.RentalRoomId=pt.ma
		left join SP2300 k on ptk.CustomerId = k.Id
		left join SP8055 lgt on lgt.Id = k.KindOfPaper
		join vw_001 dk on pt.BookingId = dk.ma
		left join SP1309 ttpt on ttpt.Ma = pt.Status
		left join SP1311 ttdk on ttdk.BookingStatusId = dk.BookingStatus
		left join SP1302 ct on ct.Ma = dk.TravelAgency
		left join SP8020 qt on qt.NationalityId = k.Nationality
		left join SP2107 sp on sp.RentalRoomId = pt.Ma
		left join SP1308 mk on mk.Ma = dk.MarketSegment
		left join SP8037 sc on sc.Ma = dk.SourceCode
		left join SP8052 hp on hp.Ma = pt.Ma
		left join SP1000 p on p.Ma = pt.Room
		left join SP1313 ttp on ttp.Ma = p.Status
		left join ( select RentalRoomId,STRING_AGG(SpecialRequest,',') as NotePT
					from sp2107 sp
					left join SP1325 spn on sp.Id = spn.Id
					group by RentalRoomId ) as spr on spr.RentalRoomId = pt.Ma
		left join ( SELECT Sum(Amount) as Amount,RentalRoomId2
					FROM vw_018
					where Edit = 0 and RentalRoomId2 is not null
					group by RentalRoomId2
					) as tt on tt.RentalRoomId2 = pt.Ma
		left join ( SELECT Sum(Amount) as Amount,RegisterID2
					FROM vw_018
					where Edit = 0 and RegisterID2 is not null
					group by RegisterID2
					) as tt1 on tt1.RegisterID2 = pt.BookingId
		left join (SELECT RentalRoomId2 , SUM(Amount) as Amount
					FROM vw_025
					where Edit = 0 and RentalRoomId2 is not null
					group by RentalRoomId2
					) as pm on pm.RentalRoomId2 = pt.Ma
		left join (SELECT RegisterID2 , SUM(Amount) as Amount
					FROM vw_025
					where Edit = 0 and RegisterID2 is not null
					group by RegisterID2
					) as pm2 on pm2.RegisterID2 = pt.BookingId
	where isnull(pt.Room, '') not like '0%'
	and	(ptk.Status IN (0, 1, 2,3)) AND (pt.Status IN (0,1,2,3,100))
	and (1 = @all or pt.ArrivalDate between @fromDate and @toDate or pt.CheckoutDate between @fromDate and @toDate or hp.CancelDate between @fromDate and @toDate  )
	and ptk.IsMainGuest = 1
	

	
end

GO


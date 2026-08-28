USE [ProVistaDTXHotel]
GO

/****** Object:  StoredProcedure [dbo].[sp_043]    Script Date: 8/27/2026 4:31:04 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE   proc [dbo].[sp_043](@fromDate date, @toDate date, @all bit)
 as
begin

    declare @systemDate date =(select SystemDate from SP1500);

	select RentalRoomId 
	into #temp
	from dbo.func_054(@fromDate, @toDate, '')
	inner join SP2100 pt on RentalRoomId = pt.Ma
	where ServiceId = 'RM' and IsRoomNight = 1 and Date between pt.ArrivalDate and pt.CheckoutDate and (pt.Status = 1 or (Date < @systemDate and pt.Status in (1,2))) and pt.CheckoutDate != Date 



	select 
	pt.Ma as RoomId,
	k.Id as CustomerId, 	cast(pt.BookingId as varchar) as BookingId, pt.Room as NumOfRoom,
	k.Title +' '+ k.FirstName as FullName, k.Address, k.AsmProvince, pt.ArrivalDate, pt.CheckoutDate as DepartureDate, pt.ActualNumOfDays,
	pt.Rate,	pt.RoomRateCode as RateCode,	ct.Company as TravelAgency,
	isnull(lgt.Name,'') as KindOfPaper, k.PassPort,
	k.Email, k.Phone, k.Birthday,
	k.Nationality, qt.NationalityName as FirstNameNationality,
	k.GuestStatus,tt.Vietnamese as FirstNameStatus, 
	case when (1 = @all and pt.Status in (0)) or pt.ArrivalDate between @fromDate and @toDate and pt.Status = 0 then 1 else 0 end as Arrival,
	case when (1 = @all and pt.Status in (1)) or pt.CheckoutDate between @fromDate and @toDate and pt.Status in (0,1) then 1 else 0 end Departure,
	case when (1 = @all and pt.Status in (1,2,100)) or  pt.ArrivalDate between @fromDate and @toDate and pt.Status in (1,2) then 1 else 0 end CheckIn,
	case when (1 = @all and pt.Status in (2,100)) or  ( 0 = @all and pt.CheckoutDate between @fromDate and @toDate and pt.Status in (2)) then 1 else 0 end CheckOut,
	case when (1 = @all or (@fromDate = @toDate and @toDate = @systemDate)) and pt.Status in (1) then 1 else  
			case when pt.Ma in (select * from #temp) then 1 else 0 end end InHouse,
	pt.Status as StatusRentalRoom, dk.Status as StatusBooking
	from SP2100 pt
		left join SP2200 ptk on ptk.RentalRoomId=pt.ma
		left join SP2300 k on ptk.CustomerId = k.Id
		left join SP8055 lgt on lgt.Id = k.KindOfPaper
		left join vw_001 dk on pt.BookingId = dk.ma
		left join SP1302 ct on ct.Ma = dk.TravelAgency
		left join SP1309 tt on tt.Ma = k.GuestStatus
		left join SP8020 qt on qt.NationalityId = k.Nationality
	where  isnull(pt.Room, '') not like '0%'
	and	(ptk.Status IN (0, 1, 2,100)) AND (pt.Status IN (0,1, 2, 100))
	and (1 = @all or (pt.ArrivalDate between @fromDate and @toDate) or (pt.CheckoutDate between @fromDate and @toDate) or (@fromDate between pt.ArrivalDate and pt.CheckoutDate))
	and dk.IsAvailability = 1
	union

	select 
	pt.Ma as RoomId,
	te.Id as CustomerId,cast(pt.BookingId as varchar) as BookingId, pt.Room as NumOfRoom,
	te.Title + ' ' +te.FirstName as FullName, te.Address, null, pt.ArrivalDate, pt.CheckoutDate as DepartureDate, pt.ActualNumOfDays,
	pt.Rate,	'' as RateCode,	ct.Company as TravelAgency,
	isnull(lgt.Name,'') as KindOfPaper, te.PassPort,
	te.Email, te.Phone, te.Birthday,
	te.Nationality, qt.NationalityName as FirstNameNationality,
	ptte.Status,tt.Vietnamese as FirstNameStatus,
	case when (1 = @all and pt.Status in (0))  or pt.ArrivalDate between @fromDate and @toDate and pt.Status = 0 then 1 else 0 end as Arrival,
	case when (1 = @all and pt.Status in (1)) or pt.CheckoutDate between @fromDate and @toDate and pt.Status in (0,1) then 1 else 0 end Departure,
	case when (1 = @all and pt.Status in (1,2,100)) or  pt.ArrivalDate between @fromDate and @toDate and pt.Status in (1,2) then 1 else 0 end CheckIn,
	case when (1 = @all and pt.Status in (2,100)) or  ( 0 = @all and pt.CheckoutDate between @fromDate and @toDate and pt.Status in (2)) then 1 else 0 end CheckOut,
	case when (1 = @all or (@fromDate = @toDate and @toDate = @systemDate)) and pt.Status in (1) then 1 else  
			case when pt.Ma in (select * from #temp) then 1 else 0 end end InHouse,
	pt.Status as StatusRentalRoom, dk.Status as StatusBooking
	from SP2500 ptte
		left join SP2400 te on te.Id = ptte.ChildID
		left join SP2100 pt on pt.Ma = ptte.RentalRoomId
		left join SP8055 lgt on lgt.Id = te.KindOfPaper
		left join SP8020 qt on qt.NationalityId = te.Nationality
		left join vw_001 dk on pt.BookingId = dk.ma
		left join SP1302 ct on ct.Ma = dk.TravelAgency
		left join SP1309 tt on tt.Ma = ptte.Status
	where isnull(pt.Room, '') not like '0%'
	and	(ptte.Status IN (0, 1, 2,100)) AND (pt.Status IN (0,1, 2, 100))
	and (1 = @all or (pt.ArrivalDate between @fromDate and @toDate) or (pt.CheckoutDate between @fromDate and @toDate) or (@fromDate between pt.ArrivalDate and pt.CheckoutDate))
	and dk.IsAvailability = 1 

	order by ArrivalDate




end

GO


-- exec sp_292 '20260716' 

CREATE     procedure [dbo].[sp_292]( @date datetime, @companyId int = 0, @bookingId int= 0)
as
begin
	
	declare @prefix varchar(20) = (select top 1 isnull(PrefixBookingId, '') from sp1322)
	
	;with RoomTable as (
		Select BookingId, STRING_AGG(Room, ', ') as RoomString, isnull(count(Room), 0) as RoomNo
		from SP2100
		where status in (0, 1, 2, 4, 100)
		group by BookingId
	)
	select dk.Ma, concat(dk.BookingName, case when isnull(pt.RoomNo, 0) = 0 then '' else ' - ' end, pt.RoomString) BookingName, ct.Company, dk.ArrivalDate, dateadd(day, dk.NumOfDays, dk.ArrivalDate) as DepartureDate, isnull(pt.RoomNo, 0) RoomNo
	into #tempBooking
	from SP2000 dk
	left join RoomTable pt on pt.BookingId = dk.Ma
	left join SP1302 ct on ct.Ma = dk.TravelAgency
	where
	((@date between  dk.ArrivalDate
	and dateadd(day, dk.NumOfDays, dk.ArrivalDate) 
	and status != 3)
	or (dk.Ma in (select isnull(pt.Bookingid, hd.RegisterID2) from func_054(@date, @date,'') dt 
	left join sp3000 hd on hd.Ma = dt.BillIdService
	left join SP2100 pt on hd.RentalRoomId2 = pt.Ma)))
	and (@companyId = 0 or dk.TravelAgency = @companyId)
	and (@bookingId = 0 or dk.Ma = @bookingId)

	declare @minDate date = (select min(ArrivalDate) from #tempBooking)
	declare @maxDate date = 
	(select max(Date) 
	from SP3000 hd
	where (RegisterId2 in (select Ma from #tempBooking) or RentalRoomId2 in (select Ma from SP2100 where BookingId in (select Ma from #tempBooking) and Status in (0,1,2,100))) and edit = 0
	)

	select dt.RentalRoomId, dt.ServiceId, dt.BookingId, dt.Date, dt.Total, pt.Status, isnull(hd.RegisterId2, pt.BookingId) as BookingId2
	into #tempRevenue
	From func_054(@minDate, @date,'') dt
	left join SP3000 hd on hd.Ma = dt.BillIdService
	left join SP2100 pt on pt.Ma = case when dt.ServiceId = 'RM' then dt.RentalRoomId else isnull(hd.RentalRoomId2, dt.RentalRoomId) end
	where pt.Bookingid in (select Ma from #tempBooking)
	union all
	select null, ServiceId, RegisterID2, date, amount, dk.Status , dk.ma from SP3000 hd
	left join Sp2000 dk on hd.RegisterID2 = dk.ma
	where edit = 0 and RentalRoomId1 is null and RentalRoomId2 is null and dk.Ma in (select Ma from #tempBooking)

	select isnull(dk.Ma, pt.BookingId) BookingId, sum (case when tt.PaymentMethod = 'CA' then tt.Amount else 0 end) as Cash 
	, sum (case when tt.PaymentMethod in ('BT','CD') then tt.Amount else 0 end) as BankTransfer 
	,sum (case when tt.PaymentMethod = 'AC' then tt.Amount else 0 end) as CityLedger	
	,sum (case when tt.PaymentMethod = 'HH' then tt.Amount else 0 end) as Commission
	,sum (case when tt.PaymentMethod not in ('AC','BT','CD','CA') then tt.Amount else 0 end) as Other
	--, sum(case when pt.Status = 2 then tt.Amount else 0 end) as Checkedout
	--, sum(case when pt.Status != 2 then tt.Amount else 0 end) as NotCheckedout
	into #tempPayment
	from SP3002 tt
	left join SP2000 dk on tt.RegisterID2 = dk.Ma
	left join SP2100 pt on tt.RentalRoomId2 = pt.Ma
	where edit = 0 and (RegisterID2 in (select BookingId from #tempRevenue) or RentalRoomId2 in (select RentalRoomId from #tempRevenue))
	and date <= @date
	group by isnull(dk.Ma, pt.BookingId)

	select BookingId2, sum(case when rev.ServiceId = 'RM' and rev.Date = @date then rev.Total else 0 end) as RoomToday
	, sum(case when rev.ServiceId in ('EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO') and rev.Date = @date then rev.Total else 0 end) as ExtraRoomToday
	, sum(case when rev.ServiceId in ('BD', 'BF') and rev.Date = @date then rev.Total else 0 end) as BreakfastSurchargeToday
	, sum(case when rev.ServiceId = 'LA' and rev.Date = @date then rev.Total else 0 end) as LaundryToday
	, sum(case when rev.ServiceId = 'MB' and rev.Date = @date then rev.Total else 0 end) as MinibarToday
	, sum(case when rev.ServiceId = 'BR' and rev.Date = @date then rev.Total else 0 end) as BrokenToday
	, sum(case when rev.ServiceId = 'FB' and rev.Date = @date then rev.Total else 0 end) as RestaurantToday
	, sum(case when rev.ServiceId not in ('RM', 'LA', 'FB', 'EB', 'EP', 'ER', 'KC', 'UP', 'BD', 'BF', 'EI', 'LO', 'BR', 'MB') and rev.Date = @date then rev.Total else 0 end) as OtherToday
	, sum(case when rev.Date != @date then rev.Total else 0 end) as PrevDay
	, sum(case when rev.Status = 2 then rev.Total else 0 end) as Checkedout
	, sum(case when rev.Status != 2 then rev.Total else 0 end) as NotCheckedout
	into #tempRevenueDetail
	from #tempRevenue rev
	group by BookingId2

	--select * from #tempPayment
	--select * From #tempRevenueDetail

	select dk.*, isnull(dt.RoomToday, 0) RoomToday, isnull(dt.LaundryToday, 0) LaundryToday, isnull(dt.MinibarToday, 0) MinibarToday, isnull(dt.BrokenToday, 0) BrokenToday, isnull(dt.RestaurantToday, 0) RestaurantToday, isnull(dt.ExtraRoomToday, 0) ExtraRoomToday, isnull(dt.BreakfastSurchargeToday, 0) BreakfastSurchargeToday, isnull(dt.OtherToday, 0) OtherToday, isnull(dt.RoomToday, 0) + isnull(dt.LaundryToday, 0) + isnull(dt.MinibarToday, 0) + isnull(dt.BrokenToday, 0) + isnull(dt.RestaurantToday, 0) + isnull(dt.OtherToday, 0) + isnull(dt.ExtraRoomToday, 0) + isnull(dt.BreakfastSurchargeToday, 0) TotalToday, isnull(dt.PrevDay, 0) PrevDay, isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0) TotalRevenue, isnull(tt.Cash, 0) Cash, isnull(tt.BankTransfer, 0) BankTransfer, isnull(tt.CityLedger, 0) CityLedger, isnull(tt.Commission, 0) Commission, case when isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0) - isnull(tt.Cash, 0) - isnull(tt.BankTransfer, 0) - isnull(tt.CityLedger, 0) - isnull(tt.Commission, 0) < 0 then 0 else isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0) - isnull(tt.Cash, 0) - isnull(tt.BankTransfer, 0) - isnull(tt.CityLedger, 0)  - isnull(tt.Commission, 0) end as InhouseRoom
	from #tempBooking dk
	left join #tempRevenueDetail dt on dk.Ma = dt.BookingId2
	left join #tempPayment tt on dk.Ma = tt.BookingId
	where RoomNo != 0 and isnull(dt.RoomToday, 0) + isnull(dt.LaundryToday, 0) + isnull(dt.MinibarToday, 0) + isnull(dt.BrokenToday, 0) + isnull(dt.RestaurantToday, 0) + isnull(dt.OtherToday, 0) + isnull(dt.ExtraRoomToday, 0) + isnull(dt.BreakfastSurchargeToday, 0) + isnull(dt.PrevDay, 0) != 0
	order by dk.ArrivalDate

	drop table #tempBooking
	drop table #tempRevenue
	drop table #tempPayment
	drop table #tempRevenueDetail
end
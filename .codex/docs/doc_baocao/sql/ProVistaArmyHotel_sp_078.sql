 





--exec sp_078 '2025-06-01', '2025-06-30','','','','',0

--exec sp_078_Division '0', '2024-11-04', '2024-11-04','','','','',0

--exec sp_078_Division '0','2023-02-01', '2023-02-28','','CTY0001','','',0



CREATE proc [dbo].[sp_078](

	@From date,

	@To date,

	@Area int,

	@Company varchar(50),

	@Segment int,

	@UserSale varchar(20),

	@SourceCode int = 0

)

 as

begin

	if @Area = -1 set @Area = ''



	declare @ERRevenue varchar(50) = (select Value from SP1600 where Parameter = 'RevenueER')

	--declare @ERRevenue varchar(50) = 'ER'

	declare @Revenue varchar(50) = (select Value from SP1600 where Parameter = 'Revenue')

	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	declare @division varchar(20) = (select  top 1 isnull(Division,'') from sp1322)



	declare @dbMaster varchar(50) = (select Value from SP1600 where Parameter = 'DBMaster')

	declare @Sql nvarchar(max)=''



	Create table #tableUser(

					Username varchar(20),

					FullName nvarchar(200))



	set @Sql = ' use ['+@dbMaster+']

									insert into #tableUser

									select Username, FullName from SP1321'

							exec (@Sql)



	---Tổng doanh thu phòng

	select A.*, bk.BookingName, bk.ArrivalDate, bk.ArrivalDate + bk.NumOfDays DepartureDate, bk.Salesperson, bk.MarketSegment, SP1302.Ma CompanyId, SP1302.Company, SP1000.Area, PaymentID, uscr.FullName as BookerName, us.FullName, (case when isnull(A.Promotio
n, pt.pack3) is not null and cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) > 0 and Total <> 0 then

case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.ValueType') = 'percent' then case when cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) = 100 then pt.Rate else  (Total / (100 - cast(JSON_VALUE(isnull(A.Promotion, pt.pack
3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end)) * 100)  end

else Total + cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end) end

else Total end) as OriginalAmount, isnull(bk.BookingCode, 0) BookingCode

	into #ServiceRev

	from (select * from func_054(@From, @To, '')) A 

	inner join vw_001 bk on A.BookingId = bk.Ma 

	inner join SP1302 on bk.TravelAgency = SP1302.Ma 

	inner join SP2100 pt on pt.Ma = A.RentalRoomId 

	left join SP1000 on SP1000.Ma = pt.Room

	left join SP1328 on SP1328.Id = bk.Booker

	left join Sp3000 on SP3000.Ma = A.BillIdService

	left join #tableUser us on us.Username = bk.SalesPerson

	left join #tableUser uscr on uscr.Username = bk.Username

	where (@Area = '' or SP1000.Area = @Area)

		and (@Company = '' or SP1302.ID = @Company)

		and (@Segment = '' or bk.MarketSegment = @Segment)

		and (@UserSale = '' or bk.Salesperson = @UserSale)

		and (@SourceCode = 0 or bk.SourceCode = @SourceCode)

		--and bk.IsAvailability = 1 

		--and A.ServiceId in (select Data from dbo.func_061(@Revenue, ','))

	---Tổng doanh thu nhóm

	select * into #Group from (

	select isnull(SP2000.Ma, 0) Ma, ServiceId, Amount Total, isnull(TravelAgency, SP3000.CompanyId2) CompanyId, SalesPerson, SP2000.MarketSegment, SP2000.ArrivalDate, SP2000.ArrivalDate + SP2000.NumOfDays DepartureDate, isnull(SP2000.BookingName, '') Booking
Name, SP1302.Company, PaymentID, uscr.FullName as BookerName, us.FullName, Amount OriginalAmount, isnull(BookingCode, '') BookingCode, SP3000.DepartmentId

	from SP3000 

	left join SP2000 on RegisterId2 = SP2000.Ma 

	left join SP1328 on SP1328.Id = SP2000.Booker

	left join SP1302 on isnull(Sp2000.TravelAgency, SP3000.CompanyId2) = SP1302.Ma

	left join SP8037 on SP2000.SourceCode = SP8037.Ma

	left join #tableUser us on us.Username = SP2000.SalesPerson

	left join #tableUser uscr on uscr.Username = SP2000.Username

	where ((RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null) or RentalRoomId2 in (select Ma from sp2100 where room like '0%')) and Date between @From and @To

	--and PaymentId is not null

	and Edit = 0

	--and ServiceId in (select Data from dbo.func_061(@Revenue, ','))

	and (@Company = '' or Sp1302.ID = @Company)

	and (@Segment = '' or SP2000.MarketSegment = @Segment)

	and (@UserSale = '' or SP2000.Salesperson = @UserSale)

	and (@SourceCode = 0 or SP2000.SourceCode = @SourceCode)

	) A





	select isnull(isnull(bk1.TravelAgency, bk2.TravelAgency), tt.CompanyId2) CompanyId, SP1302.Company, isnull(isnull(bk1.Ma, bk2.Ma), 0) as BookingId, isnull(isnull(bk1.ArrivalDate, bk2.ArrivalDate), null) ArrivalDate, isnull(isnull(bk1.ArrivalDate + bk1.Nu
mOfDays, bk2.ArrivalDate + bk2.NumOfDays), null) DepartureDate, isnull(isnull(bk1.BookingName, bk2.BookingName), '') BookingName, isnull(uscr.FullName, '') as BookerName, isnull(us.FullName, '') as FullName,  hddvct.DepartmentId, hddvct.ServiceId, hddvct.
Amount as Total,coalesce(bk1.BookingCode, bk2.BookingCode, '') BookingCode

	into #tmpRevenue

	from sp3001 hddvct 

	left join sp3000 hddv on hddv.ma = hddvct.BillServiceId

	left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID order by Date desc)

	left join SP2000 bk1 on hddv.RegisterID2 = bk1.Ma

	left join SP2100 pt on pt.Ma = hddv.RentalRoomId2

	left join SP2000 bk2 on pt.BookingId = bk2.Ma

	left join SP1302 on SP1302.Ma = isnull(isnull(bk1.TravelAgency, bk2.TravelAgency), tt.CompanyId2)

	left join #tableUser us on us.Username = isnull(isnull(bk1.SalesPerson, bk2.SalesPerson), '')

	left join #tableUser uscr on uscr.Username = isnull(isnull(bk1.Username, bk2.Username), '')

	where tt.Date between @From and @To and (hddv.PaymentID is null or hddv.PaymentID  in ( select PaymentID from SP3002 tt where tt.PaymentMethod not in (select Ma from SP1326 where HTMienPhi = 1) and tt.Edit=0) and hddv.Edit = 0)	

	and (@Company = '' or Sp1302.ID = @Company)

	and (@Segment = '' or isnull(isnull(bk1.MarketSegment, bk2.MarketSegment), '') = @Segment)

	and (@UserSale = '' or isnull(isnull(bk1.SalesPerson, bk2.SalesPerson), tt.Username) = @UserSale)

	and (@SourceCode = 0 or isnull(isnull(bk1.SourceCode, bk2.SourceCode), '') = @SourceCode)





	--tmp Booking

	select CompanyId, BookingId, Min(Company) Company, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName, isnull(Min(BookerName), '') BookerName,  isnull(Min(FullName), '') FullName,Min(BookingCode) as BookingCode

	into #tmpBooking

	from #ServiceRev 

	where ServiceId in (select Data from dbo.func_061(@Revenue, ','))

	group by CompanyId, BookingId

	union all

	select CompanyId, Ma BookingId, Min(Company) Company, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName, isnull(Min(BookerName), '') BookerName,  isnull(Min(FullName), '') FullName,Min(BookingCode) as BookingCod
e

	from #Group

	group by CompanyId, Ma

	union

	select CompanyId, BookingId, Min(Company) Company, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName, isnull(Min(BookerName), '') BookerName,  isnull(Min(FullName), '') FullName,Min(BookingCode) as BookingCode

	from #tmpRevenue

	group by CompanyId, BookingId



	select rev.* into #ServiceTotal 

	from  #ServiceRev rev

	inner join vw_001 bk on rev.BookingId = bk.Ma 

	where IsAvailability = 1

	

	select BookingId, CompanyId, Total , IsRoomNight, ServiceId, DepartmentId

	into #RoomRev

	from #ServiceRev

	union all 

	select Ma, CompanyId, Total, 1, ServiceId, DepartmentId from #Group

	



	select BookingId, CompanyId, COUNT(*) as Total

	into #tmpFOC

	from #ServiceTotal

	where (isnull(RoomRateCode,'') like '%FOC' or (Total = 0 and isnull(RoomRateCode,'') not like '%HU' )) and ServiceId = 'RM'

	group by BookingId, CompanyId



	select BookingId, CompanyId, sum(Total) as Total

	into #tmpRoomRevenue

	from  #RoomRev

	where ServiceId in (select value from string_split((select Value from SP1600 where Parameter = 'Revenue'), ','))--and IsRoomNight = 1

	group by BookingId, CompanyId



	select BookingId, CompanyId, sum(Total) as Total

	into #tmpFbRevenue

	from #RoomRev

	where isnull(DepartmentId,'') = 'FB'

	group by BookingId, CompanyId



	select BookingId, CompanyId, sum(Total) as Total

	into #tmpOtherRevenue

	from #RoomRev

	where ServiceId not in (select value from string_split((select Value from SP1600 where Parameter = 'Revenue'), ',')) and isnull(DepartmentId,'') != 'FB'

	group by BookingId, CompanyId



	--select * from #tmpBooking

	--select * from #tmpFbRevenue



	select min(Ma) as RentalRoomId, BookingId 

	into #tempRentalRoomBooking

	from sp2100 where bookingid in (select BookingId from #tmpBooking) and status != 3

	group by BookingId



	select count(*) as NoOfRoom, max(datediff(day, case when @From >=  isnull(pc.ArrivalDate, pt.ArrivalDate) then @From else  isnull(pc.ArrivalDate, pt.ArrivalDate) end,case when @To <  pt.CheckoutDate then dateadd(day, 1,@To) else pt.CheckoutDate end )) as
 NoOfNight ,  pt.BookingId 

	into #tempStatisticRentalRoomBooking

	from sp2100 pt

	left join sp2100 pc on pc.MoveRoom = pt.Ma

	where  pt.bookingid in (select BookingId from #tmpBooking) and  pt.status not in (3, 100)

	and (isnull(pc.ArrivalDate, pt.ArrivalDate) between @From and @To or  pt.CheckoutDate between @From and @To or ( isnull(pc.ArrivalDate, pt.ArrivalDate) < @From and  pt.CheckoutDate > @To))

	group by  pt.BookingId



	---Dịch vụ đêm phòng

	select * into #Service 

	from (select * from #ServiceTotal where ServiceId = 'RM' and IsRoomNight = 1) A





	-- Tính Số NoOfGuest

	select * into #NoGuest

	from (

		select dk.TravelAgency, sale.BookingId, (select Count(CustomerId) from SP2200 where RentalRoomId = pt.Ma and Status in (0, 1, 2, 4, 100) and (pt.ActualNumOfDays = 0 or (sale.Date > pt.ArrivalDate + pt.CheckoutDate and CheckoutDate > pt.CheckoutDate) or 
CheckoutDate > sale.Date)) NoGuest

		from #Service sale

		inner join SP2100 pt on sale.RentalRoomId = pt.Ma and sale.ServiceId = 'RM'

		inner join SP2000 dk on pt.BookingId = dk.Ma

	) A





	select A.*, @division as division

	from (

		select Dem.BookingCode,Dem.CompanyId, Min(Dem.Company) Company, case when  cast(Dem.BookingId as varchar(20)) = '0' then 'FB' else @prefix + cast(Dem.BookingId as varchar(20)) end as BookingId, Min(Dem.ArrivalDate) ArrivalDate, Min(Dem.DepartureDate) De
partureDate, Min(Dem.BookingName) BookingName,  Min(BookerName) BookerName,  Min(FullName) FullName,

		(select Count(RentalRoomId) from (select RentalRoomId from #Service where #Service.CompanyId = Dem.CompanyId and #Service.BookingId = Dem.BookingId) A) RoomNight,

		(select isnull(Sum(NoGuest), 0) from #NoGuest where TravelAgency = Dem.CompanyId and BookingId = Dem.BookingId) GuestNight,

		 (select isnull(Sum(Total), 0) from #ServiceRev where #ServiceRev.CompanyId = Dem.CompanyId and #ServiceRev.BookingId = Dem.BookingId and #ServiceRev.ServiceId = 'RM' ) + (select isnull(Sum(Total), 0) from #Group where CompanyId = Dem.CompanyId and #Gro
up.Ma = Dem.BookingId and ServiceId = 'RM' ) RoomAmount, 

		 (select isnull(Sum(OriginalAmount), 0) from #ServiceRev where #ServiceRev.CompanyId = Dem.CompanyId and #ServiceRev.BookingId = Dem.BookingId and #ServiceRev.ServiceId = 'RM' ) + (select isnull(Sum(OriginalAmount), 0) from #Group where CompanyId = Dem.
CompanyId and #Group.Ma = Dem.BookingId and ServiceId = 'RM') OriginalAmount, isnull(sum(roomRev.Total), 0) as RoomRevenue, isnull(sum(fbRev.Total), 0) as FbRevenue, isnull(sum(otherRev.Total), 0) as OtherRevenue, isnull(sum(foc.Total), 0) as FOC, lp.Shor
tName as RoomType, k.FirstName, k.Nationality, dk.BookingDate, pt.RoomRateCode, segment.Code as MarketSegment, code.Code SourceCode, ptbks.NoOfNight, ptbks.NoOfRoom

	from #tmpBooking Dem

	left join #tmpRoomRevenue roomRev on Dem.CompanyId =roomRev.CompanyId and Dem.BookingId = roomRev.BookingId

	left join #tmpFbRevenue fbRev on Dem.CompanyId = fbRev.CompanyId and Dem.BookingId = fbRev.BookingId

	left join #tmpOtherRevenue otherRev on Dem.CompanyId = otherRev.CompanyId and Dem.BookingId = otherRev.BookingId

	left join #tmpFOC foc on Dem.CompanyId =foc.CompanyId and Dem.BookingId = foc.BookingId

	left join #tempRentalRoomBooking ptbk on ptbk.BookingId = Dem.BookingId

	left join #tempStatisticRentalRoomBooking ptbks on ptbks.BookingId = Dem.BookingId

	left join SP2000 dk on dk.Ma = Dem.BookingId

	left join Sp2100 pt on pt.Ma = ptbk.RentalRoomId

	left join Sp2200 ptk on pt.Ma = ptk.RentalRoomId and ptk.IsMainGuest = 1

	left join SP2300 k on k.Id = ptk.CustomerId

	left join Sp1100 lp on pt.RoomType = lp.Ma

	left join SP1308 segment on dk.MarketSegment = segment.Ma

	left join SP8037 code on code.Ma = dk.SourceCode

	--where Dem.BookingId != 0

	group by Dem.CompanyId, Dem.BookingId, lp.ShortName, k.FirstName, k.Nationality, dk.BookingDate, pt.RoomRateCode,dem.BookingCode, segment.code, code.Code, ptbks.NoOfNight, ptbks.NoOfRoom) A 

	left join Sp1302 ct on A.CompanyId = ct.Ma

	order by (case when BookingId = 'FB' then 0 else cast( REPLACE( BookingId, @prefix, '') as int) end)







	drop table #tempRentalRoomBooking

	drop table #tableUser

	drop table #ServiceTotal

	drop table #Service

	drop table #Group

	drop table #NoGuest

	drop table #tmpRevenue

	drop table #ServiceRev

	drop table #tmpFbRevenue

	drop table #tmpFOC

	drop table #tmpOtherRevenue

	drop table #tmpRoomRevenue

	drop table #tmpBooking

	drop table #RoomRev

end



--exec "dbo"."sp_078" @From='2025-12-07',@To='2025-12-07',@Area=0,@Company='',@Segment=0,@UserSale='',@SourceCode=0

--exec "dbo"."sp_078_Division" @Division='0', @From='2025-12-07',@To='2025-12-07',@Area=0,@Company='',@Segment=0,@UserSale='',@SourceCode=0






--exec sp_078 '2023-02-01', '2023-02-28','','CTY0001','','',0

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



	---Tổng doanh thu phòng

	select A.*, bk.BookingName, bk.ArrivalDate, bk.ArrivalDate + bk.NumOfDays DepartureDate, bk.Salesperson, bk.MarketSegment, SP1302.Ma CompanyId, SP1302.Company, SP1000.Area into #ServiceRev

	from (select * from func_054(@From, @To, '')) A 

	inner join vw_001 bk on A.BookingId = bk.Ma 

	inner join SP1302 on bk.TravelAgency = SP1302.Ma 

	inner join SP2100 on SP2100.Ma = A.RentalRoomId 

	left join SP1000 on SP1000.Ma = SP2100.Room

	where (@Area = '' or SP1000.Area = @Area)

		and (@Company = '' or SP1302.ID = @Company)

		and (@Segment = '' or bk.MarketSegment = @Segment)

		and (@UserSale = '' or bk.Salesperson = @UserSale)

		and (@SourceCode = 0 or bk.SourceCode = @SourceCode)

		--and bk.IsAvailability = 1 

		and ServiceId in (select Data from dbo.func_061(@Revenue, ','))

	---Tổng doanh thu nhóm

	select * into #Group from (

	select SP2000.Ma, ServiceId, Amount Total, TravelAgency CompanyId, SalesPerson, SP2000.MarketSegment, SP2000.ArrivalDate, SP2000.ArrivalDate + SP2000.NumOfDays DepartureDate, SP2000.BookingName, SP1302.Company

	from SP3000 

	inner join SP2000 on RegisterId2 = SP2000.Ma 

	inner join SP1302 on SP2000.TravelAgency = SP1302.Ma

	left join SP8037 on SP2000.SourceCode = SP8037.Ma

	where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @From and @To

	and Edit = 0

	and ServiceId in (select Data from dbo.func_061(@Revenue, ','))

	and (@Company = '' or Sp1302.ID = @Company)

	and (@Segment = '' or SP2000.MarketSegment = @Segment)

	and (@UserSale = '' or SP2000.Salesperson = @UserSale)

	and (@SourceCode = 0 or SP2000.SourceCode = @SourceCode)

	) A





	select rev.* into #ServiceTotal 

	from  #ServiceRev rev

	inner join vw_001 bk on rev.BookingId = bk.Ma 

	and IsAvailability = 1





	---Dịch vụ đêm phòng

	select * into #Service from (select * from #ServiceTotal where ServiceId = 'RM' and IsRoomNight = 1) A





	-- Tính Số NoOfGuest

	select * into #NoGuest

	from (

		select dk.TravelAgency, sale.BookingId, (select Count(CustomerId) from SP2200 where RentalRoomId = pt.Ma and Status in (0, 1, 2, 4, 100) and (pt.ActualNumOfDays = 0 or (sale.Date > pt.ArrivalDate + pt.CheckoutDate and CheckoutDate > pt.CheckoutDate) or 
CheckoutDate > sale.Date)) NoGuest

		from #Service sale

		inner join SP2100 pt on sale.RentalRoomId = pt.Ma and sale.ServiceId = 'RM'

		inner join SP2000 dk on pt.BookingId = dk.Ma

	) A



	select *, RM + EB + ER Revenue, @division as division

	from (

		select Dem.CompanyId, Min(Dem.Company) Company, @prefix + cast(Dem.BookingId as varchar(20)) as BookingId, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName,

		(select Count(RentalRoomId) from (select RentalRoomId from #Service where #Service.CompanyId = Dem.CompanyId and #Service.BookingId = Dem.BookingId) A) RoomNight,

		(select isnull(Sum(NoGuest), 0) from #NoGuest where TravelAgency = Dem.CompanyId and BookingId = Dem.BookingId) GuestNight,

		 (select isnull(Sum(Total), 0) from #ServiceRev where #ServiceRev.CompanyId = Dem.CompanyId and #ServiceRev.BookingId = Dem.BookingId and #ServiceRev.ServiceId = 'RM') + (select isnull(Sum(Total), 0) from #Group where CompanyId = Dem.CompanyId and #Grou
p.Ma = Dem.BookingId and ServiceId = 'RM') RM, 

		 (select isnull(Sum(Total), 0) from #ServiceRev where CompanyId = Dem.CompanyId and #ServiceRev.BookingId = Dem.BookingId and ServiceId = 'EB') + (select isnull(Sum(Total), 0) from #Group where CompanyId = Dem.CompanyId and #Group.Ma = Dem.BookingId and
 ServiceId = 'EB') EB, 

		 (select isnull(Sum(Total), 0) from #ServiceRev where CompanyId = Dem.CompanyId and #ServiceRev.BookingId = Dem.BookingId and ServiceId in (select Data from dbo.func_061(@ERRevenue, ','))) + (select isnull(Sum(Total), 0) from #Group where CompanyId = De
m.CompanyId and #Group.Ma = Dem.BookingId and ServiceId in (select Data from dbo.func_061(@ERRevenue, ','))) ER

	from (select CompanyId, BookingId, Min(Company) Company, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName

			from #ServiceRev 

			where ServiceId in (select Data from dbo.func_061(@Revenue, ','))

			group by CompanyId, BookingId

			union all

			select CompanyId, Ma BookingId, Min(Company) Company, Min(ArrivalDate) ArrivalDate, Min(DepartureDate) DepartureDate, Min(BookingName) BookingName

			from #Group

			group by CompanyId, Ma

		) Dem

	group by Dem.CompanyId, Dem.BookingId) A 

	order by ArrivalDate,RoomNight











	drop table #ServiceTotal

	drop table #Service

	drop table #Group

end




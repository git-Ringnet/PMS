CREATE     procedure [dbo].[sp_279] (@date datetime)

as

begin

	declare @beginDate datetime = (select DATEFROMPARTS(year(@date), month(@date), 1))

	declare @loopDate datetime = @beginDate;

	declare @FOCMonth int = 0, @FOC int = 0, @Checkin int = 0, @Checkout int = 0, @Inhouse decimal(15,2) = 0, @HouseUse int = 0, @InhouseDaily decimal(15,2) = 0 

	declare @RoomAvailable decimal(15,2) = (dbo.func_002(@date,@date) - (select Sum(Quantity) from dbo.func_003 (@date,@date)))

	declare @averageRoomRateIncludedOthersRoomRevenue char(1) = (select isnull(Value, 0) from SP1600 where Parameter = 'AverageRoomRateIncludedOthersRoomRevenue')	

	declare @SystemDate date = (select SystemDate from SP1500 );

	declare @Revenue varchar(50) = (select Value from SP1600 where Parameter = 'Revenue')



	create table #tmpDaily (

		Date date,

		Segment varchar(100),

		CheckinRoom int,

		CheckoutRoom int,

		InhouseRoom int,

		DayUseRoom int,

		BreakfastGuestNum int,

		NoBreakfastGuestNum int

	)



	print(@RoomAvailable)                       



	select * into #tempSetup

	from SP1610 where Report = 'NightAuditReport'



	select * into #tempRev

	from SP3000 where edit = 0 and Date between @beginDate and @date 



	select * into #temp

	from func_054 (@beginDate, @date,'')



	insert into #tmpDaily

	exec sp_275 @date, @date



	select #temp.* into #tempFOC

	from #temp

	left join vw_001 on vw_001.ma=#temp.BookingId

	where IsAvailability = 1 and ServiceId = 'RM' 

			and IsRoomNight = 1 and (RoomRateCode ='FOC' or (isnull(RoomRateCode,'') != 'HU' and Total = 0))



	--select * from #tempFOC



	select @Checkin = count(*) 

	from SP2100 pt

		left join vw_001 dk on pt.BookingId = dk.Ma

		where pt.ActualArrivalDate = @date and dk.IsAvailability = 1 

		and pt.Status in (0, 1, 2) and isnull(Room,'') not like '0%'



	select @Checkout = count(*)

	from SP2100 pt

	left join vw_001 dk on pt.BookingId = dk.Ma

	where pt.CheckoutDate = @date

		and pt.Status in(0,1,2)

		and (pt.Room is null or pt.Room not like '0%%')  

		and dk.IsAvailability = 1



	select @Inhouse = Count(*) 

	from (

		select RentalRoomId

		from (select * from #temp where date = @date

		) A inner join SP2100 on SP2100.Ma = A.RentalRoomId	

		left join vw_001 dk on SP2100.BookingId = dk.Ma

		where ServiceId = 'RM' and IsRoomNight = 1 and dk.IsAvailability = 1

	) A 



	select @InhouseDaily = sum(InhouseRoom) from #tmpDaily



	select @HouseUse = count(*) 

	from #temp

	left join vw_001 on vw_001.ma=#temp.BookingId

	where IsAvailability = 1 and ServiceId = 'RM' 

			and IsRoomNight = 1 and RoomRateCode ='HU' and Date = @date





	select isnull(rm1.Date, hddv.Date) as Date, isnull((rm1.Total), 0) + isnull(hddv.Amount, 0) as RM into #tempRoomRevenue 

	from (select #temp.Date, isnull(sum(#temp.Total), 0) as Total from #temp

	left join SP2100 pt on pt.Ma = #temp.RentalRoomId

	where ((@averageRoomRateIncludedOthersRoomRevenue = '1' and #temp.ServiceId in (select value from string_split(@Revenue, ',')))

	or (@averageRoomRateIncludedOthersRoomRevenue != '1' and #temp.ServiceId = 'RM')) and (pt.DayUse is null or pt.DayUse = 0) and Date = @date

	group by #temp.Date) rm1

	full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma

			where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date = @date and Edit = 0

			and ((@averageRoomRateIncludedOthersRoomRevenue = '1' and ServiceId in (select value from string_split(@Revenue, ',')))

			or (@averageRoomRateIncludedOthersRoomRevenue != '1' and ServiceId = 'RM'))

			group by Date) hddv on hddv.Date = rm1.Date			



	update #tempRoomRevenue  set #tempRoomRevenue.RM = #tempRoomRevenue.RM - rev.RM

	from 

	#tempRoomRevenue inner join 

	(select #temp.Date, isnull(sum(#temp.Total), 0) as RM 

	from #temp left join vw_001 dk on dk.Ma=#temp.BookingId

	left join SP2100 pt on pt.Ma = #temp.RentalRoomId

	where ((@averageRoomRateIncludedOthersRoomRevenue = '1' and #temp.ServiceId in (select value from string_split(@Revenue, ',')))

	or (@averageRoomRateIncludedOthersRoomRevenue != '1' and #temp.ServiceId = 'RM')) and dk.IsAvailability =0 and (pt.DayUse is null or pt.DayUse = 0)

	group by #temp.Date) rev on #tempRoomRevenue.Date = rev.Date 

	where #tempRoomRevenue.Date >= @SystemDate



	while (@loopDate <= @date)

	begin

		set @FOCMonth = @FOCMonth + (select count(*) from #tempFOC where @loopDate = Date)



		if(@loopDate = @date)

			set @FOC = (select count(*) from #tempFOC where @loopDate = Date)



		set @loopDate = dateadd(day, 1, @loopDate)

	end



	create table #TempResult(

		STT varchar(10),

		Content nvarchar(100),

		[Date] money,

		[Month] money,

		IsBold bit

	)



	insert into #TempResult values ('1-1', N'Tổng doanh thu',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId not in (select value from string_split((select string_agg(service, ',') from #tempSetup where DisplayName in ('FbRevenue
','ConferenceRevenue','TransportationRevenue')), ','))),(select isnull(sum(Amount), 0) from #tempRev where ServiceId not in (select value from string_split((select string_agg(service, ',') from #tempSetup where DisplayName in ('FbRevenue','ConferenceReven
ue','TransportationRevenue')), ','))), 1)



	insert into #TempResult values ('1-2', N'Doanh thu phòng',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'RoomRevenue'),','))),(select 
isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'RoomRevenue'),','))), 0)



	--insert into #TempResult values ('1-3', N'Doanh thu nhà hàng',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'FbRevenue'),','))),(sele
ct isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'FbRevenue'),','))), 0)



	--insert into #TempResult values ('1-4', N'Doanh thu hội nghị',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'ConferenceRevenue'),',')
)),(select isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'ConferenceRevenue'),','))), 0) 



	insert into #TempResult values ('1-5', N'Doanh thu Minibar',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'MinibarRevenue'),','))),(se
lect isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'MinibarRevenue'),','))), 0)



	--insert into #TempResult values ('1-6', N'Doanh thu VPTH',0,0,0)



	insert into #TempResult values ('1-7', N'Doanh thu giặt ủi',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'LaundryRevenue'),','))),(se
lect isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'LaundryRevenue'),','))), 0)

	

	--insert into #TempResult values ('1-8', N'Doanh thu shop',0,0,0)



	--insert into #TempResult values ('1-9', N'Doanh thu vận chuyển',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'TransportationRevenue'
),','))),(select isnull(sum(Amount), 0) from #tempRev where ServiceId in (select value from string_split((select [Service] from #tempSetup where DisplayName = 'TransportationRevenue'),','))), 0)



	insert into #TempResult values ('1-10', N'Doanh thu dịch vụ khác',(select isnull(sum(Amount),0) from #tempRev where Date = @date and ServiceId in (select Ma from sp1306 where ma not in(select value from string_split((select string_agg(service, ',') from 
#tempSetup ), ',')))),(select isnull(sum(Amount), 0) from #tempRev where ServiceId in (select Ma from sp1306 where ma not in(select value from string_split((select string_agg(service, ',') from #tempSetup), ',')))), 0)



	insert into #TempResult values ('1-11', N'FOC',@FOC,@FOCMonth,0)



	insert into #TempResult values ('2-1', N'Hoạt động khách sạn',null,null,1)



	insert into #TempResult values ('2-2', N'Số phòng In house',@InhouseDaily,null,0)



	insert into #TempResult values ('2-3', N'Check in',@Checkin,null,0)



	insert into #TempResult values ('2-4', N'Check out',@Checkout,null,0)

	

	insert into #TempResult values ('2-5', N'Số phòng ở cuối ngày',@Inhouse,null,0)

	

	insert into #TempResult values ('3-1', N'Công suất phòng (OCC)/%',case when @RoomAvailable = 0 then 0 else @Inhouse/@RoomAvailable*100 end,null,1)



	insert into #TempResult values ('4-1', N'Giá phòng bình quân (ADR)',round(case when  @Inhouse = 0 then 0 else(select RM from #tempRoomRevenue where Date = @date)/@Inhouse end, 0),null,1)



	insert into #TempResult values ('5-1', N'Ý kiến khách hàng',null,null,1)

	

	insert into #TempResult values ('6-1', N'Tình trạng cơ sở vật chất',null,null,1)

	

	insert into #TempResult values ('6-2', N'Tình trạng cơ sở vật chất',1,null,1)



	insert into #TempResult values ('7-1', N'Đề xuất',null,null,1)



	select * from #TempResult



	drop table #tempRev

	drop table #TempResult

	drop table #tempSetup

	drop table #tempFOC

	drop table #temp

	drop table #tempRoomRevenue

	drop table #tmpDaily

end



--exec sp_279 '20250429'

                         

-- select * from sp1610




CREATE   function [dbo].[func_store148](

	@From date,

	@To date

)

returns @re table(

		Date date,

		CompanyId varchar(20),

		Company nvarchar(500),

		MarketSegment int,

		SourceCode int,

		UserSale varchar(20),

		Area int,

		NumOfRoom int,

		RoomNight int,

		HU int,

		FOC int,

		GuestNight int,

		RM decimal(18,4),

		BF decimal(18,4),

		RMWithoutTax decimal(18,4),

		EB decimal(18,4),

		ER decimal(18,4),

		ERWithoutTax decimal(18,4),

		FBRevenue decimal(18,4),

		OthersRevenue decimal(18,4),

		NumOdGuest int,			

		TotalByDate decimal(18,4),

		OriginalTotal decimal(18,4),

		ARR decimal(18,4),

		ARROriginal decimal(18,4),

		Revenue decimal(18,4),

		PerRoomNight decimal(18,4),

		PerRevenue decimal(18,4),

		AVGRevenue decimal(18,4),

		RAV int,

		RAV3 int

)

 as

begin

	declare @ERRevenue varchar(50) = (select Value from SP1600 where Parameter = 'RevenueER')

	declare @Revenue varchar(50) = (select Value from SP1600 where Parameter = 'Revenue')

	declare @FOCBaoGom varchar(5) = (select Value from SP1600 where Parameter = 'FOCBaoGomCompliment')

	declare @Quantity decimal(18,2), @ArrivalDate as date, @DepartureDate as date, @OpenTimeOOO time



	set @OpenTimeOOO = (select cast(Value as time) from SP1600 where Parameter = 'FrmOOO_DefineLockByTime')

	declare @averageRoomRateIncludedOthersRoomRevenue char(1) = (select isnull(Value, 0) from SP1600 where Parameter = 'AverageRoomRateIncludedOthersRoomRevenue')



	--Kiểm tra ngày hoạt động

	declare @operationDate varchar(15) = (select Value from SP1600 where Parameter = 'operationdate')

	if(isnull(@operationDate,'')!='' )

	begin

			declare @date date = cast(@operationDate as date) 

			if(@From < @date)

				set @From = @date

	end



	declare  @temp TABLE

	(

		[Name] nvarchar(50),

		[Date] date,

		[func_002] int,

		[RoomOOO] int,

		[RAV] int

	)



	declare  @ServiceTotal TABLE

	(

		RentalRoomId varchar(10),

		BookingId bigint,

		BillServiceId bigint,

		ServiceId char(2),

		RoomRateCode varchar(20),

		Date date,

		Quantity float,

		Total decimal(18,2),

		IsRoom smallint,

		DetailId int, 

		DepartmentId varchar(5),

		IsRoomNight smallint,

		VoucherCode varchar(30),

		Promotion varchar(300),

		Salesperson varchar(20),

		MarketSegment int,

		SourceCode int,

		CompanyId varchar(20),

		Company nvarchar(500),

		Area int, 

		IsAvailability bit,

		PaymentId bigint,

		TaxAmount decimal(18,4),

		OriginalAmount decimal(18,4)

	)



	declare  @ServicePaid TABLE

	(

		Date date,

		DepartmentId char(2),

		ServiceId char(2),

		TaxAmount decimal(18,4),

		Total decimal(18,4),

		Salesperson varchar(50),

		MarketSegment int,

		SourceCode int,

		CompanyId varchar(20),

		Company nvarchar(500),

		Area int,

		IsAvailability bit

	)



	declare @HU table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)



	declare @FOC table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)

	declare @RM table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4),

		TaxAmount decimal(18,4)

	)



	declare @BF table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4)

	)



	declare @RMDate table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		TotalByDate decimal(18,4),

		OriginalTotal decimal(18,4)

	)



	declare @EB table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4)

	)



	declare @FB table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4)

	)



	declare @Other table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4)

	)



	declare @ER table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total decimal(18,4),

		TaxAmount decimal(18,4)

	)

	declare @RN table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)

	declare @NOR table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)

	declare @GN table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)

	declare @NOG table

	(

		Date date,

		CompanyId varchar(20),

		MarketSegment int,

		SourceCode int,

		Salesperson varchar(20),

		Area int,

		Total int

	)



	declare @rev table

	(

		Date date,

		CompanyId varchar(20),

		Company nvarchar(500),

		MarketSegment int,

		SourceCode int,

		UserSale varchar(20),

		Area int,

		NumOfRoom int,

		RoomNight int,

		HU int,

		FOC int,

		GuestNight int,

		RM decimal(18,4),

		BF decimal(18,4),

		RMWithoutTax decimal(18,4),

		EB decimal(18,4),

		ER decimal(18,4),

		ERWithoutTax decimal(18,4),

		FBRevenue decimal(18,4),

		OthersRevenue decimal(18,4),

		NumOdGuest int,	

		TotalByDate decimal(18,4),

		OriginalTotal decimal(18,4)

	)

	--Total Room

	--dựng ngày ảo

	insert into @temp(Name,Date,func_002,RoomOOO, RAV)

	select top (DATEDIFF(day, @From,@To)+1) 'Total Room',

	DATEADD(day,row_number() over (order by a.object_id) -1, @From),0, 0, 0

	from sys.all_objects a cross join sys.all_objects b;

	





	update @temp set RoomOOO = (select sum(Quantity) from dbo.func_003 (Date,Date)) 



	update @temp set func_002 = dbo.func_002(Date,Date)



	update @temp set RAV = func_002 - RoomOOO





	---Tổng doanh thu

	

	insert into @ServiceTotal

	select A.*, isnull(bk.Salesperson, '') Salesperson, isnull(bk.MarketSegment, '') MarketSegment,isnull(bk.SourceCode, '') SourceCode, SP1302.Id CompanyId, SP1302.Company, isnull(SP1000.Area, 1) Area ,IsAvailability, SP3000.PaymentID, ta.TaxAmount, (case w
hen isnull(A.Promotion, pt.pack3) is not null and cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) > 0 and Total <> 0 then

case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.ValueType') = 'percent' then case when cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) = 100 then pt.Rate else  (Total / (100 - cast(JSON_VALUE(isnull(A.Promotion, pt.pack
3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end)) * 100)  end

else Total + cast(JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(A.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end) end

else Total end) as OriginalAmount 

	from func_054(@From, @To, '') A 

		inner join vw_001 bk on A.BookingId = bk.Ma 

		inner join SP1302 on bk.TravelAgency = SP1302.Ma 

		inner join SP2100 pt on pt.Ma = A.RentalRoomId 

		left join SP1000 on SP1000.Ma = pt.Room 

		left join Sp3000 on SP3000.Ma = A.BillIdService

		left join (select BillServiceId, Sum(TaxAmount) TaxAmount from sp3001 group by BillServiceId) as ta on ta.BillServiceId = SP3000.Ma

	--where --bk.IsAvailability = 1 and 

		  --A.ServiceId in(select Data from dbo.func_061(@Revenue, ',')) 

	union all

	-----Tổng doanh thu

	select '' as RentalRoomId, RegisterId2 , SP3000.Ma as BillIdService, ServiceId, null as RoomRateCode, 

			Date, 0 as Quantity,SUM(Amount) Total, null as IsRoom, null as DetailId, DepartmentId,

			null as IsRoomNight, '' as VoucherCode, '' as Promotion, isnull(SalesPerson, '') Salesperson, isnull(SP2000.MarketSegment, '') MarketSegment,

			SP2000.SourceCode, Sp1302.Id CompanyId, SP1302.Company , 1 as Area, IsAvailability, SP3000.PaymentID, ta.TaxAmount, sum(Amount) as OriginalAmount

	from SP3000 

	left join (select BillServiceId, Sum(TaxAmount) TaxAmount from sp3001 group by BillServiceId) as ta on ta.BillServiceId = SP3000.Ma

	inner join SP2000 on RegisterId2 = SP2000.Ma

	inner join (select Ma, IsAvailability from vw_001) bk on RegisterId2 = bk.Ma 

	inner join SP1302 on SP1302.Ma = SP2000.TravelAgency

	where RentalRoomId1 is null 

	and RentalRoomId2 is null 

	and RegisterId2 is not null 

	and Edit = 0

	and Date between @From and @To

	--and (ServiceId in(select Data from dbo.func_061(@Revenue, ',')) or ServiceId in (select Data from dbo.func_061(@ERRevenue, ','))) 

	group by RegisterId2, Date, SP3000.Ma,ServiceId, TravelAgency, Salesperson, SP2000.MarketSegment, SP2000.SourceCode,

				DepartmentId, SP1302.Company, IsAvailability, SP3000.PaymentID, ta.TaxAmount, SP1302.Id



					

	insert into @ServicePaid

	select tt.Date, hddvct.DepartmentId, hddvct.ServiceId, hddvct.TaxAmount, hddvct.Amount as Total, 	isnull(isnull(bk1.SalesPerson, bk2.SalesPerson), tt.Username) Salesperson, isnull(isnull(bk1.MarketSegment, isnull(bk2.MarketSegment, SP1302.MarketSegment))
, '') MarketSegment,

			isnull(isnull(bk1.SourceCode,  isnull(bk2.SourceCode, SP1302.SourceCode)), '') SourceCode, sp1302.Id CompanyId, SP1302.Company , 1 as Area, isnull(A.IsAvailability, 0) as IsAvailability

	from sp3001 hddvct 

	left join sp3000 hddv on hddv.ma = hddvct.BillServiceId

	left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID order by Date desc)

	left join SP2000 bk1 on hddv.RegisterID2 = bk1.Ma

	left join SP2100 pt on pt.Ma = hddv.RentalRoomId2

	left join SP2000 bk2 on pt.BookingId = bk2.Ma

	left join SP1302 on SP1302.Ma = isnull(isnull(bk1.TravelAgency, bk2.TravelAgency), tt.CompanyId2)

	left join vw_001 A on A.Ma = isnull(bk1.Ma, bk2.Ma)

	where tt.Date between @From and @To and (hddv.PaymentID is null or hddv.PaymentID  in ( select PaymentID from SP3002 tt where tt.PaymentMethod not in (select Ma from SP1326 where HTMienPhi = 1) and tt.Edit=0) and hddv.Edit = 0)





	--HU

	insert into @HU

	select Date, CompanyId, MarketSegment, SourceCode, Salesperson, Area, COUNT(*) as Total

	from @ServiceTotal

	where isnull(RoomRateCode,'') = 'HU' and IsAvailability = 1

	group by Date, CompanyId, Company, MarketSegment, SourceCode,

	Salesperson, Area



	--FOC

	insert into @FOC

	select Date, CompanyId, MarketSegment, SourceCode, Salesperson, Area, COUNT(*) as Total

	from @ServiceTotal

	where (isnull(RoomRateCode,'') like '%FOC' or (Total = 0 and isnull(RoomRateCode,'') not like '%HU' )) and IsAvailability = 1 and ServiceId = 'RM'

	group by Date, CompanyId, Company, MarketSegment, SourceCode,

	Salesperson, Area



	--RM

	insert into @RM

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total, Sum(TaxAmount) as TaxAmount

	from @ServiceTotal

	where isnull(ServiceId,'') IN (select value from string_split((select Value from SP1600 where Parameter = 'Revenue'), ','))--in ('RM','EB','EP','EI','ER','LO','UP','BF','BC','BD') -- and IsRoomNight = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	insert into @BF

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServiceTotal

	where  ServiceId in ('BF') and DepartmentId = 'FO' and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	insert into @RMDate

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as TotalByDate, sum(OriginalAmount) as OriginalAmount

	from @ServiceTotal

	where isnull(ServiceId,'') = 'RM' and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	update @RMDate set TotalByDate = rm.TotalByDate - rev.Total

	from @RMDate as rm inner join (select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServiceTotal

	where isnull(ServiceId,'') = 'RM' and IsAvailability = 0

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area) rev on rev.Date = rm.Date and rev.CompanyId = rm.CompanyId and rev.MarketSegment = rm.MarketSegment and rev.SourceCode = rm.SourceCode and rev.Salesperson = rm.Salesperson and rev.Area = rm.Area

	where rm.Date >= (select SystemDate from sp1500)



	--update @RM set Total = rm.Total - rev.Total, TaxAmount = rm.TaxAmount - rev.TaxAmount

	--from @RM as rm inner join (select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total, Sum(TaxAmount) as TaxAmount

	--from @ServicePaid

	--where isnull(ServiceId,'') in ('RM', 'BF') and IsAvailability = 0

	--group by Date, CompanyId, MarketSegment, SourceCode,

	--Salesperson, Area) rev on rev.Date = rm.Date and rev.CompanyId = rm.CompanyId and rev.MarketSegment = rm.MarketSegment and rev.SourceCode = rm.SourceCode and rev.Salesperson = rm.Salesperson and rev.Area = rm.Area

	--where rm.Date >= (select SystemDate from sp1500)



	--ER

	--insert into @ER

	--select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total, Sum(TaxAmount) as TaxAmount

	--from @ServicePaid

	--where isnull(ServiceId,'') in (select value from string_split(@ERRevenue,','))

	--group by Date, CompanyId, Company, MarketSegment, SourceCode,

	--Salesperson, Area



	--update @ER set Total = er.Total - rev.Total, TaxAmount = er.TaxAmount - rev.TaxAmount

	--from @ER as er inner join (select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total, Sum(TaxAmount) as TaxAmount

	--from @ServicePaid

	--where isnull(ServiceId,'') in (select value from string_split(@ERRevenue,',')) and IsAvailability = 0

	--group by Date, CompanyId, Company, MarketSegment, SourceCode,

	--Salesperson, Area) rev on rev.Date = er.Date and rev.CompanyId = er.CompanyId and rev.MarketSegment = er.MarketSegment and rev.SourceCode = er.SourceCode and rev.Salesperson = er.Salesperson and rev.Area = er.Area

	--where er.Date >= (select SystemDate from sp1500)



	--EB

	insert into @EB

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServiceTotal

	where isnull(ServiceId,'') = 'EB' and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	--FB

	insert into @FB

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServicePaid

	where isnull(DepartmentId,'') = 'FB' and IsAvailability = 0

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area

	union

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServiceTotal

	where isnull(DepartmentId,'') = 'FB' -- and IsAvailability = 0

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	--Others

	insert into @Other

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, SUM(Total) as Total

	from @ServiceTotal

	where ServiceId not in (select value from string_split((select Value from SP1600 where Parameter = 'Revenue'), ',')) and isnull(DepartmentId,'') != 'FB' and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	--Room Night

	insert into @RN

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, count(*) as Total

	from @ServiceTotal

	where isnull(ServiceId,'') = 'RM' and isnull(IsRoomNight, 0) = 1 and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	--No of Room

	insert into @NOR

	select Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area,count(distinct RentalRoomId) as Total

	from (select distinct Max(Date) as Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, RentalRoomId

		  from @ServiceTotal

		  where isnull(ServiceId,'') = 'RM' and IsRoomNight = 1 and IsAvailability = 1

		  group by CompanyId, MarketSegment, SourceCode,

		  Salesperson, Area, RentalRoomId

		  ) A 

	left join SP2100 pt on pt.Ma = A.RentalRoomId

	group by A.Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area



	--Guest Night

	insert into @GN

	select Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area, SUM(ptk.Adult) as Total

	from @ServiceTotal sale

	inner join (select RentalRoomId, count(*) as Adult from sp2200 group by RentalRoomId) ptk on ptk.RentalRoomId = sale.RentalRoomId

	where ServiceId = 'RM' and IsRoomNight = 1 and IsAvailability = 1

	group by Date, CompanyId, MarketSegment, SourceCode,

	Salesperson, Area



	--No of Guest

	insert into @NOG

	Select Max(Date) as Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area,SUM(ptk.Adult) as Total

	from (select distinct Max(Date) as Date, CompanyId, MarketSegment, SourceCode,Salesperson, Area, RentalRoomId

		  from @ServiceTotal

		  where isnull(ServiceId,'') = 'RM' and IsRoomNight = 1 and IsAvailability = 1

		  group by CompanyId, MarketSegment, SourceCode,

		  Salesperson, Area, RentalRoomId

		  ) A 

	inner join (select RentalRoomId, count(*) as Adult from sp2200 group by RentalRoomId) ptk on ptk.RentalRoomId = A.RentalRoomId

	group by CompanyId, MarketSegment, SourceCode,Salesperson, Area



	--sp_148 '2023/07/01','2023/07/01',1



	insert into @rev

	select total.Date, total.CompanyId, total.Company, total.MarketSegment, total.SourceCode,

		total.Salesperson as UserSale, total.Area, 0 as NumOfRoom,

		0 as RoomNight, 0 as HU, 0 as FOC,

		0 as GuestNight, cast(0 as decimal) as RM, cast(0 as decimal) as BF, cast(0 as decimal) as RMNoVAT, cast(0 as decimal) as EB, cast(0 as decimal) as ER, cast(0 as decimal) as ERNoVAT, cast(0 as decimal) as FBRevenue, cast(0 as decimal) as OthersRevenue,



		0 as NumOdGuest, 0, 0

	from @ServiceTotal total

	group by total.Date, total.CompanyId, total.Company, total.MarketSegment, total.SourceCode,

			total.Salesperson, total.Area

	union

	select total.Date, total.CompanyId, total.Company, total.MarketSegment, total.SourceCode,

		total.Salesperson as UserSale, total.Area, 0 as NumOfRoom,

		0 as RoomNight, 0 as HU, 0 as FOC,

		0 as GuestNight, cast(0 as decimal) as RM, cast(0 as decimal) as BF, cast(0 as decimal) as RMNoVAT, cast(0 as decimal) as EB, cast(0 as decimal) as ER, cast(0 as decimal) as ERNoVAT, cast(0 as decimal) as FBRevenue, cast(0 as decimal) as OthersRevenue,



		0 as NumOdGuest, 0, 0

	from @ServicePaid total

	group by total.Date, total.CompanyId, total.Company, total.MarketSegment, total.SourceCode,

			total.Salesperson, total.Area





	update @rev set RoomNight = rn.Total

	from @RN rn

	left join @rev rev

	on rn.Date = rev.Date and

		  rn.CompanyId = rev.CompanyId and

		  rn.MarketSegment = rev.MarketSegment and

		  rn.SourceCode = rev.SourceCode and

		  rn.Salesperson = rev.UserSale and

		  rn.Area = rev.Area

	

	update @rev set NumOfRoom = nor.Total

	from @NOR nor

	left join @rev rev

	on nor.Date = rev.Date and

		  nor.CompanyId = rev.CompanyId and

		  nor.MarketSegment = rev.MarketSegment and

		  nor.SourceCode = rev.SourceCode and

		  nor.Salesperson = rev.UserSale and

		  nor.Area = rev.Area



	update @rev set HU = hu.Total

	from @HU hu

	left join @rev rev

	on hu.Date = rev.Date and

		  hu.CompanyId = rev.CompanyId and

		  hu.MarketSegment = rev.MarketSegment and

		  hu.SourceCode = rev.SourceCode and

		  hu.Salesperson = rev.UserSale and

		  hu.Area = rev.Area



	update @rev set FOC = foc.Total

	from @FOC foc

	left join @rev rev

	on foc.Date = rev.Date and

		  foc.CompanyId = rev.CompanyId and

		  foc.MarketSegment = rev.MarketSegment and

		  foc.SourceCode = rev.SourceCode and

		  foc.Salesperson = rev.UserSale and

		  foc.Area = rev.Area

	

	update @rev set GuestNight = gn.Total

	from @GN gn

	left join @rev rev

	on gn.Date = rev.Date and

		  gn.CompanyId = rev.CompanyId and

		  gn.MarketSegment = rev.MarketSegment and

		  gn.SourceCode = rev.SourceCode and

		  gn.Salesperson = rev.UserSale and

		  gn.Area = rev.Area



	update @rev set RM = rm.Total, RMWithoutTax = rm.Total - rm.TaxAmount

	from @RM rm

	left join @rev rev

	on rm.Date = rev.Date and

		  rm.CompanyId = rev.CompanyId and

		  rm.MarketSegment = rev.MarketSegment and

		  rm.SourceCode = rev.SourceCode and

		  rm.Salesperson = rev.UserSale and

		  rm.Area = rev.Area



    update @rev set BF = bf.Total

	from @BF bf

	left join @rev rev

	on bf.Date = rev.Date and

		  bf.CompanyId = rev.CompanyId and

		  bf.MarketSegment = rev.MarketSegment and

		  bf.SourceCode = rev.SourceCode and

		  bf.Salesperson = rev.UserSale and

		  bf.Area = rev.Area



	update @rev set TotalByDate = rm.TotalByDate, OriginalTotal = rm.OriginalTotal

	from @RMDate rm

	left join @rev rev

	on rm.Date = rev.Date and

		  rm.CompanyId = rev.CompanyId and

		  rm.MarketSegment = rev.MarketSegment and

		  rm.SourceCode = rev.SourceCode and

		  rm.Salesperson = rev.UserSale and

		  rm.Area = rev.Area



	update @rev set EB = eb.Total

	from @EB eb

	left join @rev rev

	on eb.Date = rev.Date and

		  eb.CompanyId = rev.CompanyId and

		  eb.MarketSegment = rev.MarketSegment and

		  eb.SourceCode = rev.SourceCode and

		  eb.Salesperson = rev.UserSale and

		  eb.Area = rev.Area



	update @rev set FBRevenue = fb.Total

	from @FB fb

	left join @rev rev

	on fb.Date = rev.Date and

		  fb.CompanyId = rev.CompanyId and

		  fb.MarketSegment = rev.MarketSegment and

		  fb.SourceCode = rev.SourceCode and

		  fb.Salesperson = rev.UserSale and

		  fb.Area = rev.Area



	update @rev set OthersRevenue = ot.Total

	from @Other ot

	left join @rev rev

	on ot.Date = rev.Date and

		  ot.CompanyId = rev.CompanyId and

		  ot.MarketSegment = rev.MarketSegment and

		  ot.SourceCode = rev.SourceCode and

		  ot.Salesperson = rev.UserSale and

		  ot.Area = rev.Area



	update @rev set ER = er.Total, ERWithoutTax = er.Total - er.TaxAmount

	from @ER er

	left join @rev rev

	ON er.Date = rev.Date and

		  er.CompanyId = rev.CompanyId and

		  er.MarketSegment = rev.MarketSegment and

		  er.SourceCode = rev.SourceCode and

		  er.Salesperson = rev.UserSale and

		  er.Area = rev.Area



	update @rev set NumOdGuest = nog.Total

	from @NOG nog

	left join @rev rev

	on nog.Date = rev.Date and

		  nog.CompanyId = rev.CompanyId and

		  nog.MarketSegment = rev.MarketSegment and

		  nog.SourceCode = rev.SourceCode and

		  nog.Salesperson = rev.UserSale and

		  nog.Area = rev.Area





		--select * from #Revenue



	

	declare @sumRAV decimal(18,4) = isnull((select sum(RAV) from (select distinct Date, RAV from @temp)a),0)

	declare @sumRV decimal(18,4) = isnull((select sum(RM + ER) from @rev),0)



		insert into @re

		select *,TotalByDate / (case when RoomNight = 0 then 1 else RoomNight end)  ARR, OriginalTotal / (case when RoomNight - HU - FOC = 0 then 1 else RoomNight - HU - FOC end), RM Revenue, 

		cast((RoomNight/ (case when @sumRAV = 0 then 1 else @sumRAV end))*100.00 as decimal(18,4)) as PerRoomNight, 

		cast(((RM + ER)/(case when @sumRV = 0 then 1 else @sumRV end))*100 as decimal(18,4)) as PerRevenue, 

		 case when (RoomNight - HU) != 0 then (case when @averageRoomRateIncludedOthersRoomRevenue = '1' then (RM + ER) else RM end) / (RoomNight - HU) else 0 end AVGRevenue, 

		 (select Sum(RAV) from @temp where Date = rev.Date) RAV, @sumRAV RAV3

		from @rev rev

		order by CompanyId



		return

		 



	--drop table @ServiceTotal

	--drop table #Revenue

	--drop table #temp

	--drop table #RN

	--drop table #RM

	--drop table #EB

	--drop table #ER

	--drop table #HU

	--drop table #FOC

	--drop table #Sale

end




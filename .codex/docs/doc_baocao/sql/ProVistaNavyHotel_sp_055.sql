Changed database context to 'ProVistaNavyHotel'.

--exec sp_055 '20241101', '20241115','','CTY0001','','','daysourcecode'
--exec sp_055 '20241101', '20241115','','','','','none'
--exec sp_055 '20241101', '20241115','','','','','marketsegment'
--exec sp_055 '20241101', '20241115','','','','','date'
--exec sp_055 '20241101', '20241115','','','','','daysegment'
--exec sp_055 '20241101', '20241115','','','','','sourcecode'
--exec sp_055_Division '1','20250301', '20250331','','','','','sourcecode'


CREATE   proc [dbo].[sp_055](
	@From date,
	@To date,
	@Area int,
	@Company varchar(50),
	@Segment int,
	@UserSale varchar(20),
	@TypeGroup varchar(20),
	@BF bit = 1
)
 as
begin

	

	declare @operationDate varchar(15) = (select Value from SP1600 where Parameter = 'operationdate')
	declare @division varchar(20) = (select  top 1 isnull(Division,'') from sp1322)
	if(isnull(@operationDate,'')!='' )
	begin
			declare @date date = cast(@operationDate as date) 
			if(@From < @date)
				set @From = @date
	end

	declare @SystemDate date = (select SystemDate from SP1500)
	declare @fDate1 Date, @tDate1 Date, @fDate2 Date, @tDate2 Date, @func_0021 int = 0,@OOO int = 0,@OOS int = 0,@ArrivalDate datetime, @DepartureDate datetime;
	declare @func_002 int = (select Value from SP1600 where Parameter = 'RoomAvailable')

	if @Area = -1 set @Area = ''
	
	set @fDate1 = @From;
	if(@To < @SystemDate)
		set @tDate1 = @To;
	else
		set @tDate1 = dateadd(day, -1, @SystemDate);
	if(@SystemDate <= @From)
		set @fDate2 = @From;
	else
		set @fDate2 = @SystemDate;
	set @tDate2 = @To;
	
	--select * into #Agency from (select * from SP7000 where 1=0) A

--	declare @Agency table 
--	(
--		Date date,
--		CompanyId int,
--		Company nvarchar(500),
--		MarketSegment int,
--		SourceCode int,
--		UserSale varchar(20),
--		Area int,
--		NumOfRoom int,
--		RoomNight int,
--		HU int,
--		FOC int,
--		GuestNight int,
--		RM decimal(18,4),
--		RMWithoutTax decimal(18,4),
--		EB decimal(18,4),
--		ER decimal(18,4),
--		ERWithoutTax decimal(18,4),
--		FBRevenue decimal(18,4),
--		OthersRevenue decimal(18,4),
--		NumOdGuest int,
--		Revenue decimal(18,4),
--		PerRoomNight decimal(18,4),
--		PerRevenue decimal(18,4),
--		AVGRevenue decimal(18,4),
--		RAV int,
--		RAV3 int
--)


	set @func_0021 = (select dbo.func_002(@From,@To))
	
	
	
	
	
		--insert into #Agency([Date],[CompanyId],[Company],[MarketSegment],[SourceCode],[UserSale],[Area],[NumOfRoom],[RoomNight],[HU],[FOC],[GuestNight],[RM],[EB],[ER],[NumOdGuest],[Revenue],[PerRoomNight],[PerRevenue],[AVGRevenue],[RAV],[RAV3])
		--exec sp_148 @From, @To, 1

		--insert into  @Agency([Date],[CompanyId],[Company],[MarketSegment],[SourceCode],[UserSale],[Area],[NumOfRoom],[RoomNight],[HU],[FOC],[GuestNight],[RM],[RMWithoutTax],[EB],[ER], [ERWithoutTax],[FBRevenue],[OthersRevenue],[NumOdGuest],[Revenue],[PerRoomNight],[PerRevenue],[AVGRevenue],[RAV],[RAV3])
		select * into #Agency from func_store148(@From, @To)
		

		--select * from #Agency
		
		
		
		
		
		
		
		

		
		
		
		

		
	

if @TypeGroup = 'none'
	begin

		
	select * into #GroupCompany from (
		select #Agency.Date, CompanyId, ct.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal, MAX(#Agency.RAV) as RAV, Max(#Agency.RAV3) as RAV3
		from #Agency inner join SP1302 ct on #Agency.CompanyId = ct.Id
		left join (select [Date], Company,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.ID = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date, CompanyId, ct.Company
		) A


		

		select * into #Tam1
		from (
		select @SystemDate as Date, 0 as SourceCode, '' as SourceCodeName, 0 as MaSegment, '' as MarketSegment, a1.CompanyId, a1.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) RM, Sum(EB) EB, Sum(ER) ER, SUM(NumOdGuest) as NumOdGuest, Sum(Revenue) Revenue,Sum(PerRoomNight) as PerRoomNight, 
		Sum(PerRevenue) PerRevenue, 
		case when (Sum(RoomNight) - Sum(HU)) != 0 then Sum(Revenue) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(FBRevenue) as FBRevenue, SUM(OthersRevenue) as OthersRevenue,SUM(TotalByDate)  TotalByDate, SUM(OriginalTotal) as OriginalTotal,
		SUM(RAV) as RAV, Max(RAV3) as RAV3, 0 as GRAV
		from #GroupCompany a1
		group by  a1.CompanyId, a1.Company) A

		--select * from #Tam1

			
		select #Tam1.*, @division  as Division
		from #Tam1 
		order by Revenue + FBRevenue + OthersRevenue desc
		
		
		
		
		
		

		
	end

else if @TypeGroup = 'Date'
	begin
		

		select * into #Date from (
		select #Agency.Date, CompanyId, ct.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal, Max(#Agency.RAV) as RAV, Max(RAV3) as RAV3
		from #Agency inner join SP1302 ct on #Agency.CompanyId = ct.Id
		left join (select [Date], Company,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.ID = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date, CompanyId, ct.Company
		) A

		

		declare @groupDate int = (select SUM(RAV) from ( select MAX(RAV) as RAV from #Agency group by Date ) A) 

		select Date, 0 as SourceCode, '' as SourceCodeName, 0 as MaSegment, '' as MarketSegment,	CompanyId,	Company	,NumOfRoom,	RoomNight	,FOC	,HU	,GuestNight,	RM,	EB,	ER	,NumOdGuest,	Revenue,	
		 PerRoomNight,	
		PerRevenue	,AVGRevenue, FBRevenue, OthersRevenue,TotalByDate,  OriginalTotal, RAV, RAV3 , @groupDate as GRAV , @division as Division
		from #Date
		
		order by date asc, Revenue + FBRevenue + OthersRevenue desc
	end
	else if @TypeGroup = 'marketsegment'
	begin
		
		select A.*, SP2000.Salesperson, SP2000.MarketSegment, SP1302.Ma CompanyId, SP1302.Company, SP1000.Area into #ServiceTotal
		from (select * from func_054(@From, @To, '')) A inner join SP2000 on A.BookingId = SP2000.Ma inner join SP1302 on SP2000.TravelAgency = SP1302.Ma inner join SP2100 on SP2100.Ma = A.RentalRoomId left join SP1000 on SP1000.Ma = SP2100.Room

		
	select * into #GroupMarket from (
		select #Agency.Date, #Agency.MarketSegment ,CompanyId, #Agency.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue,SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal, max(#Agency.RAV) as RAV, Max(RAV3) as RAV3
		from #Agency
		left join (select [Date],MarketSegment, Company,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company, MarketSegment ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company and #Agency.MarketSegment = BF.MarketSegment
		left join SP1302 ct on ct.Id = #Agency.CompanyId
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.ID = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date,CompanyId,#Agency.Company, #Agency.MarketSegment
		) A
		
		


		select * into #Tam
		from (
		select  @SystemDate as Date, 0 as SourceCode, '' as SourceCodeName, #GroupMarket.MarketSegment MaSegment, ma.MarketSegment, CompanyId, Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) RM, Sum(EB) EB, Sum(ER) ER, 0 NumOdGuest, Sum(Revenue) Revenue, SUM(PerRoomNight) as PerRoomNight, 
		Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then Sum(Revenue) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(FBRevenue) as FBRevenue, SUM(OthersRevenue) as OthersRevenue,SUM(TotalByDate)  TotalByDate, SUM(OriginalTotal) as OriginalTotal, max(RAV) as RAV , Max(RAV3) as RAV3
		from #GroupMarket 
		left join SP1308 ma on #GroupMarket.MarketSegment = ma.Ma 
		group by #GroupMarket.MarketSegment, ma.MarketSegment, CompanyId, Company) A

		declare @groupMarket int = (select SUM(RAV) from ( select MAX(RAV) as RAV from #Agency group by Date ) A) 

		select *,@groupMarket as GRAV, @division as Division from #Tam 
		order by Revenue + FBRevenue + OthersRevenue desc

		drop table #Tam
		--drop table #NoGuest1
		drop table #ServiceTotal
	end
	else if @TypeGroup = 'daysegment'
	begin
		select * into #Groupdaysegment from (
		select #Agency.Date, #Agency.MarketSegment MaSegment,ma.MarketSegment ,CompanyId, #Agency.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal,max(#Agency.RAV) as RAV, Max(RAV3) as RAV3
		from #Agency
		left join SP1308 ma on #Agency.MarketSegment = ma.Ma
		left join (select [Date], Company, MarketSegment,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company, MarketSegment ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company and #Agency.MarketSegment = BF.MarketSegment
		left join SP1302 ct on ct.Id = #Agency.CompanyId
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.ID = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date,CompanyId,#Agency.Company, #Agency.MarketSegment, ma.MarketSegment 
		) A
		
		
		declare @groupDateSegment int = (select SUM(RAV) from ( select MAX(RAV) as RAV from #Agency group by Date ) A) 

		select Date, 0 as SourceCode, '' as SourceCodeName,MaSegment, MarketSegment,	CompanyId,	Company	,NumOfRoom,	RoomNight	,FOC	,HU	,GuestNight,	RM,	EB,	ER	,NumOdGuest,	Revenue,	
		 PerRoomNight,	
		PerRevenue	,AVGRevenue, FBRevenue, OthersRevenue, TotalByDate, OriginalTotal, RAV , RAV3, @groupDateSegment as GRAV, @division as Division
		
		from #Groupdaysegment
		
		order by Revenue + FBRevenue + OthersRevenue desc
	end
	else if @TypeGroup = 'sourcecode'
	begin
		

		

		
		
	select * into #Groupsourcecode from (
		select #Agency.Date,isnull(#Agency.SourceCode, '') SourceCode, isnull(sc.SourceCode, '') SourceCodeName, CompanyId, #Agency.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal, max(#Agency.RAV) as RAV, Max(RAV3) as RAV3
		from #Agency 
		left join SP8037 sc on #Agency.SourceCode = sc.Ma 
		left join (select [Date], SourceCode, Company,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company, SourceCode ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company and #Agency.SourceCode = BF.SourceCode
		left join SP1302 ct on ct.Id = #Agency.CompanyId
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.Id = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date,#Agency.SourceCode , sc.SourceCode, CompanyId, #Agency.Company 
		) A

		


		select * into #TamSC
		from (
		select @SystemDate as Date, SourceCode,SourceCodeName , 0 as MaSegment, '' as MarketSegment, CompanyId, Company, 
		Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, 
		Sum(RM) RM, Sum(EB) EB, Sum(ER) ER, SUM(NumOdGuest) as NumOdGuest, Sum(Revenue) Revenue, SUM(PerRoomNight) as PerRoomNight, 
		Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then Sum(Revenue) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(FBRevenue) as FBRevenue, SUM(OthersRevenue) as OthersRevenue,SUM(TotalByDate)  TotalByDate, SUM(OriginalTotal) as OriginalTotal,max(RAV) as RAV, Max(RAV3) as RAV3
		from #Groupsourcecode 
		group by SourceCode, SourceCodeName, CompanyId, Company) A

		declare @groupSourceCode int = (select SUM(RAV) from ( select MAX(RAV) as RAV from #Agency group by Date ) A) 

		select *, @groupSourceCode as GRAV, @division as Division from #TamSC 
		order by Revenue + FBRevenue + OthersRevenue desc

		drop table #TamSC
	
	end
	else if @TypeGroup = 'daysourcecode'
	begin
	select * into #Groupdaysourcecode from (
		select #Agency.Date,#Agency.SourceCode SourceCode, sc.SourceCode SourceCodeName, CompanyId, #Agency.Company, Sum(NumOfRoom) NumOfRoom, Sum(RoomNight) RoomNight, Sum(FOC) FOC, Sum(HU) HU, Sum(GuestNight) GuestNight, Sum(RM) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) RM, Sum(EB) EB, Sum(ER) ER, Sum(NumOdGuest) NumOdGuest, Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end) Revenue, Sum(PerRoomNight) PerRoomNight, Sum(PerRevenue) PerRevenue, case when (Sum(RoomNight) - Sum(HU)) != 0 then (Sum(Revenue) - (case when @BF = 1 then 0 else isnull(Sum(#Agency.BF), 0) end)) / (Sum(RoomNight) - Sum(HU)) else 0 end AVGRevenue, SUM(#Agency.FBRevenue) as FBRevenue, SUM(#Agency.OthersRevenue) as OthersRevenue,SUM(#Agency.TotalByDate) TotalByDate, SUM(#Agency.OriginalTotal) OriginalTotal, max(#Agency.RAV) as RAV, Max(RAV3) as RAV3
		from #Agency 
		left join SP8037 sc on #Agency.SourceCode = sc.Ma 
		left join (select [Date],SourceCode, Company,sum(Amount) as Total from func_075(@From,@To) group by [Date], Company,SourceCode ) as BF on #Agency.Date = BF.Date and #Agency.CompanyId = BF.Company and #Agency.SourceCode = BF.SourceCode
		left join sp1302 ct on ct.Id = #Agency.CompanyId
		where #Agency.Date between @From and @To
			and (@Area = '' or Area = @Area)
			and (@Company = '' or ct.ID = @Company)
			and (@Segment = '' or #Agency.MarketSegment = @Segment)
			and (@UserSale = '' or UserSale = @UserSale)
		group by #Agency.Date,#Agency.SourceCode , sc.SourceCode, CompanyId, #Agency.Company
		) A

		declare @groupDateSourceCode int = (select SUM(RAV) from ( select MAX(RAV) as RAV from #Agency group by Date ) A) 

		select Date, SourceCode, SourceCodeName, 0 as MaSegment, '' as MarketSegment, CompanyId, Company, NumOfRoom, RoomNight,FOC, HU, GuestNight,  RM,  EB,  ER,  NumOdGuest,  Revenue,	PerRoomNight,
		PerRevenue, AVGRevenue, FBRevenue, OthersRevenue, TotalByDate, OriginalTotal, RAV, RAV3, @groupDateSourceCode as GRAV, @division as Division
		from #Groupdaysourcecode
		
		order by Revenue + FBRevenue + OthersRevenue desc
	end

	drop table if exists #Agency

end



(1 rows affected)

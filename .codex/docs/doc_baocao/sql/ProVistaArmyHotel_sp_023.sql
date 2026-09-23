--exec sp_023 '2023/11/01','2023/11/30', '',0
--exec sp_023 '2023/04/01','2023/04/07', '',1
--exec sp_023 '2023/06/02','2023/06/02', '',1
---------------------------------
CREATE proc [dbo].[sp_023]--'2010/03/19','2010/03/20'
	@FromDate date,
	@ToDate date,
	@Division nvarchar(100),
	@BF bit = 1,
	@ShowDivision bit = 1
as 
	begin

	--Kiểm tra ngày hoạt động
	declare @operationDate varchar(15) = (select Value from SP1600 where Parameter = 'operationdate')
	declare @divisionName varchar(20) = (select  top 1 isnull(Division,'') from sp1322)
	declare @averageRoomRateIncludedOthersRoomRevenue char(1) = (select isnull(Value, 0) from SP1600 where Parameter = 'AverageRoomRateIncludedOthersRoomRevenue')
	if(isnull(@operationDate,'')!='' )
	begin
			declare @date date = cast(@operationDate as date) 
			if(@FromDate < @date)
				set @FromDate = @date
	end
	
	create table #temp8
	(
		Date datetime,
		HouseUse int
	)

	create table #temp10
	(
		Date datetime,
		FOCAll int
	)

	create table #temp11
	(
		Date datetime,
		FOC int
	)

	create table #temp12
	(
		Date datetime,
		FOCOwner int
	)

	create table #temp13
	(
		Date datetime,
		ExtraBed int
	)

	create table #temp14
	(
		Date datetime,
		BabyCot int
	)

	create table #tmp
	(Date datetime,
	RoomSales int,
	AvgRate decimal(18,2),
	AvgRate2 decimal(18,2),
	RoomAvible int,
	PercentOccupancy decimal(18,2),
	PercentOccupancy1 decimal(18,2),
	RM decimal(18,2) ,
	EB decimal(18, 2),
	ER decimal(18, 2),
	OOO int
	)

	declare @RM decimal(18,2) = 0
	declare @DayUse decimal(18,2) = 0
	declare @EB decimal(18, 2)= 0 
	declare @ER decimal(18, 2)= 0
	declare @OccRooms int
	declare @HouseUse int
	declare @FOCAll int
	declare @RoomSales int
	declare @AvgRate decimal(18,2)
	declare @AvgRate2 decimal(18,2)
	declare @func_002 int
	declare @RoomAvible int
	declare @RoomOOO int
	declare @Revenue decimal(18,2)
	declare @PercentOccupancy decimal(18,2)
	declare @PercentOccupancy1 decimal(18,2)
	declare @ERRevenue varchar(50) = (select Value from SP1600 where Parameter = 'RevenueER')

	declare @SystemDate date;
	select  @SystemDate = SystemDate from SP1500 

	declare @fDate1 Date, @tDate1 Date, @fDate2 Date, @tDate2 Date;
	--
	set @fDate1 = @FromDate;
	if(@ToDate < @SystemDate)
		set @tDate1 = @ToDate;
	else
		set @tDate1 = dateadd(day,-1,@SystemDate);
	if(@SystemDate <= @FromDate)
		set @fDate2 = @FromDate;
	else
		set @fDate2 = @SystemDate;
	set @tDate2 = @ToDate;
	--

	declare @FOCBaoGom varchar(5) = (select Value from SP1600 where Parameter = 'FOCBaoGomCompliment')

	select Date, RentalRoomId,Total, ServiceId, IsRoomNight, BookingId ,RoomRateCode
	into #temp1 
	from func_054(@FromDate, @ToDate, '')
	where (ServiceId = 'RM' and IsRoomNight = 1) or ServiceId in (select Data from dbo.func_061((select Value from SP1600 where Parameter = 'Revenue'),','))

	select Date, RentalRoomId, RoomRateCode, Sum(Total) as Total into #temp2 from func_052(@FromDate, @ToDate, '') where ServiceId = 'RM'
	group by Date, RentalRoomId, RoomRateCode

	select pt.Date, isnull(pt.Adult, 0) DepAdult,
		isnull(pt.Child,0)  DepChild,
		isnull(pt.NumOfRoom,0) DepRooms
	into #temp3 
	from (select pt1.ArrivalDate+pt1.ActualNumOfDays as Date, isnull(Sum(pt1.Adult),0) as Adult,
	isnull(Sum(pt1.Child),0) as Child, COUNT(*) as NumOfRoom
	from SP2100 pt1
	left join vw_001 dk on pt1.BookingId = dk.Ma
	where pt1.ArrivalDate+pt1.ActualNumOfDays between @FromDate and @ToDate
		and pt1.Status in(0,1,2)
		and (pt1.Room is null or pt1.Room not like '0%%')  
		and dk.IsAvailability = 1
	group by pt1.ArrivalDate+pt1.ActualNumOfDays) pt
		

	select pt.Date, isnull(pt.Adult,0)  as ArrAdult,
	isnull(pt.Child,0) ArrChild,
	isnull(pt.NumOfRoom,0) as ArrRooms
	into #temp4
	from (select pt.ArrivalDate as Date, isnull(Sum(pt.Adult),0) as Adult,
			isnull(Sum(pt.Child),0) as Child, isnull(count(*),0) as NumOfRoom
		from SP2100 pt
		left join vw_001 dk on pt.BookingId = dk.Ma
		where pt.ArrivalDate between @FromDate and @ToDate and dk.IsAvailability = 1 
		and pt.Status in (0, 1, 2) and isnull(Room,'') not like '0%'
	group by pt.ArrivalDate) pt


	select Date, Count(sale.RentalRoomId) as OccAdult into #temp5
	from (select * from #temp1--func_054(@FromDate, @FromDate, '')
			where ServiceId = 'RM' and IsRoomNight = 1) sale
		inner join SP2100 pt on sale.RentalRoomId = pt.Ma 
		left join vw_001 dk on pt.BookingId = dk.Ma
		inner join SP2200 ptk on pt.Ma = ptk.RentalRoomId 
		and ptk.Status in (0, 1, 2, 4, 100)  and dk.IsAvailability = 1 and (pt.ActualNumOfDays = 0 or (sale.Date > pt.ArrivalDate + case when ActualNumOfDays = 0 then ActualNumOfDays else ActualNumOfDays - 1 end and ptk.CheckoutDate > pt.ArrivalDate + case when ActualNumOfDays = 0 then ActualNumOfDays else ActualNumOfDays - 1 end) or ptk.CheckoutDate > sale.Date)
		group by Date
			  
	--select @OccChild=dbo.funcTDATinhSoChildChiemDung(@FromDate)
	select Date, isnull(Sum(pt.Child), 0) as OccChild into #temp6
		from (select * from #temp1--func_054(@FromDate, @FromDate, '')
			where ServiceId = 'RM' and IsRoomNight = 1) sale
		inner join SP2100 pt on sale.RentalRoomId = pt.Ma
		left join vw_001 dk on pt.BookingId = dk.Ma
		where dk.IsAvailability = 1
		group by Date
			  
	--select @OccRooms=dbo.funcTDATinhNumOfRoomChiemDungThucTeForReport(-1,@FromDate)
	select Date, Count(*) as OccRooms into #temp7
	from (
		select Date, RentalRoomId
		from (select * from #temp1--func_054(@Date, @Date, '')
		) A inner join SP2100 on SP2100.Ma = A.RentalRoomId	
		left join vw_001 dk on SP2100.BookingId = dk.Ma
		where ServiceId = 'RM' and IsRoomNight = 1 and dk.IsAvailability = 1
	) A group by Date
			  

	insert into #temp8 
	select Date, count(*) as HouseUse 
	from #temp1
	left join vw_001 on vw_001.ma=#temp1.BookingId
	left join SP2100 pt on #temp1.RentalRoomId = pt.Ma
	where IsAvailability = 1 and ServiceId = 'RM' 
			and IsRoomNight = 1 and pt.RoomRateCode ='HU'
	group by Date

	insert into #temp11 
	select Date, count(*) as FOC 
	from #temp1
	left join vw_001 on vw_001.ma=#temp1.BookingId
	left join SP2100 pt on #temp1.RentalRoomId = pt.Ma
	where IsAvailability = 1 and ServiceId = 'RM' 
			and IsRoomNight = 1 and (pt.RoomRateCode ='FOC' or (isnull(pt.RoomRateCode,'') != 'HU' and Total = 0))
	group by Date
	

	SELECT DISTINCT HDDV.Date, HDDV.RentalRoomId1 into #temp9
			FROM SP3000 HDDV INNER JOIN SP3004 HDTP ON HDDV.Ma = HDTP.BillId INNER JOIN SP3002 TT ON HDDV.PaymentId = TT.PaymentId
			WHERE TT.PaymentMethod = 'CL' AND HDDV.Edit = 0
	
	--select @FOCAll=dbo.funcTDATinhNumOfRoomFOC(@FromDate)
	--Đoạn quá khứ nhỏ hơn ngày hệ thống
	if(@fDate1 <= @tDate1)
	begin 
		;WITH Dates AS
			(SELECT @fDate1 as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @tDate1
			)

		insert into #temp10
		select Date, ISNULL(count(Ma),0) from(
			SELECT Date, RentalRoomId as Ma
				FROM SP7001
				WHERE  Date between @fDate1 and @tDate1
							AND ( Room NOT LIKE '0%' OR Room IS NULL)
							and (RoomRateCode like 'FOC%' or (1 != '0' and ((Rate=0 and RoomRateCode not like 'HU%')
								or (Rate = 0 and ISNULL(RoomRateCode, '') = '')
								or (ISNULL(RoomRateCode, '') = '' and SP7001.RentalRoomId IN (
										--SELECT DISTINCT HDDV.RentalRoomId1
										--FROM SP3000 HDDV INNER JOIN SP3004 HDTP ON HDDV.Ma = HDTP.BillId INNER JOIN SP3002 TT ON HDDV.PaymentId = TT.PaymentId
										--WHERE TT.PaymentMethod = 'CL' AND HDDV.Date = @Date AND HDDV.Edit = 0
										select RentalRoomId1 from #temp9 where Date = SP7001.Date
								)))))
								union
				select lst.Date, P.Ma
				from SP2100 P
				left join vw_001 dk on dk.Ma=P.BookingId 
				left join SP1302 on dk.TravelAgency=SP1302.ma
				left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
				left join SP2300 on SP2300.Id=SP2200.CustomerId
				--left join RentalRoomRoomRateCode on P.Ma = RentalRoomRoomRateCode.RentalRoomId
				left join (select Date,RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
				where Date between @fDate1 and @tDate1--ServiceId = 'RM'
				--group by RentalRoomId,RoomRateCode
				) as lst on lst.RentalRoomId = p.Ma
				where 
				--((P.RoomRateCode is null and RentalRoomRoomRateCode.Ma is not null
				--and @FromDate between RentalRoomRoomRateCode.FromDate and RentalRoomRoomRateCode.ToDate 
				--and RentalRoomRoomRateCode.RoomRateCode like 'FOC%') OR 
				(lst.Total = 0 and lst.RoomRateCode like 'FOC%')--)
				and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma)))) and dk.IsAvailability = 1
				group by lst.Date,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma
								union
				select RentalRoomRoomRateCode.FromDate, P.Ma
				from SP2100 P
				left join vw_001 dk on dk.Ma=P.BookingId 
				left join SP1302 on dk.TravelAgency=SP1302.ma
				left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
				left join SP2300 on SP2300.Id=SP2200.CustomerId
				left join (select * from SP2102 inner join Dates n 
					on n.Date between SP2102.FromDate and SP2102.ToDate) as RentalRoomRoomRateCode on P.Ma = RentalRoomRoomRateCode.RentalRoomId and RentalRoomRoomRateCode.ServiceId = 'RM'
				--left join (select Date,RentalRoomId,RoomRateCode,Sum(Total) as Total from dbo.func_052(@Date,@Date,'') 
				--where ServiceId = 'RM'
				--group by RentalRoomId,RoomRateCode
				--) as lst on lst.RentalRoomId = p.Ma
				where 
				((P.RoomRateCode is null and RentalRoomRoomRateCode.Ma is not null
				--and @FromDate between RentalRoomRoomRateCode.FromDate and RentalRoomRoomRateCode.ToDate 
				and RentalRoomRoomRateCode.RoomRateCode like 'FOC%'))--OR (lst.Total = 0 and lst.RoomRateCode like 'FOC%'))
				and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma)))) and dk.IsAvailability = 1
				group by RentalRoomRoomRateCode.FromDate,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma
		union
		--late checkin noshow compliment
		select lst.Date, P.Ma
				from SP2100 P
					left join vw_001 dk on dk.Ma=P.BookingId 
					left join SP1302 on dk.TravelAgency=SP1302.ma
					left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
					left join SP2300 on SP2300.Id=SP2200.CustomerId
					left join (select Dates.Date, a.RentalRoomId, a.RoomRateCode, a.Total from Dates left join (select Date,RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
								where Date between @fDate1 and @tDate1--ServiceId = 'RM'
								--group by RentalRoomId,RoomRateCode
								) a on Dates.Date = a.Date) as lst on lst.RentalRoomId = p.Ma
				where isnull(lst.RoomRateCode, '') not like 'HU%' and isnull(lst.RoomRateCode, '') not like 'FOC%'
					and (lst.Total = 0
						or P.Ma IN (
							--	SELECT DISTINCT HDDV.RentalRoomId1
							--	FROM SP3000 HDDV INNER JOIN SP3004 HDTP ON HDDV.Ma = HDTP.BillId INNER JOIN SP3002 TT ON HDDV.PaymentId = TT.PaymentId
							--WHERE TT.PaymentMethod = 'CL' AND HDDV.Date = @Date AND HDDV.Edit = 0
								select RentalRoomId1 from #temp9 where Date = lst.Date
							))
					and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma))))
					and isnull(Room,'') not like '0%' and dk.IsAvailability = 1
				group by lst.Date,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma)result
				group by Date
				option (maxrecursion 0)
	end
	--Đoạn tương lai lớn hơn và bằng ngày hệ thống
	if(@fDate2 <= @tDate2)
	begin
		;WITH Dates AS
			(SELECT @fDate2 as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @tDate2
			)
		insert into #temp10
		select Date, isnull(count(Ma),0)
		from(
				select Dates.Date, pt1.Ma
				from SP2100 pt1
					inner join Dates on Dates.Date between pt1.ArrivalDate and dateadd(dd, pt1.ActualNumOfDays - case when pt1.DayUse = '1' then 0 else 1 end, pt1.ArrivalDate)
					left join (select Date,RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
								where Date between @fDate2 and @tDate2--ServiceId = 'RM'
								--group by RentalRoomId,RoomRateCode
								) as lst on lst.RentalRoomId = pt1.Ma and lst.Date = Dates.Date
					left join vw_001 dk on dk.Ma=pt1.BookingId 
				where --(@FromDate between pt1.ArrivalDate and dateadd(dd, pt1.ActualNumOfDays - case when pt1.DayUse = 1 then 0 else 1 end, pt1.ArrivalDate))
					--and 
					pt1.Status in(0,1,2,100) and dk.IsAvailability = 1
					and (pt1.Room is null or pt1.Room not like '0%%')
					and pt1.Ma not in( select pt2.MoveRoom 
								from SP2100 pt2
								where (Dates.Date between pt2.ArrivalDate and dateadd(dd, pt2.ActualNumOfDays - case when pt1.DayUse = '1' then 0 else 1 end, pt2.ArrivalDate))
									and pt2.Status in(0,1,2,100,4)
									and (pt2.Room is null or pt2.Room not like '0%%')
									and pt2.MoveRoom is not null
									and pt2.ArrivalDate+pt2.ActualNumOfDays=pt1.ArrivalDate)
					and (lst.RoomRateCode  like 'FOC%' or (1 != '0' and ((lst.Total=0 and lst.RoomRateCode not like 'HU%')
						or (lst.Total = 0 and ISNULL(lst.RoomRateCode, '') = '')
						or (ISNULL(lst.RoomRateCode, '') = '' and pt1.Ma IN (
							--SELECT DISTINCT HDDV.RentalRoomId1
							--FROM SP3000 HDDV INNER JOIN SP3004 HDTP ON HDDV.Ma = HDTP.BillId INNER JOIN SP3002 TT ON HDDV.PaymentId = TT.PaymentId
							--WHERE TT.PaymentMethod = 'CL' AND HDDV.Date = @Date AND HDDV.Edit = 0
							select RentalRoomId1 from #temp9 where Date = Dates.Date
						))))
					)
		--late checkin no show FOC
		union
				select lst.Date, P.Ma
				from SP2100 P
				left join vw_001 dk on dk.Ma=P.BookingId 
				left join SP1302 on dk.TravelAgency=SP1302.ma
				left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
				left join SP2300 on SP2300.Id=SP2200.CustomerId
				left join SP2102 on P.Ma = SP2102.RentalRoomId
				left join (select Date,RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
				where Date between @fDate2 and @tDate2--ServiceId = 'RM'
				--group by RentalRoomId,RoomRateCode
				) as lst on lst.RentalRoomId = p.Ma
				where 
				----((P.RoomRateCode is null and RentalRoomRoomRateCode.Ma is not null
				----and @Date between RentalRoomRoomRateCode.FromDate and RentalRoomRoomRateCode.ToDate 
				----and RentalRoomRoomRateCode.RoomRateCode like 'FOC%') OR 
				(lst.Total = 0 and lst.RoomRateCode like 'FOC%')--) 
				and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma)))) and dk.IsAvailability = 1
				group by lst.Date,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma
		union
				select RentalRoomRoomRateCode.FromDate,P.Ma
				from SP2100 P
				left join vw_001 dk on dk.Ma=P.BookingId 
				left join SP1302 on dk.TravelAgency=SP1302.ma
				left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
				left join SP2300 on SP2300.Id=SP2200.CustomerId
				left join (select * from SP2102 inner join Dates n 
					on n.Date between SP2102.FromDate and SP2102.ToDate) as RentalRoomRoomRateCode on P.Ma = RentalRoomRoomRateCode.RentalRoomId and RentalRoomRoomRateCode.ServiceId = 'RM'
				--left join (select RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
				--where Date between @fDate2 and @tDate2--ServiceId = 'RM'
				--group by RentalRoomId,RoomRateCode
				--) as lst on lst.RentalRoomId = p.Ma
				where 
				((P.RoomRateCode is null and RentalRoomRoomRateCode.Ma is not null
				--and @Date between RentalRoomRoomRateCode.FromDate and RentalRoomRoomRateCode.ToDate 
				and RentalRoomRoomRateCode.RoomRateCode like 'FOC%'))-- OR (lst.Total = 0 and lst.RoomRateCode like 'FOC%'))
				and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma)))) and dk.IsAvailability = 1
				group by RentalRoomRoomRateCode.FromDate,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma
		union
		--late checkin noshow compliment
		select lst.Date,P.Ma
				from SP2100 P
					left join vw_001 dk on dk.Ma=P.BookingId 
					left join SP1302 on dk.TravelAgency=SP1302.ma
					left join SP2200 on P.ma=SP2200.RentalRoomId and Ismainguest=1
					left join SP2300 on SP2300.Id=SP2200.CustomerId
					left join (select Dates.Date, a.RentalRoomId, a.RoomRateCode, a.Total from Dates left join (select Date,RentalRoomId,RoomRateCode,Total from #temp2--dbo.func_052(@Date,@Date,'') 
								where Date between @fDate2 and @tDate2--ServiceId = 'RM'
								--group by RentalRoomId,RoomRateCode
								) a on Dates.Date = a.Date) as lst on lst.RentalRoomId = p.Ma
				where isnull(lst.RoomRateCode, '') not like 'HU%' and isnull(lst.RoomRateCode, '') not like 'FOC%'
					and (lst.Total = 0
						or P.Ma IN (
							--	SELECT DISTINCT HDDV.RentalRoomId1
							--	FROM SP3000 HDDV INNER JOIN SP3004 HDTP ON HDDV.Ma = HDTP.BillId INNER JOIN SP3002 TT ON HDDV.PaymentId = TT.PaymentId
							--WHERE TT.PaymentMethod = 'CL' AND HDDV.Date = @Date AND HDDV.Edit = 0
								select RentalRoomId1 from #temp9 where Date = lst.Date
							))
					and ((P.Status in (0,1,2,100) and (P.Status != 100 or P.ActualNumOfDays != 0)) or ((P.Status=4 and P.Ma in (select hddv.RentalRoomId1 from SP3000 hddv inner join SP3004 on Ma=BillId where IsRoomNight=1 and hddv.RentalRoomId1=p.Ma))))
					and isnull(Room,'') not like '0%' and dk.IsAvailability = 1
				group by lst.Date,P.BookingId,SP2300.FirstName,P.Room,P.ArrivalDate,P.ArrivalDate+P.ActualNumOfDays,SP1302.Company,P.RoomRateCode,P.Rate,dk.Note,P.Ma)result
				group by Date
				option (maxrecursion 0)
	end

	if(@fDate1 <= @tDate1)
	begin
		insert into #temp12
		select ih1.Date, isnull(ih1.RentalRoomId, 0) - isnull(ih2.RentalRoomId, 0) from (
		(SELECT Date, isnull(count(distinct RentalRoomId),0) as RentalRoomId
					  FROM SP7001
					  WHERE  Date between @fDate1 and @tDate1
							AND ( Room NOT LIKE '0%' OR Room IS NULL)
							and (RoomRateCode like 'FOC%' or (Rate=0 and RoomRateCode not  like 'HU%')) group by Date) ih1
		left join
		(SELECT  Date, isnull(count(distinct RentalRoomId),0) as RentalRoomId
					  FROM SP7001
					  WHERE  Date between @fDate1 and @tDate1
							AND ( Room NOT LIKE '0%' OR Room IS NULL)
							and (RoomRateCode = 'FOC') group by Date) ih2 on ih2.Date = ih1.Date)
	end

	--Đoạn tương lai lớn hơn và bằng ngày hệ thống
	if(@fDate2 <= @tDate2)
	begin
		;WITH Dates AS
			(SELECT @fDate2 as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @tDate2
			)

		insert into #temp12
		select pt1.Date, isnull(pt1.RoomRateCode, 0)
		- (isnull(pt2.RoomRateCode, 0)) as FOCOwner from (
		(select Dates.Date, isnull(COUNT(RoomRateCode), 0) as RoomRateCode
			from SP2100 pt1
			inner join Dates on Dates.Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate)
			left join vw_001 dk on dk.Ma=pt1.BookingId 	
			where --(@Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate))
			--and 
				pt1.Status in(0,1,2,100) and dk.IsAvailability = 1 
				and (pt1.Room is null or pt1.Room not like '0%%')
				and pt1.Ma not in( select pt2.MoveRoom 
							from SP2100 pt2
							where (Dates.Date between pt2.ArrivalDate and   dateadd(dd, pt2.ActualNumOfDays - 1, pt2.ArrivalDate))
								and pt2.Status in(0,1,2,100)
								and (pt2.Room is null or pt2.Room not like '0%%')
								and pt2.MoveRoom is not null
								and pt2.ArrivalDate+pt2.ActualNumOfDays=pt1.ArrivalDate)
				and (pt1.RoomRateCode  like 'FOC%' or (pt1.Rate=0 and pt1.RoomRateCode not  like 'HU%'))
			group by Dates.Date) pt1
		left join (select Date, isnull(COUNT(RoomRateCode), 0) as RoomRateCode
			from SP2100 pt1
			inner join Dates on Dates.Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate)
			left join vw_001 dk on dk.Ma=pt1.BookingId 	
			where --(@Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate))
				--and 
				pt1.Status in(0,1,2,100) and dk.IsAvailability = 1 
				and (pt1.Room is null or pt1.Room not like '0%%')
				and pt1.Ma not in( select pt2.MoveRoom 
							from SP2100 pt2
							where (Dates.Date between pt2.ArrivalDate and   dateadd(dd, pt2.ActualNumOfDays - 1, pt2.ArrivalDate))
								and pt2.Status in(0,1,2,100)
								and (pt2.Room is null or pt2.Room not like '0%%')
								and pt2.MoveRoom is not null
								and pt2.ArrivalDate+pt2.ActualNumOfDays=pt1.ArrivalDate)
				and (pt1.RoomRateCode  = 'FOC')
				group by Date) pt2 on pt2.Date = pt1.Date)
		option (maxrecursion 0)
	end

	--select @ExtraBed=dbo.funcTDATinhSoExtraBed(@FromDate)
	;WITH Dates AS
			(SELECT @FromDate as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @ToDate
			)

	insert into #temp13
	select Date, isnull(Sum(ExtraBed), 0) as ExtraBed from (select Dates.Date, p.Ma Room, pt.Ma, isnull(ptdv.Quantity, pt.ExtraBed) ExtraBed, p.Area, lp.Ma RoomType, pt.Status,
			case when (isnull(ptdv.Quantity, pt.ExtraBed) > 0) then isnull(ptdv.Total, pt.ExtraBedRate) else 0 end ExtraBedRate
		from	SP2100 pt 
				inner join Dates on Dates.Date between pt.ArrivalDate and pt.ArrivalDate + ActualNumOfDays-1 
				left join vw_001 dk on dk.Ma=pt.BookingId 	
				left join SP1100 lp on lp.Ma = pt.RoomType
				left join SP1000 p on pt.Room = p.Ma 
				left join (select * from SP2102 ptdv inner join Dates on ptdv.ServiceId = 'EB' and Dates.Date between ptdv.FromDate and ptdv.ToDate) ptdv on pt.Ma = ptdv.RentalRoomId and ptdv.Date = Dates.Date
				--and ptdv.ServiceId = 'EB' and ptdv.FromDate <= @Day and @Day <= ptdv.ToDate
		where pt.BookingId is not null and pt.Status in (0, 1, 2, 100) and dk.IsAvailability = 1 -- and pt.ArrivalDate <= @Day and @Day <= pt.ArrivalDate + ActualNumOfDays-1 
		-- Lấy ngày đến + số ngày thực -1
				--and isnull(pt.ExtraBed, 0) + isnull(ptdv.Quantity, 0) > 0
			) result group by Date
	option (maxrecursion 0)
	
	--select @BabyCot=dbo.funcTDATinhSoBabyCot(@FromDate)
	--Đoạn quá khứ nhỏ hơn ngày hệ thống
	if(@fDate1 <= @tDate1)
	begin
		;WITH Dates AS
			(SELECT @fDate1 as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @tDate1
			)

		insert into #temp14
		select pt.Date, isnull(pt.Provide1, 0) as BabyCot from 
		(select Date, isnull(Sum(cast(isnull(SP2100.Provide1,0) as int)),0) as Provide1
			from SP2100
			inner join Dates on Date between ArrivalDate and   dateadd(dd, ActualNumOfDays - 1, ArrivalDate)
			left join vw_001 dk on dk.Ma=SP2100.BookingId 	
			where --(@Date between ArrivalDate and   dateadd(dd, ActualNumOfDays - 1, ArrivalDate))
				--and 
				SP2100.Status in(1,2,5,100) and dk.IsAvailability = 1
				and (Room is null or Room not like '0%%')
			group by Date) pt
		option (maxrecursion 0)
	end
	
	--Đoạn tương lai lớn hơn và bằng ngày hệ thống
	if(@fDate2 <= @tDate2)
	begin
		;WITH Dates AS
			(SELECT @fDate2 as Date
			UNION ALL
			SELECT DATEADD(day, 1, Date)
			FROM Dates
			WHERE DATEADD(day, 1, Date) <= @tDate2
			)

		insert into #temp14
		select pt.Date, isnull(pt.Provide1, 0) as BabyCot from 
		(select Date, isnull(Sum(cast(isnull(pt1.Provide1,0) as int)),0) as Provide1
			from SP2100 pt1
			inner join Dates on Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate)
			left join vw_001 dk on dk.Ma=pt1.BookingId 	
			where --(@Date between pt1.ArrivalDate and   dateadd(dd, pt1.ActualNumOfDays - 1, pt1.ArrivalDate))
			--and 
				pt1.Status in(0,1,2,100) and dk.IsAvailability = 1
				and (pt1.Room is null or pt1.Room not like '0%%')
				and pt1.Ma not in( select pt2.MoveRoom 
						from SP2100 pt2
						where (Date between pt2.ArrivalDate and   dateadd(dd, pt2.ActualNumOfDays - 1, pt2.ArrivalDate))
							and pt2.Status in(0,1,2,100)
							and pt2.MoveRoom is not null
							and pt2.ArrivalDate+pt2.ActualNumOfDays=pt1.ArrivalDate)
			group by Date) pt
		option (maxrecursion 0)
	end
	
	-- Tính doanh thu tiền dịch vụ
	select BF.Total,isnull(rev.Date, hddv.Date) as Date, isnull(rev.Total, 0) + isnull(hddv.Amount, 0) - (case when @BF = 1 then 0 else isnull(BF.Total,0) end) as Revenue
	into #temp15 
	from 
	(select Date, isnull(sum(Total), 0) as Total from #temp1 
	--left join vw_001 dk on dk.Ma=#temp1.BookingId 
		where  #temp1.Date between @FromDate and @ToDate and #temp1.ServiceId in (select Data from dbo.func_061((select Value from SP1600 where Parameter = 'Revenue'),','))
		--and IsAvailability = 1
		group by #temp1.Date) rev
	full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma
	where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @FromDate and @ToDate and Edit = 0
	and (ServiceId in (select Data from dbo.func_061((select Value from SP1600 where Parameter = 'Revenue'),',')))
	group by Date) hddv on hddv.Date = rev.Date
	full outer join (select [Date],sum(Amount) as Total from func_075(@FromDate,@ToDate) group by [Date] ) as BF on isnull(rev.Date, hddv.Date) = BF.Date
	

	update #temp15 set Revenue = Revenue - rev.Total
	from 
	#temp15 inner join 
	(select Date, isnull(sum(Total), 0) as Total from #temp1 
	left join vw_001 dk on dk.Ma=#temp1.BookingId 
		where  #temp1.Date between @FromDate and @ToDate and #temp1.ServiceId in (select Data from dbo.func_061((select Value from SP1600 where Parameter = 'Revenue'),','))
		and IsAvailability = 0
		group by #temp1.Date) rev on #temp15.Date = rev.Date 
	where #temp15.Date >= @SystemDate


	--exec sp_023 '2023/06/03','2023/06/03', '',1
	--RM
	select isnull(rm1.Date, hddv.Date) as Date, isnull((rm1.Total), 0) + isnull(hddv.Amount, 0)  - (case when @BF = 1 then 0 else isnull(BF.Total,0) end) as RM into #temp16 
	from (select #temp1.Date, isnull(sum(#temp1.Total), 0) as Total from #temp1
	left join SP2100 pt on pt.Ma = #temp1.RentalRoomId
	--left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
	where #temp1.ServiceId in ('RM')  and (pt.DayUse is null or pt.DayUse = 0)
	group by #temp1.Date) rm1
	full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma
			where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @FromDate and @ToDate and Edit = 0
			and (ServiceId in ('RM'))
			group by Date) hddv on hddv.Date = rm1.Date
	full outer join (select [Date],sum(Amount) as Total from func_075(@FromDate,@ToDate) group by [Date] ) as BF on isnull(rm1.Date, hddv.Date) = BF.Date

	update #temp16 set #temp16.RM = #temp16.RM - rev.RM
	from 
	#temp16 inner join 
	(select #temp1.Date, isnull(sum(#temp1.Total), 0)  - (case when @BF = 1 then 0 else isnull(BF.Total,0) end) as RM 
	from #temp1 left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
	left join SP2100 pt on pt.Ma = #temp1.RentalRoomId
	left join (select [Date],sum(Amount) as Total from func_075(@FromDate,@ToDate) group by [Date] ) as BF on #temp1.Date = BF.Date
	where #temp1.ServiceId in ('RM') and dk.IsAvailability =0 and (pt.DayUse is null or pt.DayUse = 0)
	group by #temp1.Date, BF.Total) rev on #temp16.Date = rev.Date 
	where #temp16.Date >= @SystemDate

	-- DayUse
	select rm1.Date as Date, isnull((rm1.Total), 0)  as DayUse into #temp19 
	from (select #temp1.Date, isnull(sum(#temp1.Total), 0) as Total from #temp1
	left join SP2100 pt on pt.Ma = #temp1.RentalRoomId
	--left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
	where #temp1.ServiceId in ('RM') and pt.DayUse = 1
	group by #temp1.Date) rm1
	--full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma
	--		where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @FromDate and @ToDate and Edit = 0
	--		and (ServiceId in ('RM'))
	--		group by Date) hddv on hddv.Date = rm1.Date

	update #temp19 set #temp19.DayUse = #temp19.DayUse - rev.DayUse
	from 
	#temp19 inner join 
	(select #temp1.Date, isnull(sum(#temp1.Total), 0)  - (case when @BF = 1 then 0 else isnull(BF.Total,0) end) as DayUse 
	from #temp1 left join vw_001 dk on dk.Ma=#temp1.BookingId	
	left join SP2100 pt on pt.Ma = #temp1.RentalRoomId
	--dbo.func_054(@FromDate, @FromDate, '')
	left join (select [Date],sum(Amount) as Total from func_075(@FromDate,@ToDate) group by [Date] ) as BF on #temp1.Date = BF.Date
	where #temp1.ServiceId in ('RM') and dk.IsAvailability =0 and pt.DayUse = 1 
	group by #temp1.Date, BF.Total) rev on #temp19.Date = rev.Date 
	where #temp19.Date >= @SystemDate

	-- EB
	select isnull(eb1.Date, hddv.Date) as Date, isnull(eb1.Total, 0) + isnull(hddv.Amount, 0) as EB into #temp17 from
	(select Date, isnull(sum(Total),0) as Total from #temp1 left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
		where  #temp1.ServiceId in ('EB') and dk.IsAvailability =1
	group by Date) eb1
	full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma
			where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @FromDate and @ToDate and Edit = 0
			and (ServiceId in ('EB'))
			group by Date) hddv on hddv.Date = eb1.Date

	-- ER
	select isnull(er1.Date, hddv.Date) as Date, isnull(er1.Total, 0) + isnull(hddv.Amount, 0) as ER into #temp18 from
	(select Date, isnull(sum(Total),0) as Total from #temp1 left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
		where #temp1.ServiceId in (select Data from dbo.func_061(@ERRevenue, ',')) --and dk.IsAvailability =1
	group by Date) er1
	full outer join (select Date, isnull(sum(Amount), 0) as Amount from SP3000 inner join SP2000 on RegisterId2 = SP2000.Ma
			where RentalRoomId1 is null and RentalRoomId2 is null and RegisterId2 is not null and Date between @FromDate and @ToDate and Edit = 0
			and (ServiceId in (select Data from dbo.func_061(@ERRevenue, ',')))
			group by Date) hddv on hddv.Date = er1.Date
	
	update #temp18 set #temp18.ER = #temp18.ER - rev.Total
	from 
	#temp18 inner join 
	(select Date, isnull(sum(Total),0) as Total from #temp1 left join vw_001 dk on dk.Ma=#temp1.BookingId--dbo.func_054(@FromDate, @FromDate, '')
		where #temp1.ServiceId in (select Data from dbo.func_061(@ERRevenue, ',')) and dk.IsAvailability =0
	group by Date) rev on #temp18.Date = rev.Date 
	where #temp18.Date >= @SystemDate

	--update #temp15 set Revenue = Revenue - isnull(#temp19.DayUse, 0)
	--from 
	--#temp15 left join #temp19 on #temp15.Date = #temp19.Date
	

	while @FromDate <= @ToDate
	begin				
		select @OccRooms=isnull(MIN(OccRooms), 0) from #temp7 where Date = @FromDate
		select @HouseUse=isnull(MIN(HouseUse), 0) from #temp8 where Date = @FromDate
		select @FOCAll=isnull(MIN(FOCAll), 0) from #temp10 where Date = @FromDate
		select @Revenue=isnull(MIN(Revenue), 0) from #temp15 where Date = @FromDate

		select @RM=isnull(MIN(RM), 0) from #temp16 where Date = @FromDate
		select @DayUse = isnull(Min(DayUse), 0) from #temp19 where Date = @FromDate

		set @RoomSales=@OccRooms - @HouseUse
		
		if @RoomSales <> 0 and @RoomSales is not null 
			begin
				if(@RoomSales<> 0)
					set @AvgRate = (case when @averageRoomRateIncludedOthersRoomRevenue = '1' then @Revenue else @RM end)/@RoomSales
				if((@RoomSales - @FOCAll)<> 0)
					set @AvgRate2 = (case when @averageRoomRateIncludedOthersRoomRevenue = '1' then @Revenue else @RM end)/(@RoomSales - @FOCAll)
				else
					set @AvgRate2 = 0
			end
		else
			begin
				set @AvgRate = 0
				set @AvgRate2 = 0
			end
		
		set @RoomOOO = (select Sum(Quantity) from dbo.func_003 (@FromDate,@FromDate)) 
		--select @func_002=Value from Parameter where Parameter='RoomAvailable'
		set @func_002 = dbo.func_002(@FromDate,@FromDate);
		
		if(@func_002 = 0)
			set @func_002 = (select RoomAvible from SP7003 where Date = @FromDate)

		set @RoomAvible = @func_002 - @RoomOOO
		
		Declare @SetOccRooms decimal
		set @SetOccRooms = Convert(decimal(18,2),@OccRooms)
		Declare @SetRoomAvaible decimal
		set @SetRoomAvaible = Convert(decimal(18,2),@RoomAvible)
		
		if @SetRoomAvaible <> 0

			set @PercentOccupancy = (@SetOccRooms/@SetRoomAvaible)*100

		Declare @SetOccRooms1 decimal
		set @SetOccRooms1 = Convert(decimal(18,2),@OccRooms) - Convert(decimal(18,2),@HouseUse) - Convert(decimal(18,2),@FOCAll)
		Declare @SetRoomAvaible1 decimal
		set @SetRoomAvaible1 = Convert(decimal(18,2),@RoomAvible)
		
		--if @SetOccRooms <> 0 or @SetRoomAvaible <> 0
		if @SetRoomAvaible1 <> 0

			set @PercentOccupancy1 = (@SetOccRooms1/@SetRoomAvaible1)*100

		insert into #tmp values(@FromDate,--@DepAdult,@DepChild,@DepRooms,
									--@ArrAdult,@ArrChild,@ArrRooms,
									--@OccAdult,@OccChild,@OccRooms,
									--@HouseUse,@FOCAll,@FOC,@FOCOwner,
									@RoomSales,
									--@ExtraBed,@Revenue,
									@AvgRate,@AvgRate2,@RoomAvible,@PercentOccupancy,@PercentOccupancy1, @RM, @EB, @ER
									,@RoomOOO
									)
		set @FromDate = DATEADD(dd,1,@FromDate)
		
	end 
	
	if(@ShowDivision = 1)
	select #tmp.Date, isnull(#temp3.DepAdult, 0) as DepAdult, isnull(#temp3.DepChild, 0) as DepChild, isnull(#temp3.DepRooms, 0) as DepRooms,
		isnull(#temp4.ArrAdult, 0) as ArrAdult, isnull(#temp4.ArrChild, 0) as ArrChild, isnull(#temp4.ArrRooms, 0) as ArrRooms,
			isnull(#temp5.OccAdult, 0) as OccAdult, isnull(#temp6.OccChild, 0) as OccChild, isnull(#temp7.OccRooms, 0) as OccRooms, 
			isnull(#temp8.HouseUse, 0) as HouseUse, isnull(#temp11.FOC, 0) as FOC, isnull(#temp10.FOCAll, 0) as FOCAll, 
			isnull(#temp12.FOCOwner, 0) as FOCOwner, #tmp.RoomSales, isnull(#temp13.ExtraBed, 0) as ExtraBed, isnull(#temp15.Revenue, 0) as Revenue,
			#tmp.AvgRate, #tmp.AvgRate2, #tmp.RoomAvible, #tmp.PercentOccupancy, #tmp.PercentOccupancy1, isnull(#temp14.BabyCot, 0) as BabyCot, isnull(#temp16.RM, 0) as RM, isnull(#temp17.EB, 0) as EB, isnull(#temp18.ER, 0) as ER
			,OOO, isnull(#temp19.DayUse, 0) as DayUse,  @divisionName as Division
			from #tmp
						left join #temp3 on #tmp.Date = #temp3.Date 
						left join #temp4 on #tmp.Date = #temp4.Date
						left join #temp5 on #tmp.Date = #temp5.Date
						left join #temp6 on #tmp.Date = #temp6.Date
						left join #temp7 on #tmp.Date = #temp7.Date
						left join #temp8 on #tmp.Date = #temp8.Date
						left join #temp10 on #tmp.Date = #temp10.Date
						left join #temp11 on #tmp.Date = #temp11.Date
						left join #temp12 on #tmp.Date = #temp12.Date
						left join #temp13 on #tmp.Date = #temp13.Date
						left join #temp14 on #tmp.Date = #temp14.Date
						left join #temp15 on #tmp.Date = #temp15.Date
						left join #temp16 on #tmp.Date = #temp16.Date
						left join #temp17 on #tmp.Date = #temp17.Date
						left join #temp18 on #tmp.Date = #temp18.Date
						left join #temp19 on #tmp.Date = #temp19.Date
	else 
	select #tmp.Date, isnull(#temp3.DepAdult, 0) as DepAdult, isnull(#temp3.DepChild, 0) as DepChild, isnull(#temp3.DepRooms, 0) as DepRooms,
		isnull(#temp4.ArrAdult, 0) as ArrAdult, isnull(#temp4.ArrChild, 0) as ArrChild, isnull(#temp4.ArrRooms, 0) as ArrRooms,
			isnull(#temp5.OccAdult, 0) as OccAdult, isnull(#temp6.OccChild, 0) as OccChild, isnull(#temp7.OccRooms, 0) as OccRooms, 
			isnull(#temp8.HouseUse, 0) as HouseUse, isnull(#temp11.FOC, 0) as FOC, isnull(#temp10.FOCAll, 0) as FOCAll, 
			isnull(#temp12.FOCOwner, 0) as FOCOwner, #tmp.RoomSales, isnull(#temp13.ExtraBed, 0) as ExtraBed, isnull(#temp15.Revenue, 0) as Revenue,
			#tmp.AvgRate, #tmp.AvgRate2, #tmp.RoomAvible, #tmp.PercentOccupancy, #tmp.PercentOccupancy1, isnull(#temp14.BabyCot, 0) as BabyCot, isnull(#temp16.RM, 0) as RM, isnull(#temp17.EB, 0) as EB, isnull(#temp18.ER, 0) as ER
			,OOO
			from #tmp
						left join #temp3 on #tmp.Date = #temp3.Date 
						left join #temp4 on #tmp.Date = #temp4.Date
						left join #temp5 on #tmp.Date = #temp5.Date
						left join #temp6 on #tmp.Date = #temp6.Date
						left join #temp7 on #tmp.Date = #temp7.Date
						left join #temp8 on #tmp.Date = #temp8.Date
						left join #temp10 on #tmp.Date = #temp10.Date
						left join #temp11 on #tmp.Date = #temp11.Date
						left join #temp12 on #tmp.Date = #temp12.Date
						left join #temp13 on #tmp.Date = #temp13.Date
						left join #temp14 on #tmp.Date = #temp14.Date
						left join #temp15 on #tmp.Date = #temp15.Date
						left join #temp16 on #tmp.Date = #temp16.Date
						left join #temp17 on #tmp.Date = #temp17.Date
						left join #temp18 on #tmp.Date = #temp18.Date
	--delete temporary tables

	drop table #temp19
	drop table #temp18
	drop table #temp17
	drop table #temp16
	drop table #temp15
	drop table #temp14
	drop table #temp13
	drop table #temp12
	drop table #temp11
	drop table #temp10
	drop table #temp9
	drop table #temp8
	drop table #temp7
	drop table #temp6
	drop table #temp5
	drop table #temp4
	drop table #temp3
	drop table #temp2
	drop table #temp1
	drop table #tmp
end




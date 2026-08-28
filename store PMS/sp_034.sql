USE [ProVistaDTXHotel]
GO

/****** Object:  StoredProcedure [dbo].[sp_034]    Script Date: 8/27/2026 4:30:00 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO



--exec  [sp_034] '2025/04/28','2025/04/28',1,'','','','','','','','','','','',50,1


CREATE   proc [dbo].[sp_034](@fromDate date, @toDate date, @all bit, @status varchar(30), @bkno bigint, @refCode varchar(30), @bookingName nvarchar(200), @bookingStatus varchar(30), @contact varchar(50), @company int, @marketSegment int, @sourceCode int, @regDate datetime, @userSale varchar(20), @pageSize int, @pageNumber int)
 as
begin
	declare @dbMaster varchar(50) = (select Value from SP1600 where Parameter = 'DBMaster')
	declare @Sql nvarchar(max)=''

	declare @beginDateBill date = '1753-1-1'
	declare @endDateBill date = '9999-12-31'

	Create table #tableUser(
					Username varchar(20),
					FullName nvarchar(200))

	set @Sql = ' use ['+@dbMaster+']
									insert into #tableUser
									select Username, FullName from SP1321'
							exec (@Sql)

    declare @systemDate date =(select SystemDate from SP1500);
	if(@all=1)
		begin
			set @fromDate = (select min(arrivaldate) from sp2100 where  (room is null or room not like '0%'))
			set @toDate = (select max(DepartureDate) from sp2100 where  (room is null or room not like '0%'))
		end
	else
		begin
			select @beginDateBill = min(ArrivalDate), @endDateBill = max(dateadd(day, NumOfDays, ArrivalDate))
			from sp2000
			where ArrivalDate between @fromDate and @toDate or (ArrivalDate + NumOfDays) between @fromDate and @toDate or (ArrivalDate < @fromDate and (ArrivalDate + NumOfDays) > @toDate)
		end

		--old
	select * 
	into #tempBL 
	--from dbo.func_054('1753-1-1', '9999-12-31', '')
	from dbo.func_052(@beginDateBill, dateadd(day,-1,@systemDate), '')
	union all
	select * from dbo.func_052(@systemDate, @endDateBill, '')
	--union all
	--select  '' as RentalRoomId, RegisterID2 , SP3000.Ma as BillIdService, ServiceId, null as RoomRateCode, 
	--		Date, 0 as Quantity,Amount as Total, null as IsRoom, null as DetailId, DepartmentId,
	--		null as IsRoomNight
	--from SP3000 
	--inner join vw_001 bk on RegisterID2 = bk.Ma 
	--where RentalRoomId1 is null 
	--		and RentalRoomId2 is null 
	--		and Edit = 0
	--		and RegisterID2 is not null 
			--and Date between @fromDate and  @toDate

	--select * from #tempBL
	--where BookingId = '104'

	select pt.BookingId 
	into #temp
	from #tempBL
	inner join SP2100 pt on RentalRoomId = pt.Ma
	where ServiceId = 'RM' --and IsRoomNight = 1 
	and Date between pt.ArrivalDate and pt.CheckoutDate 
	and (pt.Status = 1 
	--or (Date < @systemDate and pt.Status in (1,2))
	) 
	and pt.CheckoutDate != Date 

	--old
	--select isnull(RegisterID2,pt.BookingId) as RegisterID2,SUM(Amount) as Amount
	--into #tmpPayment
	--from SP3002 hd
	--	left join SP2100 pt on pt.Ma = hd.RentalRoomId2
	--where Edit = 0 and Amount > 0 
	--group by RegisterID2,BookingId

	select isnull(RegisterID2,pt.BookingId) as RegisterID2,SUM(Amount) as Amount
	into #tmpPayment
	from SP3002 hd
		left join SP2100 pt on pt.Ma = hd.RentalRoomId2
	where Edit = 0 and Amount > 0 and hd.Pack2 = 'DPR'
	group by RegisterID2,BookingId


	select bl.BookingId,pt.Room,MIN(Date) as FromDate,MAX(Date) as ToDate,SUM(bl.Total) as Total
	into #tmpRoom
	from #tempBL bl
	left join SP2100 pt on pt.Ma = bl.RentalRoomId
	where --IsRoomNight = 1 and 
	ServiceId = 'RM'
	group by bl.BookingId,Total,Room


	select  *
	into #tempBK
	from (
	select 
	dk.Ma, dk.BookingCode, dk.BookingName, ct.Company,mk.MarketSegment  as MarketSegment, dk.ArrivalDate, dk.ArrivalDate + dk.NumOfDays as DepartureDate, 
	isnull(user1.FullName, dk.Username) Username,isnull((select SUM(Total) from #tempBL where BookingId = dk.Ma ),0) as Total, isnull(dps.Amount,0) as Deposite , dk.Status,ttdk.English as FirstNameStatus,
	dk.BookingStatus as StatusCode, tt.BookingStatusName as StatusName, dk.Note, dk.BookingDate , dk.Contact,
	isnull(user2.FullName, dk.SalesPerson) as UserSale,isnull(bk.Phone, '') Phone,isnull(bk.Address, '') Address,
	--hd.ROom,
	--hd.Total as TotalBill, 
	--Quantity,
	--FromDate,
	--ToDate,
	case when dk.ArrivalDate between @fromDate and @toDate and dk.Status = 0 then 1 else 0 end as Arrival,
	case when (dk.ArrivalDate + dk.NumOfDays) between @fromDate and @toDate and dk.Status in(1,0) then 1 else 0 end Departure,
	case when  dk.ArrivalDate between @fromDate and @toDate and dk.Status in (1,2) then 1 else 0 end CheckIn,
	case when (  (dk.ArrivalDate + dk.NumOfDays) between @fromDate and @toDate and dk.Status in (2)) then 1 else 0 end CheckOut,
	case when (@fromDate = @toDate and @toDate = @systemDate) and dk.Status in (1) then 1 else  
			case when dk.Ma in (select * from #temp) then 1 else 0 end end InHouse,
	case when  (hdk.CancelDate between @fromDate and @toDate and dk.Status in (3)) then 1 else 0 end Cancel,
	sc.SourceCode , dk.Email, dk.BookingDate as RegDay, pt.RoomType as CurrentRoomType, case when cr.Ma is null then case when pt.pack4 is null then pt.RoomType else left(pt.pack4, charindex('-', pt.Pack4) -1 ) end else case when cr.pack4 is null then cr.RoomType else left(cr.pack4, charindex('-', cr.Pack4) -1 ) end end as OriginalRoomType, pt.Status as StatusPT, dk.NumOfDays, pt.Ma as RentalRoomId
	from SP2000 dk
	LEFT JOIN SP2100 as pt ON pt.BookingId = dk.Ma
	left join func_081() as cr on cr.ma_phong_cuoi = pt.Ma
	left join SP1302 ct on ct.Ma = dk.TravelAgency
	left join SP1311 tt on tt.BookingStatusId = dk.BookingStatus
	left join #tableUser user1 on user1.Username = dk.Username
	left join #tableUser user2 on user2.Username = dk.SalesPerson
	left join SP1309 ttdk on ttdk.Ma = dk.Status
	left join SP1308 mk on mk.Ma = dk.MarketSegment
	left join SP1328 bk on bk.Id = dk.Booker
	left join SP8037 sc on sc.Ma = dk.SourceCode
	left join SP8053 hdk on hdk.Ma = dk.Ma
	left join ( select RegisterID2 ,SUM(Amount) as Amount 
				from #tmpPayment hd
				group by RegisterID2
				) dps on dps.RegisterID2 = dk.Ma
	left join (select BookingId,STRING_AGG(Room,',') as Room,FromDate,ToDate,Total,COUNT(*) as Quantity
				from #tmpRoom bl
				group by bl.BookingId,Total,FromDate,ToDate
				) as hd on hd.BookingId = dk.Ma		
	where (1 = @all or dk.ArrivalDate between @fromDate and @toDate or (dk.ArrivalDate+dk.NumOfDays) between @fromDate and @toDate or (dk.ArrivalDate < @fromDate and (dk.ArrivalDate + dk.NumOfDays) > @toDate) or hdk.CancelDate between @fromDate and @toDate )
	and (pt.Status is null or pt.Status != 100)
	and (@bkno = '' or dk.Ma = @bkno)
	and (@refCode = '' or dk.BookingCode = @refCode)
	and (@bookingName = '' or dk.BookingName like '%'+@bookingName+'%')
	and (@bookingStatus = '' or tt.BookingStatusName = @bookingStatus)
	and (@contact = '' or dk.Contact like '%'+ @contact + '%')
	and (@company = '' or dk.TravelAgency = @company)
	and (@marketSegment = '' or dk.MarketSegment = @marketSegment)
	and (@sourceCode = '' or dk.SourceCode = @sourceCode)
	and (@regDate = '' or dk.BookingDate = @regDate)
	and (@userSale = '' or dk.SalesPerson like '%'+@userSale+'%')
	group by dk.Ma, dk.BookingCode, dk.BookingName, ct.Company,mk.MarketSegment, dk.ArrivalDate, 
	dk.Username, dk.Status,ttdk.English,dk.NumOfDays,dps.Amount,
	dk.BookingStatus, tt.BookingStatusName, dk.Phone, dk.Note, dk.BookingDate, dk.Contact,
	dk.SalesPerson,bk.Phone,bk.Address, sc.SourceCode, dk.Email, hdk.CancelDate, user1.FullName, user2.FullName, pt.RoomType, pt.Pack4,  cr.Ma, cr.Pack4, cr.RoomType,
	--hd.ROom,
	--hd.Total, 
	--Quantity,
	--FromDate,
	--ToDate, 
	pt.Status, pt.Ma) A
	where (@status = 'CheckIn' and CheckIn = 1)
	or (@status = 'CheckOut' and CheckOut = 1)
	or (@status = 'InHouse' and InHouse = 1)
	or (@status = 'Arrival' and Arrival = 1)
	or (@status = 'Departure' and Departure = 1)
	or (@status = 'Cancel' and Cancel = 1)
	or @status = ''

	select dk.Ma, dk.BookingCode, dk.BookingName, Company, MarketSegment, dk.ArrivalDate, DepartureDate, Username,Total, Deposite , dk.Status,FirstNameStatus, StatusCode, StatusName, dk.Note, dk.BookingDate , dk.Contact, UserSale,Phone,Address, 
	--STRING_AGG(Room+' - '+format(TotalBill*Quantity,'N0')+N' đ ('+format(FromDate,'dd/MM/yyyy')+' - '+format(ToDate,'dd/MM/yyyy')+')',' |') as Room, Arrival, Departure, CheckIn, CheckOut, InHouse, Cancel,
	SourceCode , Email,  RegDay, NumOfDays as Night, isnull(STUFF((select concat(', ', ShortName , ' (',count(*),')')
	from #tempBK as t2
	left join SP1100 lp on t2.CurrentRoomType = lp.Ma
	where dk.Ma = t2.Ma and StatusPT in (0,1,2,4,100)
	group by ShortName, lp.Orders
	order by lp.Orders
	for xml path(''),type).value('.', 'nvarchar(max)'),1,2,''), '') CurrentRoomTypeString,
	isnull(STUFF((select concat(', ', ShortName , ' (',count(*),')')
	from #tempBK as t2
	left join SP1100 lp on t2.OriginalRoomType = lp.Ma
	where dk.Ma = t2.Ma and StatusPT in (0,1,2,4,100)
	group by ShortName, lp.Orders
	order by lp.Orders
	for xml path(''),type).value('.', 'nvarchar(max)'),1,2,''), '') OriginalRoomTypeString, count(*) over() as TotalRow 
	into #tempTotal 
	from #tempBK dk
	group by dk.Ma, dk.BookingCode, dk.BookingName, Company, MarketSegment, dk.ArrivalDate, DepartureDate, Username,Total, Deposite , dk.Status,FirstNameStatus, StatusCode, StatusName, dk.Note, dk.BookingDate , dk.Contact, UserSale,Phone,Address, Arrival, Departure, CheckIn, CheckOut, InHouse, Cancel,
	SourceCode , Email,  RegDay, NumOfDays
	

	--if(@all = 1)
		select * from #tempTotal
		order by Ma desc
	--else
	--	select * from #tempTotal
	--	order by Ma desc
	--	offset (@pageSize * (@pageNumber - 1)) rows
	--	fetch next @pageSize rows only

	select pt.BookingId, lp.ShortName as RoomType, count(distinct pt.Ma) as RoomNum, (select sum(Adult) from sp2100 where BookingId = pt.BookingId and RoomType = pt.RoomType and ArrivalDate = pt.ArrivalDate and CheckoutDate = pt.CheckoutDate and pt.Rate = Rate and isnull(pt.RoomRateCode, '') = isnull(RoomRateCode, '') and Status in (0,1,2,4,100) group by BookingId, Rate, ArrivalDate, CheckoutDate,isnull(RoomRateCode, ''), RoomType) as GuestNum,  (select sum(Child) from sp2100 where BookingId = pt.BookingId and RoomType = pt.RoomType and ArrivalDate = pt.ArrivalDate and CheckoutDate = pt.CheckoutDate and pt.Rate = Rate and isnull(pt.RoomRateCode, '') = isnull(RoomRateCode, '') and Status in (0,1,2,4,100) group by BookingId, Rate, ArrivalDate, CheckoutDate,isnull(RoomRateCode, ''), RoomType) as ChildNum, pt.ArrivalDate, pt.CheckoutDate, pt.RoomRateCode,  pt.Rate,   sum(func.Total) as RoomTotal 
	from #tempBL func 
	left join SP2100 pt on pt.Ma = func.RentalRoomId 
	left join SP2000 dk on dk.Ma = pt.BookingId
		left join SP8053 hdk on hdk.Ma = dk.Ma
	left join SP1100 lp on lp.Ma = pt.RoomType
	where (func.BookingId in (select Ma from #tempTotal))
	group by lp.ShortName, pt.BookingId, pt.Rate, pt.ArrivalDate, pt.CheckoutDate, pt.RoomRateCode, pt.RoomType
	order by pt.BookingId

	drop table #tempBK
	drop table #tempTotal
	drop table #temp
	drop table #tempBL
	drop table #tmpPayment
	drop table #tmpRoom
	drop table #tableUser
end

GO


Changed database context to 'ProVistaNavyHotel'.
--exec sp_155 '2026/07/19','2026/07/19','',1,'14:00','13:59'
CREATE   procedure [dbo].[sp_155] 
(
@DateFrom date,
@DateTo date,
@Sale varchar(max) = '',
@IsViewByTime bit = 0,
@FromTime char(5),
@ToTime char(5)
)
as
begin

--declare @DateFrom date ='2022/12/10'
--declare @DateTo date='2022/12/10'
declare @Prefix varchar(100) = (select PrefixBookingId from sp1322)
declare @Revenue varchar(50) = (select Value from SP1600 where Parameter = 'Revenue')

DECLARE 
	@FromDate datetime, 
	@ToDate datetime,
	@MinDate datetime,
	@MaxDate datetime
	--@FromTime datetime = '14:00'
	
    SET @FromDate = CAST(CAST(case when @IsViewByTime = 1 then Dateadd(day, -1, cast(@DateFrom as date)) else @DateFrom end AS DATE) AS DATETIME) + @FromTime;
	SET @ToDate = CAST(CAST(@DateTo AS DATE) AS DATETIME) + @ToTime;



--set @MinDate = (select min(ArrivalDate) from #tempBooking)
--set @MaxDate = (select max(DepartureDate) from #tempBooking)

select *
into #tmpBL
from dbo.func_054(@DateFrom,@DateTo,'')
--where ServiceId in(select Data from dbo.func_061(@Revenue, ','))

select Ma, ArrivalDate, dateadd(day, NumOfDays, ArrivalDate) as DepartureDate , IsAvailability, SalesPerson
into #tempBooking
from vw_001
where ma in (select BookingId from #tmpBL)

select RentalRoomId, #tmpBL.BookingId, #tmpBL.ServiceId, #tmpBL.RoomRateCode, #tmpBL.Total
into #tmpRoom
from #tmpBL 
inner join #tempBooking dk on dk.Ma = #tmpBL.BookingId
where IsAvailability = 1 and IsRoomNight = 1 and ServiceId = 'RM'
-- where ServiceId = 'RM' --and IsRoomNight = 1 --and IsAvailability = 1

select BookingId, COUNT(*) as Total
	into #tmpFOC
	from #tmpRoom
	where (isnull(RoomRateCode,'') like '%FOC' or (Total = 0 and isnull(RoomRateCode,'') not like '%HU' )) and ServiceId = 'RM'
	group by BookingId



select RentalRoomId, bl.BookingId, Total,(case when isnull(bl.Promotion, pt.pack3) is not null and cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) > 0 and Total <> 0 then
case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.ValueType') = 'percent' then case when cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) = 100 then pt.Rate else  (Total / (100 - cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end)) * 100)  end
else Total + cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end) end
else Total end) as OriginalAmount , SalesPerson, ServiceId, DepartmentId
into #tmpRevenue
from #tmpBL bl 
inner join #tempBooking dk on dk.Ma = bl.BookingId
left join SP2100 pt on pt.Ma = bl.RentalRoomId

union all

select '',RegisterId2,sum(Amount), sum(Amount), SalesPerson, ServiceId, DepartmentId
from SP3000 
left join SP2000 dk on dk.Ma = RegisterId2
where RentalRoomId1 is null 
		and RentalRoomId2 is null 
		and RegisterId2 is not null 
		and Date between @DateFrom and @DateTo
		--and ServiceId in(select Data from dbo.func_061(@Revenue, ','))
		and Edit = 0
		and @Sale = '' or @Sale = SalesPerson
group by RegisterId2, SalesPerson, ServiceId, DepartmentId

--select isnull(isnull(bk1.Ma, bk2.Ma), 0) as BookingId, isnull(isnull(bk1.BookingName, bk2.BookingName), '') BookingName, isnull(isnull(bk1.ArrivalDate, bk2.ArrivalDate), null) ArrivalDate, isnull(isnull(bk1.ArrivalDate + bk1.NumOfDays, bk2.ArrivalDate + bk2.NumOfDays), null) DepartureDate , isnull(bk1.SalesPerson, isnull(bk2.SalesPerson, tt.Username)) as SalesPerson, isnull(isnull(bk1.TravelAgency, bk2.TravelAgency), '') CompanyId, isnull(isnull(bk1.MarketSegment, bk2.MarketSegment), null) MarketSegment, isnull(isnull(bk1.Username, bk2.Username), '') UserCreated ,hddvct.Amount as Amount, hddvct.DepartmentId, hddvct.ServiceId, hddv.Outlet
--	into #tmpRevenueTotal
--	from sp3001 hddvct 
--	left join sp3000 hddv on hddv.ma = hddvct.BillServiceId
--	left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID order by Date desc)
--	left join SP2000 bk1 on hddv.RegisterID2 = bk1.Ma
--	left join SP2100 pt on pt.Ma = hddv.RentalRoomId2
--	left join SP2000 bk2 on pt.BookingId = bk2.Ma
--	left join SP1302 on SP1302.Ma = isnull(isnull(bk1.TravelAgency, bk2.TravelAgency), tt.CompanyId2)
--	where cast(hddv.Date as DateTime) + hddv.OpenTime between @FromDate and @ToDate and (hddv.PaymentID is null or hddv.PaymentID  in ( select PaymentID from SP3002 tt where tt.PaymentMethod not in (select Ma from SP1326 where HTMienPhi = 1) and tt.Edit=0) and hddv.Edit = 0)
--	and (@Sale = '' or @Sale = isnull(bk1.SalesPerson, isnull(bk2.SalesPerson, tt.Username)))


	select BookingId, SalesPerson, sum(Total) as Total
	into #tmpRoomRevenue
	from #tmpRevenue
	where ServiceId in (select ServiceId from SP1305 where DepartmentId = 'FO') or ServiceId in ('RM', 'BF')
	group by BookingId, SalesPerson

	select BookingId, SalesPerson, sum(Total) as Total
	into #tmpFbRevenue
	from #tmpRevenue
	where isnull(DepartmentId,'') = 'FB' and ServiceId in (select Ma from Sp1306 where Replace(Service, ' ','') not in  ('Showroom','SPA'))
	group by BookingId, SalesPerson

	select BookingId, SalesPerson, sum(Total) as Total
	into #tmpSpaRevenue
	from #tmpRevenue
	where isnull(DepartmentId,'') = 'FB' and ServiceId in (select Ma from Sp1306 where Replace(Service, ' ','') = 'SPA')
	group by BookingId, SalesPerson

	select BookingId, SalesPerson, sum(Total) as Total
	into #tmpSrRevenue
	from #tmpRevenue
	where isnull(DepartmentId,'') = 'FB' and ServiceId in (select Ma from Sp1306 where Replace(Service, ' ','') = 'Showroom')
	group by BookingId, SalesPerson

	select BookingId, SalesPerson, sum(Total) as Total
	into #tmpOtherRevenue
	from #tmpRevenue
	where isnull(ServiceId,'') in (select ServiceId from sp1305 where DepartmentId = 'HK')
	group by BookingId, SalesPerson


select Ma as BookingId, BookingName, ArrivalDate, dk.ArrivalDate+dk.NumOfDays as DepartureDate, SalesPerson, TravelAgency, MarketSegment, Username as UserCreated
into #tempData
from Sp2000 dk
join #tmpRoom bl on bl.BookingId = dk.Ma
group by Ma, BookingName, ArrivalDate, NumOfDays, SalesPerson, TravelAgency, MarketSegment, Username

--select * from #tempData
--select * from #tmpRoom where bookingid = 191

select case when dk.BookingId = 0 then 'FB/SR' else @Prefix+ cast(dk.BookingId as varchar) end as BKK, dk.BookingName, dk.ArrivalDate, dk.DepartureDate as DepartureDate, 
sum(case when bl.ServiceId = 'RM' then 1 else 0 end) as RoomNight, isnull(foc.Total, 0) as FOC, max(case when bl.ServiceId = 'RM' then pt.Adult else 0 end) as Adult, max(case when bl.ServiceId = 'RM' then pt.Child else 0 end) as Child, dk.SalesPerson, (select SUM(Total) from #tmpRevenue where #tmpRevenue.BookingId = dk.BookingId) as Rev, (select SUM(OriginalAmount) from #tmpRevenue where #tmpRevenue.BookingId = dk.BookingId) as OriginalAmount, ct.Company, mk.MarketSegment, round(isnull(room.Total, 0), 0) as RoomRevenue, round(isnull(fb.Total, 0), 0) as FbRevenue, round(isnull(spa.Total, 0), 0) as SpaRevenue, round(isnull(sr.Total, 0), 0) as SrRevenue, round(isnull(Other.Total, 0),0) as OtherRevenue, dk.UserCreated
from #tempData dk
left join #tmpRoom bl on bl.BookingId = dk.BookingId
left join (select BookingId, sum(Adult) as Adult, sum(Child) as Child from Sp2100 where status not in (3,4) group by BookingId) pt on pt.BookingId = bl.BookingId
left join #tmpFOC foc on foc.BookingId = dk.BookingId
left join #tmpRoomRevenue room on room.BookingId = dk.BookingId and room.SalesPerson = dk.SalesPerson
left join #tmpFbRevenue fb on fb.BookingId = dk.BookingId and fb.SalesPerson = dk.SalesPerson
left join #tmpSpaRevenue spa on spa.BookingId = dk.BookingId and spa.SalesPerson = dk.SalesPerson
left join #tmpSrRevenue sr on sr.BookingId = dk.BookingId and sr.SalesPerson = dk.SalesPerson
left join #tmpOtherRevenue other on other.BookingId = dk.BookingId and other.SalesPerson = dk.SalesPerson
left join SP1302 ct on ct.ma = dk.TravelAgency
left join SP1308 mk on mk.ma = dk.MarketSegment
where (@Sale = '' or @Sale = dk.SalesPerson) 
group by dk.BookingId, dk.BookingName, dk.ArrivalDate,dk.DepartureDate , dk.SalesPerson,ct.Company, mk.MarketSegment,foc.Total, room.Total, fb.Total, other.Total, dk.UserCreated, sr.Total, spa.Total
order by dk.ArrivalDate



drop table #tmpBL
drop table #tmpRoom
drop table #tempData
drop table #tmpFbRevenue
drop table #tmpSpaRevenue
drop table #tmpFOC 
drop table #tmpOtherRevenue
drop table #tmpRevenue
--drop table #tmpRevenueTotal
drop table #tmpRoomRevenue
drop table #tmpSrRevenue
drop table #tempBooking

end


(1 rows affected)

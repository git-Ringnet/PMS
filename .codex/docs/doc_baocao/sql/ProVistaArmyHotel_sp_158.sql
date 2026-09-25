Changed database context to 'ProVistaArmyHotel'.

--exec sp_158 '2026/06/24','2026/06/24','',1,'14:00','13:59'
CREATE   procedure [dbo].[sp_158] 
(
@fDate date,
@tDate date,
@Sale varchar(max) = '',
@IsViewByTime bit = 0,
@FromTime char(5),
@ToTime char(5)
) as
begin

--DECLARE @fDate DATE = '2023/01/16' , @tDate DATE = '2023/01/16',@Sale varchar(max) = ''

DECLARE @RoomOOO int = (select sum(Quantity) from dbo.func_003 (@fDate,@tDate)) 
DECLARE @func_002 int = dbo.func_002(@fDate,@tDate)
DECLARE @RAV int = @func_002 - @RoomOOO
DECLARE @Revenue varchar(50) = (select Value from SP1600 where Parameter = 'Revenue')

DECLARE 
	@FromDate datetime, 
	@ToDate datetime,
	@MinDate datetime,
	@MaxDate datetime
	--@FromTime datetime = '14:00'
	
    SET @FromDate = CAST(CAST(case when @IsViewByTime = 1 then Dateadd(day, -1, cast(@fDate as date)) else @fDate end AS DATE) AS DATETIME) + @FromTime;
	SET @ToDate = CAST(CAST(@tDate AS DATE) AS DATETIME) + @ToTime;

select Ma, ArrivalDate, dateadd(day, NumOfDays, ArrivalDate) as DepartureDate , IsAvailability, SalesPerson
into #tempBooking
from vw_001
where ArrivalDate between @fDate and @tDate

set @MinDate = (select min(ArrivalDate) from #tempBooking)
set @MaxDate = (select max(DepartureDate) from #tempBooking)

--select top (DATEDIFF(day, @fDate,@tDate)+1)
--DATEADD(day,row_number() over (order by a.object_id) -1, @fDate)  as [Date],CAST(0 as decimal(18,4)) as [%OCC], 
--@RAV as RoomAvailable, 0 as FOC,0 as Arr
--into #tempDate
--from sys.all_objects a cross join sys.all_objects b;

select billserviceId, sum(TaxAmount) TaxAmount 
into #tempTaxAmount
from Sp3001 group by BillServiceId


select * into 
#tempBalanceRev
from dbo.func_054(@MinDate,@MaxDate,'') bl
left join #tempTaxAmount tax on tax.BillServiceId = bl.BillIdService
where BookingId != 0
--where  ServiceId in(select Data from dbo.func_061(@Revenue, ','))




select bl.*,(case when isnull(bl.Promotion, pt.pack3) is not null and cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) > 0 and Total <> 0 then
case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.ValueType') = 'percent' then case when cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) = 100 then pt.Rate else  (Total / (100 - cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end)) * 100)  end
else Total + cast(JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.PromotionValue') as float) * (case when JSON_VALUE(isnull(bl.Promotion, pt.pack3), '$.Type') = 'discount' then 1 else -1 end) end
else Total end) as OriginalAmount 
into #tempBalanceRevRoom 
from #tempBalanceRev bl
left join SP2100 pt on pt.Ma = bl.RentalRoomId
--where ServiceId in(select Data from dbo.func_061(@Revenue, ','))

--select * from #tempBalanceRev

--select * from #tempBalanceRevRoom

select bl.* into 
#tempBalance 
from #tempBalanceRevRoom bl
join vw_001 vw on vw.Ma = bl.BookingId
where IsAvailability = 1 and ServiceId in(select Data from dbo.func_061(@Revenue, ','))


-- RoomNight
select  isnull(dk.SalesPerson,'') as SalesPerson, count(*) as NoOfNight
into #tempRoomNight
from #tempBalance bl
inner join #tempBooking dk on dk.Ma = bl.BookingId
where IsRoomNight = 1 and (@Sale = '' or @Sale = SalesPerson)
group by SalesPerson

--Revenue
--select bl.Date, isnull(dk.SalesPerson,'') as SalesPerson , 0 as NoOfNight, SUM(bl.Total) as Rev, Sum(isnull(room.Total, 0)) as RevRoom, Sum(bl.TaxAmount) as TaxAmount, Sum(isnull(room.OriginalAmount, 0)) as OriginalAmount
--into #tempRevenue
--from  #tempBalanceRev bl
--left join SP2000 dk on dk.Ma = bl.BookingId
--left join #tempBalanceRevRoom room on room.BookingId = bl.BookingId and room.RentalRoomId = bl.RentalRoomId and room.ServiceId =  bl.ServiceId and room.Date = bl.Date
--where @Sale = '' or @Sale = SalesPerson
--group by bl.Date, SalesPerson

--select isnull(bk1.SalesPerson, isnull(bk2.SalesPerson, tt.Username)) as SalesPerson, sum(hddvct.Amount) as Amount , sum(hddvct.TaxAmount) as TaxAmount
--	into #tmpRevenueTotal
--	from sp3001 hddvct 
--	left join sp3000 hddv on hddv.ma = hddvct.BillServiceId
--	left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hddv.PaymentID order by Date desc)
--	left join SP2000 bk1 on hddv.RegisterID2 = bk1.Ma
--	left join SP2100 pt on pt.Ma = hddv.RentalRoomId2
--	left join SP2000 bk2 on pt.BookingId = bk2.Ma
--	where cast(hddv.Date as datetime) + hddv.OpenTime between @FromDate and @ToDate and (hddv.PaymentID is null or hddv.PaymentID  in ( select PaymentID from SP3002 tt where tt.PaymentMethod not in (select Ma from SP1326 where HTMienPhi = 1) and tt.Edit=0) and hddv.Edit = 0)
--	and (@Sale = '' or @Sale = isnull(bk1.SalesPerson, isnull(bk2.SalesPerson, tt.Username)))
--	group by  isnull(bk1.SalesPerson, isnull(bk2.SalesPerson, tt.Username))

select isnull(dk.SalesPerson,dk.SalesPerson) as SalesPerson , 0 as NoOfNight, Sum(bl.Total) as Rev, Sum(isnull(bl.Total, 0)) as RevRoom, SUM(bl.TaxAmount) as TaxAmount, Sum(isnull(bl.OriginalAmount, 0)) as OriginalAmount
into #tempRevenue
from  #tempBalanceRevRoom bl
inner join #tempBooking dk on dk.Ma = bl.BookingId
--full outer join #tmpRevenueTotal total on  total.SalesPerson = dk.SalesPerson
where @Sale = '' or @Sale = dk.SalesPerson
group by isnull(dk.SalesPerson,dk.SalesPerson)


--FOC
select isnull(dk.SalesPerson,'') as SalesPerson , count(*) as FOC
into #tempFOC
from  #tempBalance bl
left join SP2000 dk on dk.Ma = bl.BookingId
where isnull(RoomRateCode,'') not like '%HU' and Total = 0 and (@Sale = '' or @Sale = SalesPerson)
group by  SalesPerson


--Update RoomNight
update #tempRevenue set NoOfNight = #tempRoomNight.NoOfNight
from #tempRoomNight
where #tempRoomNight.SalesPerson = #tempRevenue.SalesPerson 

--insert into #tempRevenue
--select isnull(dk.SalesPerson,'') as SalesPerson , 0, sum(0) as Rev, sum(case when ServiceId = 'CN' then Amount else 0 end) as RevRoom, sum(0) as TaxAmount, sum(case when ServiceId = 'CN' then Amount else 0 end) as OriginalAmount
--from SP3000 
--left join SP2000 dk on dk.Ma = RegisterId2
--left join #tempTaxAmount tax on tax.BillServiceId = sp3000.Ma
--where RentalRoomId1 is null 
--		and RentalRoomId2 is null 
--		and RegisterId2 is not null 
--		and Date between @fDate and @tDate
--		--and  ServiceId in(select Data from dbo.func_061(@Revenue, ','))
--		and Edit = 0
--		and (@Sale = '' or @Sale = SalesPerson)
--group by SalesPerson

select #tempRevenue.SalesPerson,SUM(NoOfNight) as NoOfNight, 0 as FOC, 0 as [%OCC], cast(0 as decimal(18,4)) as ArrOriginal, cast(0 as decimal(18,4)) as Arr,isnull(SUM(Rev), 0) as Rev, round(isnull(sum(Rev) - isnull(SUM(TaxAmount),0),0),0) as RevWithoutTax,@RAV as RoomAvailable, Sum(RevRoom) as RevRoom, Sum(OriginalAmount) as OriginalAmount
into #tempData
from #tempRevenue 
group by #tempRevenue.SalesPerson

update #tempData set [%OCC] = NoOfNight / RoomAvailable
update #tempData set Arr = round(cast(RevRoom as decimal(18,4))/case NoOfNight when 0 then 1 else NoOfNight end, 0)
update #tempData set FOC = isnull((select SUM(FOC) from #tempFOC where #tempData.SalesPerson = #tempFOC.SalesPerson),0)
update #tempData set ArrOriginal = round(cast(OriginalAmount as decimal(18,4))/case when NoOfNight - FOC <= 0 then 1 else NoOfNight - FOC end,0)



--select * from #tempBooking
--select * from #tempRevenue
--select * from #tempBalanceRev
select * from #tempData




drop table #tempTaxAmount
--drop table #tempDate
drop table #tempRoomNight
drop table #tempData
drop table #tempBalance
drop table #tempRevenue
drop table #tempFOC
drop table #tempBalanceRev
drop table #tempBalanceRevRoom
drop table #tempBooking
--drop table #tmpRevenueTotal

end


(1 rows affected)

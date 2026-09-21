



CREATE     proc [dbo].[sp_238](@fromDate date, @toDate date, @companyId int, @fromTime varchar(5), @toTime varchar(5))
 as
begin


declare @division varchar(20); 
set @division = (select  top 1 isnull(PrefixBookingId,'') from sp1322)


declare @RM varchar(max) = 'RM'
declare @ExPer varchar(max) = 'EP' 
declare @EB varchar(max) = 'EB'
--declare @SoBed varchar(max) = 'SB'
declare @ExRCharge varchar(max) = 'EI,LO,ER'
declare @Tour varchar(max) = 'TO'
declare @Transportation varchar(max) = 'PU,DO'
declare @Mis varchar(max) = 'RM,EP,EB,EI,LO,ER,TO,PU,DO,MB,LA,BR,BK,RB,PC,RF,BC,BD,BF,OT,FB'

declare @FB_Beverage varchar(max) ='RB,PC'
declare @BF_Charge varchar(max) = 'BC,BD,BF,FB' 
--declare @FB_Setmenu varchar(max) = 'SM'
declare @FB_Other varchar(max) = 'RF,RB,PC,BC,BD,BF'


declare @Mice varchar(max) = ''
declare @Mice_Other varchar(max) = ''



declare @template varchar(max) = ( select ReportName 
									from SP8060 ac
									left join SP8058 tp on tp.Id = ac.TemplateId
									left join SP8059 rp on rp.Id = ac.ReportId
									where GroupTemplate = 'Total revenue report')

create table #tempHD(
	CodeBooking varchar(100),
	Status int,
	RoomCode varchar(100),
	RoomNumber varchar(100),
	Company nvarchar(100),
	GuestName nvarchar(500),
	Amount decimal(18,2),
	ServiceId varchar(10),
	ServiceIdHD varchar(10),
	Arrival date,
	ArrivalDateBK date,
	Departure date,
	DepartureBK date,
	CheckoutDateBK date,
	CheckoutTime char(5),
	DepartmentId varchar(10),
	Outlet varchar(10),
	MaChung varchar(100),
	DayUse varchar(10),
	PaymentID varchar(10),
)


select bk.ma, 
max(cast(concat(cast(isnull(pt.CheckoutDate, bk.ArrivalDate + bk.NumOfDays) as date), ' ',isnull(pt.CheckoutTime, '12:00')) as datetime)) as DateBooking 
into #tempDateBooking
from sp2000 bk
left join sp2100 pt on bk.ma = pt.BookingId and pt.CheckoutTime = (select top 1 CheckoutTime from SP2100 order by CheckoutTime desc)
group by bk.ma,pt.CheckoutDate,pt.CheckoutTime

if(@template = 'Tulip')
	begin
		insert into #tempHD
		select distinct ( isnull(@division + cast(isnull(hd.RegisterId1,pt.BookingId) as varchar(20)), pt.Room)) as CodeBooking, isnull(pt.Status, dk.Status) as Status,pt.Ma as RoomCode,
		isnull(pt.Room,'') as RoomNumber,
		ct.Company as Company,
		isnull(dk.BookingName, k.FirstName) GuestName,	
		SUM(isnull(hdct.Amount,0)) as Amount, 
		isnull(hdct.ServiceId,'') as ServiceId, 
		isnull(hd.ServiceId,'') as ServiceIdHD, 
		isnull(pt.ArrivalDate, dk.ArrivalDate) as Arrival,
		dk.ArrivalDate as ArrivalDateBK,
		case when pt.Room like '0%' then tt.Date else isnull(pt.CheckoutDate, dk.ArrivalDate + dk.NumOfDays) end as Departure, 
		case when pt.Room like '0%' then tt.Date else dk.ArrivalDate + dk.NumOfDays end as DepartureBK,
		dk.ArrivalDate + dk.NumOfDays as CheckoutDateBK,
		--case when pt.Room like '0%' then tt.OpenTime else isnull( pt.CheckoutTime, cast(t.DateBooking as time)) end as CheckoutTime,	
		case when pt.Room like '0%' then tt.OpenTime else null end as CheckoutTime,
		isnull(hdct.DepartmentId,'') as DepartmentId, 
		isnull(hd.Outlet,'') as Outlet, isnull(hd.CustomerId2,cast(isnull(cast(RegisterId1 as varchar),pt.Ma) as varchar)) as MaChung,
		pt.DayUse, hd.PaymentID
		
		from SP3000 hd
		join SP3001 hdct on hdct.BillServiceId = hd.Ma
		left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hd.PaymentID order by Date desc)
		left join SP2100 pt on pt.Ma = hd.RentalRoomId1
		left join SP2300 k on k.Id = hd.CustomerId2
		left join SP2000 dk on dk.Ma = isnull(RegisterId1,pt.BookingId)
		left join #tempDateBooking t on t.Ma = dk.Ma
		left join SP1302 ct on dk.TravelAgency = ct.Ma
		where isnull(hd.Edit,0) = 0 and hd.ServiceId = 'RM' and (tt.PaymentMethod is null or tt.PaymentMethod not in (select Ma from sp1326 where HTMienPhi = 1))
		and (@companyId = '' or ct.Ma = @companyId)
		group by  hdct.ServiceId,hd.RegisterID2 ,hd.RentalRoomId2, pt.BookingId, pt.Room,
					ct.Company, k.FirstName, dk.BookingName, pt.ArrivalDate, pt.CheckoutDate ,hd.ServiceId,
					hdct.DepartmentId, hd.Outlet, 
					dk.ArrivalDate, dk.NumOfDays, pt.DayUse,hd.CustomerId2, pt.Ma,pt.Status,dk.Status,
					hd.RegisterId1, hd.PaymentID, pt.CheckoutTime, t.DateBooking, tt.Date, tt.OpenTime
		union all
		select distinct( isnull(@division + cast(isnull(hd.RegisterId1,pt.BookingId) as varchar(20)), pt.Room)) as CodeBooking, isnull(pt.Status, dk.Status) as Status,pt.Ma as RoomCode,
		isnull(pt.Room,'') as RoomNumber,
		ct.Company as Company,
		isnull(dk.BookingName, k.FirstName) GuestName,
		
		SUM(isnull(hdct.Amount,0)) as Amount, 
		isnull(hdct.ServiceId,'') as ServiceId, 
		isnull(hd.ServiceId,'') as ServiceIdHD, 
		dk.ArrivalDate as Arrival,
		dk.ArrivalDate as ArrivalDateBK,
		case when pt.Room like '0%' then tt.Date else  dk.ArrivalDate + dk.NumOfDays end as Departure, 
		case when pt.Room like '0%' then tt.Date else dk.ArrivalDate + dk.NumOfDays end as DepartureBK,
		dk.ArrivalDate + dk.NumOfDays as CheckoutDateBK,
		--case when pt.Room like '0%' then tt.OpenTime else isnull( pt.CheckoutTime, cast(t.DateBooking as time)) end as CheckoutTime,
		case when pt.Room like '0%' then tt.OpenTime else null end as CheckoutTime,
		isnull(hdct.DepartmentId,'') as DepartmentId, 
		isnull(hd.Outlet,'') as Outlet, isnull(hd.CustomerId2,cast(isnull(cast(hd.RegisterID1 as varchar),pt.Ma) as varchar)) as MaChung,
		pt.DayUse, hd.PaymentID
		from SP3000 hd
		left join SP3001 hdct on hdct.BillServiceId = hd.Ma
		left join SP3002 tt on tt.Ma = (select top 1 Ma from SP3002 where PaymentId = hd.PaymentID order by Date desc)
		left join SP2100 pt on pt.Ma = hd.RentalRoomId1
		left join SP2300 k on k.Id = hd.CustomerId2
		left join SP2000 dk on dk.Ma = isnull(hd.RegisterID1,pt.BookingId)
		left join #tempDateBooking t on t.Ma = dk.Ma
		left join SP1302 ct on dk.TravelAgency = ct.Ma
		where isnull(hd.Edit,0) = 0 and hd.ServiceId != 'RM' and (tt.PaymentMethod is null or tt.PaymentMethod not in (select Ma from sp1326 where HTMienPhi = 1))
		and (@companyId = '' or ct.Ma = @companyId)
		group by  hdct.ServiceId,hd.RegisterID2 ,hd.RentalRoomId2, pt.BookingId, pt.Room,
					ct.Company, k.FirstName, dk.BookingName, pt.ArrivalDate, pt.CheckoutDate ,hd.ServiceId,
					hdct.DepartmentId, hd.Outlet, 
					dk.ArrivalDate, dk.NumOfDays, pt.DayUse,hd.CustomerId2, pt.Ma,pt.Status,dk.Status,hd.RegisterId1, hd.PaymentID, pt.CheckoutTime, t.DateBooking, tt.Date, tt.OpenTime
		order by CodeBooking;
	end
	else
	begin
		insert into #tempHD
		select ( @division + cast(isnull(hd.RegisterID2,pt.BookingId) as varchar(20))) as CodeBooking, isnull(pt.Status, dk.Status) as Status,pt.Ma as RoomCode,
		isnull(pt.Room,'') as RoomNumber,
		ct.Company as Company,
		dk.BookingName as GuestName, 
		SUM(isnull(hdct.Amount,0)) as Amount, 
		isnull(hdct.ServiceId,'') as ServiceId, 
		isnull(hd.ServiceId,'') as ServiceIdHD, 
		dk.ArrivalDate as Arrival,
		dk.ArrivalDate + dk.NumOfDays as Departure, 
		isnull(pt.CheckoutTime, cast(t.DateBooking as time)) as CheckoutTime,
		isnull(hdct.DepartmentId,'') as DepartmentId, 
		isnull(hd.Outlet,'') as Outlet, isnull(RentalRoomId2,cast(RegisterID2 as varchar)) as MaChung, pt.DayUse, PaymentID

		from SP3000 hd
		join SP3001 hdct on hdct.BillServiceId = hd.Ma
		left join SP2100 pt on pt.Ma = hd.RentalRoomId2
		left join SP2300 k on k.Id = hd.CustomerId2
		left join SP2000 dk on dk.Ma = isnull(RegisterID2,pt.BookingId)
		left join #tempDateBooking t on t.Ma = dk.Ma
		left join SP1302 ct on dk.TravelAgency = ct.Ma


		where isnull(hd.Edit,0) = 0 
		and (@companyId = '' or ct.Ma = @companyId)
		group by  hdct.ServiceId,hd.RegisterID2 ,hd.RentalRoomId2, pt.BookingId,
					ct.Company,dk.BookingName, pt.ArrivalDate, pt.CheckoutDate ,hd.ServiceId,
					hdct.DepartmentId, hd.Outlet, 
					dk.ArrivalDate, dk.NumOfDays, pt.DayUse,pt.Status, dk.Status, pt.Ma, pt.Room, PaymentID, pt.CheckoutTime, t.DateBooking
		order by CodeBooking
	end
	
select CodeBooking, RoomCode, Status, Company,GuestName, isnull(ArrivalDateBK, Arrival) ArrivalDateBK, isnull(CheckoutDateBK, Departure) CheckoutDateBK, 

case when tt.ServiceId in (Select data from dbo.func_061(@RM,',')) /*and isnull(tt.DayUse,0) != 1 */then tt.Amount else 0 end as RoomCharge,
--case when tt.ServiceId in ('RM') and isnull(tt.DayUse,0) = 1 then tt.Amount else 0 end as DayUse,
case when tt.ServiceId in (Select data from dbo.func_061(@EB,',')) then tt.Amount else 0 end as ExtraBed,
case when tt.ServiceId in (Select data from dbo.func_061(@ExPer,',')) then tt.Amount else 0 end as ExtraPerson,
--case when tt.ServiceId in (Select data from dbo.func_061(@SoBed,',')) then tt.Amount else 0 end as SofaBed,
case when tt.ServiceId in (Select data from dbo.func_061(@ExRCharge,',')) then tt.Amount else 0 end as ExtraRoomCharge,
case when tt.ServiceId in (Select data from dbo.func_061(@Tour,',')) then tt.Amount else 0 end as Tour,
case when tt.ServiceId in (Select data from dbo.func_061(@Transportation,',')) then tt.Amount else 0 end as Transportation,
case when tt.ServiceId not in (Select data from dbo.func_061(@Mis,',')) then tt.Amount else 0 end as Miscel,

case when tt.ServiceId ='MB' then tt.Amount else 0 end as Minibar,
case when tt.ServiceId = 'LA' then tt.Amount else 0 end as Laundry,
case when tt.ServiceId IN ('BR','BK') then tt.Amount else 0 end as Broken,

		
case when tt.ServiceId = 'BF' and tt.ServiceIdHD ='RM' then tt.Amount else 0 end as [Breakfast func_061down], 
case when tt.ServiceId in (Select data from dbo.func_061(@BF_Charge,','))  and tt.ServiceIdHD <>'RM'and tt.Outlet='RC' then tt.Amount else 0 end as [Breakfast Charge],
case when tt.ServiceId = 'RF'  and tt.Outlet ='RE'  then tt.Amount else 0 end as [Food_Res], 
case when tt.ServiceId IN (Select data from dbo.func_061(@FB_Beverage,',')) and tt.Outlet='RE' then tt.Amount else 0 end as [Beverage_Res],
--case when tt.ServiceId IN (Select data from dbo.func_061(@FB_Setmenu,',')) and tt.Outlet='RE' then tt.Amount else 0 end as [Set Menu],
case when tt.DepartmentId = 'FB' and tt.Outlet='RE' and tt.ServiceId not in (Select data from dbo.func_061(@FB_Other,','))  then tt.Amount else 0 end as [Other_Res],
		
case when tt.ServiceId = 'RF'  and tt.Outlet='RS' then tt.Amount else 0 end as [Food_Room], 
case when tt.ServiceId IN (Select data from dbo.func_061(@FB_Beverage,',')) and tt.Outlet='RS'   then tt.Amount else 0 end as [Beverage_Room],
case when tt.DepartmentId = 'FB' and tt.Outlet='RS' and tt.ServiceId not in (Select data from dbo.func_061(@FB_Other,',')) then tt.Amount else 0 end as [Other_Room],

		
case when tt.ServiceId = 'RF'  and tt.Outlet='MT' then tt.Amount else 0 end as [Food_Meet], 
case when tt.ServiceId in (Select data from dbo.func_061(@FB_Beverage,',')) and tt.Outlet='MT'then tt.Amount else 0 end as [Beverage_Meet],
case when tt.DepartmentId = 'FB' and tt.Outlet='MT' and tt.ServiceId not in (Select data from dbo.func_061(@FB_Other,',')) then tt.Amount else 0 end as [Other_Meet],

		
case when tt.ServiceId IN (Select data from dbo.func_061(@Mice,',')) then tt.Amount else 0 end as [Mice], 
case when tt.ServiceId IN (Select data from dbo.func_061(@Mice_Other,','))then tt.Amount else 0 end as [Other],



0 as OtherRevenue,

tt.Amount as TotalRevenue,
isnull((select SUM(Amount) from SP3002 where PaymentMethod = 'BT' and ISNULL(cast(RegisterID2 as varchar),RentalRoomId2) = tt.MaChung),0) as BankTransfer,
isnull((select SUM(Amount) from SP3002 where PaymentMethod = 'CD' and ISNULL(cast(RegisterID2 as varchar),RentalRoomId2) = tt.MaChung),0) as CreditCard,
isnull((select SUM(Amount) from SP3002 where PaymentMethod = 'CA' and ISNULL(cast(RegisterID2 as varchar),RentalRoomId2) = tt.MaChung),0) as Cash,
isnull((select SUM(Amount) from SP3002 where PaymentMethod = 'AC' and ISNULL(cast(RegisterID2 as varchar),RentalRoomId2) = tt.MaChung),0) as CityLedger,
isnull((select SUM(Amount) from SP3002 where PaymentMethod = 'BC' and ISNULL(cast(RegisterID2 as varchar),RentalRoomId2) = tt.MaChung),0) as BankTransferTKCN,
isnull((select SUM(Amount) from SP3002 where PaymentMethod not in ('AC','BT','CD','CA','BC') and ISNULL(RentalRoomId2,cast(RegisterID2 as varchar)) = tt.MaChung),0) as OtherPayment,
isnull((select STRING_AGG(PaymentMethod,',') from SP3002  where Edit = 0 and PaymentID = tt.PaymentID),'') as PaymentMethod

into #tempTotal
from #tempHD tt

where cast(concat(DepartureBK, ' ',isnull(CheckoutTime,@fromTime)) as datetime) between cast(concat(@fromDate, ' ',@fromTime) as datetime) and cast(concat(@toDate, ' ',@toTime) as datetime)-- and (RoomNumber like '0%' or status in (2,100))

update #tempTotal set PaymentMethod = (select string_agg(A.Data,',') from (select distinct data from dbo.func_061(#tempTotal.PaymentMethod,',')) A)

select CodeBooking, case when CodeBooking like '0%' then null else ArrivalDateBK end as Arrival, case when CodeBooking like '0%' then null else CheckoutDateBK end as Departure, Company, GuestName,
SUM([RoomCharge]) as [RoomCharge],
--SUM([DayUse]) as [DayUse],
SUM([ExtraBed]) as [ExtraBed],
SUM([ExtraPerson]) as [ExtraPerson],
--SUM([SofaBed]) as [SofaBed],
SUM([ExtraRoomCharge]) as [ExtraRoomCharge],
SUM([Tour]) as [Tour],
SUM([Transportation]) as [Transportation],
SUM([Miscel]) as [Miscel],
SUM([Minibar]) as [Minibar],
SUM([Laundry]) as [Laundry],
SUM([Broken]) as [Broken],
SUM([Breakfast func_061down]) as [Breakfast Splitdown],
SUM([Breakfast Charge]) as [Breakfast Charge],
SUM([Food_Res]) as [Food_Res],
SUM([Beverage_Res]) as [Beverage_Res],
--SUM([Set Menu]) as [Set Menu],
SUM([Other_Res]) as [Other_Res],
SUM([Food_Room]) as [Food_Room],
SUM([Beverage_Room]) as [Beverage_Room],
SUM([Other_Room]) as [Other_Room],
SUM([Food_Meet]) as [Food_Meet],
SUM([Beverage_Meet]) as [Beverage_Meet],
SUM([Other_Meet]) as [Other_Meet],
SUM([Mice]) as [Mice],
SUM([Other]) as [Other],
SUM([OtherRevenue]) as [OtherRevenue],
SUM([TotalRevenue]) as [TotalRevenue],
MAX([BankTransfer]) as [BankTransfer],
MAX([CreditCard]) as [CreditCard],
MAX([Cash]) as [Cash],
MAX([CityLedger]) as [CityLedger],
MAX([BankTransferTKCN]) as [BankTransferTKCN],
MAX([OtherPayment]) as [OtherPayment],
MAX(PaymentMethod) as PaymentMethod
into #tempResult
from #tempTotal
group by CodeBooking, ArrivalDateBK, CheckoutDateBK,Company, GuestName


update #tempResult set OtherRevenue = TotalRevenue - ([RoomCharge]/*+[DayUse]*/+[ExtraBed]+[ExtraPerson]/*+[SofaBed]*/+[ExtraRoomCharge]+[Tour]+[Miscel]+[Minibar]+[Laundry]+[Broken]+[Breakfast Splitdown]+[Breakfast Charge]+[Food_Res]+[Beverage_Res]/*+[Set Menu]*/+[Other_Res]+[Food_Room]+[Beverage_Room]+[Other_Room]+[Food_Meet]+[Beverage_Meet]+[Other_Meet]+[Mice]+[Other]+[OtherRevenue])

select * from #tempResult order by case when CodeBooking like '0%' then 0 else replace(CodeBooking, @division,'') end

drop table #tempHD
drop table #tempTotal
drop table #tempResult

end



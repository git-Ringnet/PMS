SQL_STORED_PROCEDURE 3803 

 
CREATE   proc [dbo].[sp_039](
	@FromDate date,
	@ToDate date,
	@Department varchar(200),
	@User varchar(50),
	@Ca varchar(5),
	@FromTime varchar(5),
	@ToTime varchar(5),
	@Company varchar(20),
	@ViewDatCoc int,
	@ViewAmount0 int,
	@PaymentMethod varchar(100) = ''
)
 as
begin
	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)
	declare @division varchar(20) = (select  top 1 isnull(Division,'') from sp1322)

	;with BillFB as(
		select PaymentID, string_agg(concat(isnull(cast(BillCode as nvarchar(max)), concat(ServiceId, ' - ', hddv.DescriptionServive)), ' - ', format(amount, 'N0')), ', ') as BillCode 
		from sp3000 hddv
		left join SP5000 hdfb on hddv.Ma = hdfb.ServicePayId
		where hddv.edit = 0 and (ServiceId in (select Ma from sp1306 where Department = 'FB')  
		or ServiceId in (select value from string_split((select Service from SP1610 where report = 'NightAuditReport' and DisplayName = 'FBRevenue'), ',')))
		and (DepartmentId = 'FO' or hdfb.PaymentMethod in ('Room','Group')) and PaymentID is not null
		group by PaymentID
	)
	, PaymentAC as (
		select PaymentTableId, sum(Amount) Amount from sp3007 
		where edit =0
		group by PaymentTableId
	)
	select dk.Status as BookingStatus, pt.Status as StatusRoom, dv.InvoiceId as BillID, bcdt.Date,bcdt.Room, bcdt.Guest, bcdt.OpenTime, bcdt.PaymentID, bcdt.Amount, bcdt.Username, bcdt.Description, bcdt.PaymentMethod, bcdt.Department, pt.Room as NumOfRoom, case when bcdt.Amount < 0 then 2 else 
	case when isnull(bcdt.Pack2, '') = '' then '0' else '1' end end as Deposit,case when bcdt.Amount < 0 then N'Ho…n Tr?' else 
	case when isnull(bcdt.Pack2, '') = '' then N'Thu Ngƒn' else N'D?t c?c' end end as ShowDeposit,
		@prefix + cast(case when bcdt.RegisterID2 is null then pt.BookingId else bcdt.RegisterID2 end as varchar(20)) MaBooking, dk.ArrivalDate, dk.ArrivalDate + dk.NumOfDays DepartureDate, httt.FirstName as PaymentMethodName,
		case when bcdt.RegisterID2 is null then k.Title + ' ' + k.FirstName else dk.BookingName end GuestInfo,dk.Provide2,ct.Company, case when bcdt.paymentmethod = 'CD' then replicate('*', len(card.CardId) -4) + RIGHT (card.CardId, 4) else '' end as CardId, fb.BillCode, bcdt.Pack5, isnull(ac.Amount, 0) as PaidAmountAC, @division as Division
	from vw_004 bcdt 
		left join SP3000 dv on dv.Ma = (select top 1 Ma from SP3000 where PaymentId = bcdt.PaymentID)
		left join SP2100 pt on pt.Ma = bcdt.RentalRoomId2 
		left join SP2000 dk on dk.Ma = (case when bcdt.RegisterID2 is null then pt.BookingId else bcdt.RegisterID2 end)
		left join SP2300 k on k.Id = bcdt.CustomerId2
		left join SP1302 ct on dk.TravelAgency=ct.Ma 
		left join SP1326 httt on httt.Ma = bcdt.PaymentMethod
		left join SP8054 card on card.PaymentID = bcdt.PaymentId
		left join BillFB fb on fb.PaymentID = bcdt.PaymentID
		left join PaymentAC ac on ac.PaymentTableId = bcdt.Ma
	where (@User = '' or bcdt.Username = @User)
		and (@Ca = '' or bcdt.Ca = @Ca)
		and (@Department = '' or bcdt.DepartmentId in (select value from string_split(@Department,',')))
		and (@PaymentMethod = '' or bcdt.PaymentMethod in (select value from string_split(@PaymentMethod,',')))
		and (@Ca != '' or @FromTime = '' or @ToTime = '' or bcdt.OpenTime between @FromTime and @ToTime)
		and (@Company in ('', '-1') or isnull(bcdt.CompanyId2, '') = @Company)
		and bcdt.Date between @FromDate and @ToDate
		and bcdt.Edit =0
		
		and (@ViewAmount0 = 1 or bcdt.Amount <> 0)
		and (@ViewDatCoc = 0 or ((bcdt.PaymentID is not null and (bcdt.Pack2 = '' or bcdt.Pack2 is null)) 
				or (bcdt.Pack2 <> '' or bcdt.Pack2 is not null)))
		and (@ViewDatCoc = 1 or (bcdt.PaymentID is not null  and (bcdt.Pack2 = '' or bcdt.Pack2 is null)))
	order by bcdt.Date, OpenTime
end
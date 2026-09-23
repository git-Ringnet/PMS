--select * from sp1610
-- exec sp_293 '20260301', '20260304', 'admin', 'RM'

create   procedure sp_293 (@fromDate date, @toDate date, @user varchar(20) = '', @service varchar(10) = '')
as
begin
	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	;with ServiceSetup as (
		select * from SP1610
		where Report = 'FORevenueReport'
	),
	Lang as (
		select * from SP1602
		where id in (select CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName)) from ServiceSetup)
	)
	select CONCAT(@prefix , isnull(hddv.RegisterId2, hddv.BookingId)) as BookingId, hddv.Date, pt.Room, isnull(hddv.ArrivalDate, dk.ArrivalDate) ArrivalDate,
	isnull (DATEADD(day, hddv.NumOfDays, hddv.ArrivalDate),  DATEADD(day, hddv.NumOfDays, dk.ArrivalDate)) DepartureDate,
	hddv.Guest as GuestName, hddv.DescriptionServive, hddv.OriginalRate, hddv.ServiceChargeAmount, hddv.SpecialTaxAmount, hddv.TaxAmount, hddv.Amount,
	A.PaymentMethod, ct.Company, hddv.OpenTime, B.Description, ss.DisplayName, hddv.ServiceId, dv.Service as FirstNameService,
	isnull(la.EN, CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName))) as Name,
	isnull(la.VI, CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName))) as NameVI
	from vw_018 hddv
	left join SP2000 dk on dk.Ma= hddv.RegisterID2
	left join SP2100 pt on hddv.RentalRoomId2 = pt.Ma
	left join SP1306 dv on hddv.ServiceId = dv.Ma
	left join SP2000 dk1 on dk1.Ma = isnull(dk.Ma,pt.BookingId)
	left join ServiceSetup ss on hddv.ServiceId in (select value from string_split(ss.Service, ','))
	left join Lang la on CONCAT('reportviewer.totalbillreport.', LOWER(ss.DisplayName)) = la.Id
	left join SP1302 ct on ct.Ma = dk1.TravelAgency
	left join (select STRING_AGG(A.PaymentMethod,',') as PaymentMethod, PaymentID 
									from (select distinct PaymentMethod, PaymentID 
											from SP3002 
											where PaymentID is not null 
											group by PaymentID, PaymentMethod
										 ) A group by PaymentID 
									)  A on A.PaymentID = hddv.PaymentID
	left join (select STRING_AGG(B.Description,',') as Description, PaymentID 
									from (select distinct Description, PaymentID 
											from SP3002 
											where PaymentID is not null 
											group by PaymentID, Description
										 ) B group by PaymentID 
									)  B on B.PaymentID = hddv.PaymentID
	where edit = 0 and hddv.date between @fromDate and @toDate
	and ss.DisplayName is not null and hddv.DepartmentId in ('FO','HK')
	and (@service = '' or hddv.ServiceId = @service)
	and (@user = '' or hddv.Username = @user)
end
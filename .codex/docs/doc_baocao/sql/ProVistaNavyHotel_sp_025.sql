Changed database context to 'ProVistaNavyHotel'.





CREATE   proc [dbo].[sp_025]
(
@FromDate date,
@ToDate date,
@User varchar(20),
@OrderBy varchar(20),
@ShowDepDate bit,
@Department varchar(20)= '',
@outlet varchar(20) ='',
@Service varchar(MAX)=''
)
 as
begin
	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	select pt.BookingId, max(pt.Room) Room
	into #tempKhachLe
	from sp2000 dk
	left join sp2100 pt on dk.Ma = pt.BookingId
	where pt.Status in (0,1,2)
	group by pt.BookingId
	having count(*) = 1 and pt.BookingId is not null

	select * 
	into #templang
	from sp1602 
	where id in (select CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName)) from SP1610)

	SELECT 
		Id,
		Report,
		DisplayName,
		LTRIM(RTRIM(s.value)) AS Service,   -- Lo?i b? kho?ng tr?ng d?u/cu?i
		Department
	INTO #tempService
	FROM 
		sp1610
	CROSS APPLY 
		STRING_SPLIT(Service, ',') s
	where DisplayName != 'OtherRevenue' and Report = 'NightAuditReport'
	ORDER BY 
		Id, Service;

	if(@Service = '')
	begin

       declare @sql nvarchar(max)
       set @sql='select ROW_NUMBER() over (order by (select 1)) as STT,vw.Edit,
              vw.Ma,RefId,
               ' + '''' + @prefix +'''' + ' + cast(case when vw.RegisterID2 is not null then vw.RegisterID2 else vw.BookingId end as varchar(20)) as RegisterID2,
              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end as Guest,
			  case when vw.ArrivalDate is null then dk.ArrivalDate else vw.ArrivalDate end as ArrivalDate,
			  case when DATEADD(day, vw.NumOfDays, vw.ArrivalDate) is null then DATEADD(day, dk.NumOfDays, dk.ArrivalDate) else DATEADD(day, vw.NumOfDays, vw.ArrivalDate) end as DepartureDate,              vw.DescriptionServive,vw.OriginalRate,vw.ServiceChargeAmount,vw.TaxAmount,vw.Status,vw.Username,vw.OpenTime,vw.ServiceId,vw.Username,vw.OpenTime,
              vw.ServiceCharge,vw.Tax,vw.SpecialTax
                     ,dv.Service as FirstNameService
                     ,A.PaymentMethod as PaymentMethod, 
					 -- case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end as Outlet, 
					 isnull(sv.DisplayName, ''OtherRevenue'')  as Outlet,
					 -- fbOutlet.Name, 
					 isnull(lang.EN, CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))))  as Name,
					 isnull(lang.VI, CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))))  as NameVI,
					 vw.Date, ct.Company as Company
					 ,isnull(pt.Room, kl.Room) Room,dk.Ma as BookingId, cast(vw.UpdatedDate as Date) as UpdatedDate, B.Description
                     
                     from vw_018 vw
                     left join SP2000 dk on dk.Ma=vw.RegisterID2
					 left join #tempKhachLe kl on kl.BookingId = dk.Ma
                     left join SP2300 k on k.Id=vw.CustomerId2
                     left join SP3003 hd on hd.Ma=vw.InvoiceId
					 left join SP5409 fbOutlet on fbOutlet.OutletId = case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end
					 left join #tempService sv on sv.Service = vw.ServiceId
					 left join #tempLang lang on CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))) = lang.id
					 left join SP2100 pt on isnull(vw.RentalRoomId2, vw.RentalRoomId1) = pt.Ma
					 left join SP1306 dv on vw.ServiceId = dv.Ma
					 left join SP2000 dk1 on dk1.Ma = isnull(dk.Ma,pt.BookingId)
					 left join SP1302 ct on ct.Ma = dk1.TravelAgency
					 left join (select STRING_AGG(A.PaymentMethod,'','') as PaymentMethod, PaymentID 
								from (select distinct PaymentMethod, PaymentID 
										from SP3002 
										where PaymentID is not null 
										group by PaymentID, PaymentMethod
									 ) A group by PaymentID 
								)  A on A.PaymentID = vw.PaymentID
					left join (select STRING_AGG(B.Description,'','') as Description, PaymentID 
								from (select distinct Description, PaymentID 
										from SP3002 
										where PaymentID is not null 
										group by PaymentID, Description
									 ) B group by PaymentID 
								)  B on B.PaymentID = vw.PaymentID
                           where 1=1 '
       if @ShowDepDate = 1
              set @sql+=' and cast(vw.Date as Date) between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''''
       else
              set @sql+=' and cast(vw.Date as Date) = '''+cast(@FromDate as varchar(10))+''''
       if @User is not null and @User <> ''
              set @sql+=' and vw.username like '''+@User+''''
       set @sql += ' and vw.DepartmentId like ''%'+@Department+'%'''
       set @sql += ' and case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end like ''%'+@outlet+'%'''
       set @sql+=' order by vw.UpdatedDate, vw.'+@OrderBy+''
       
       print @sql
       exec sp_executesql @sql

	end
	else
	begin

		declare @sql1 nvarchar(max)
       set @sql1='select ROW_NUMBER() over (order by (select 1)) as STT,vw.Edit,
              vw.Ma,RefId,
			   ' + '''' + @prefix +'''' + ' + cast(case when vw.RegisterID2 is not null then vw.RegisterID2 else vw.BookingId end as varchar(20)) as RegisterID2,
              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end as Guest,
              case when vw.ArrivalDate is null then dk.ArrivalDate else vw.ArrivalDate end as ArrivalDate,
			  case when DATEADD(day, vw.NumOfDays, vw.ArrivalDate) is null then DATEADD(day, dk.NumOfDays, dk.ArrivalDate) else DATEADD(day, vw.NumOfDays, vw.ArrivalDate) end as DepartureDate		  ,vw.DescriptionServive,vw.OriginalRate,vw.ServiceChargeAmount,vw.TaxAmount,vw.Status,vw.Username,vw.OpenTime,vw.ServiceId,vw.Username,vw.OpenTime,
              vw.ServiceCharge,vw.Tax,vw.SpecialTax, DATEADD(day, vw.NumOfDays, vw.ArrivalDate) as DepartureDate
                     ,dv.Service as FirstNameService
                     ,A.PaymentMethod as PaymentMethod, 
					 -- case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end as Outlet, 
					 isnull(sv.DisplayName, ''OtherRevenue'') as Outlet,
					 -- fbOutlet.Name, 
					 isnull(lang.EN, CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))))  as Name,
					 isnull(lang.VI, CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))))  as NameVI,
					 vw.Date, ct.Company as Company,isnull(pt.Room, kl.Room) Room,dk.Ma as BookingId, cast(vw.UpdatedDate as Date) as UpdatedDate , B.Description
                     
                     from vw_018 vw
                     left join SP2000 dk on dk.Ma=vw.RegisterID2
					 left join #tempKhachLe kl on kl.BookingId = dk.Ma
                     left join SP2300 k on k.Id=vw.CustomerId2
                     left join SP3003 hd on hd.Ma=vw.InvoiceId
					 left join SP5409 fbOutlet on fbOutlet.OutletId = case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end
					 left join #tempService sv on sv.Service = vw.ServiceId
					 left join #tempLang lang on CONCAT(''reportviewer.totalbillreport.'', LOWER(isnull(sv.DisplayName, ''OtherRevenue''))) = lang.id
					 left join SP2100 pt on isnull(vw.RentalRoomId2, vw.RentalRoomId1) = pt.Ma
					 left join SP1306 dv on vw.ServiceId = dv.Ma
					 left join SP2000 dk1 on dk1.Ma = isnull(dk.Ma,pt.BookingId)
					 left join SP1302 ct on ct.Ma = dk1.TravelAgency
					 left join (select STRING_AGG(A.PaymentMethod,'','') as PaymentMethod, PaymentID 
								from (select distinct PaymentMethod, PaymentID 
										from SP3002 
										where PaymentID is not null 
										group by PaymentID, PaymentMethod
									 ) A group by PaymentID 
								)  A on A.PaymentID = vw.PaymentID
					left join (select STRING_AGG(B.Description,'','') as Description, PaymentID 
								from (select distinct Description, PaymentID 
										from SP3002 
										where PaymentID is not null 
										group by PaymentID, Description
									 ) B group by PaymentID 
								)  B on B.PaymentID = vw.PaymentID
                           where 1=1  ' + 'and vw.ServiceId in(select data from dbo.func_061(''' +@Service + ''','',''))'
       if @ShowDepDate = 1
              set @sql1+=' and cast(vw.Date as Date) between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''''
       else
              set @sql1+=' and cast(vw.Date as Date)e = '''+cast(@FromDate as varchar(10))+''''
       if @User is not null and @User <> ''
              set @sql1+=' and vw.username like '''+@User+''''
       set @sql1 += ' and vw.DepartmentId like ''%'+@Department+'%'''
       set @sql1 += ' and case when vw.ServiceId in (''BD'', ''BF'') then ''FB'' else vw.Outlet end like ''%'+@outlet+'%'''
       set @sql1+=' order by vw.UpdatedDate, vw.'+@OrderBy+''
       
       print @sql1
       exec sp_executesql @sql1

	   drop table #tempService

	end
	drop table #templang
	drop table #tempKhachLe
end




(1 rows affected)

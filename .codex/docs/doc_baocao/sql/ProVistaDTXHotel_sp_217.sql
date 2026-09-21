SQL_STORED_PROCEDURE 5250 





create       proc [dbo].[sp_217]
(
@FromDate date,
@ToDate date,
@User varchar(20),
@OrderBy varchar(20),
@ShowDepDate bit,
@Department varchar(20)= '',
@outlet varchar(20) ='',
@Service varchar(20)
)
 as
begin

declare @division varchar(20); 
set @division = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	if(@Service = '')
	begin

       declare @sql nvarchar(max)
       set @sql='select ROW_NUMBER() over (order by (select 1)) as STT,vw.Edit,
              vw.Ma,RefId, (' + '''' + @division +'''' + ' 
             '+ ' + isnull( cast(vw.RegisterID2 as nvarchar),(select BookingId from sp2100 where Ma = vw.RentalRoomId2))) as RegisterID2,
              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end as Guest,
              vw.DescriptionServive,vw.OriginalRate,vw.ServiceChargeAmount,vw.TaxAmount,vw.TienQDTD,vw.Status,vw.Username,vw.OpenTime,vw.ServiceId,vw.Username,vw.OpenTime,
              vw.ServiceCharge,vw.Tax,vw.SpecialTax
                     ,(select Service from SP1306 dv where ServiceId=dv.Ma) as FirstNameService
                     ,hd.Pack1 as PaymentMethod, vw.Outlet, fbOutlet.Name, FORMAT(vw.Date, ''MM-yyyy'') as DateFormat,FORMAT(vw.Date, ''yyyy-MM'') as Date, hd.Date as DateHDBH, (select ct.Company from SP1302 ct, SP2000 dk1 where dk1.TravelAgency = ct.Ma and dk1.Ma = isnull(dk.Ma,pt.BookingId)) as Company,
					 vw.ArrivalDateVW,vw.DepartureDateVW,vw.Date as DateHDDV
                     
                     from vw_044 vw
                     left join SP2000 dk on dk.Ma=vw.RegisterID2
                     left join SP2300 k on k.Id=vw.CustomerId2
                     left join SP3003 hd on hd.Ma=vw.InvoiceId
					 left join SP5409 fbOutlet on fbOutlet.OutletId = vw.Outlet
					 left join SP2100 pt on vw.RentalRoomId2 = pt.Ma
                           where  1=1 and  vw.PaymentID is not null and hd.Pack1 not in (select Ma from SP1326 where HTMienPhi = 1) '
       if @ShowDepDate = 1
	   begin
		      set @sql+=' and (hd.Date between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''') '

              
	   end
       else
              set @sql+=' and vw.Date = '''+cast(@FromDate as varchar(10))+''''
       if @User is not null and @User <> ''
              set @sql+=' and vw.username like '''+@User+''''
       set @sql += ' and vw.DepartmentId like ''%'+@Department+'%'''
       set @sql += ' and vw.Outlet like ''%'+@outlet+'%'''
	   set @sql += '  and ((DATEPART(Month, vw.Date) <> DATEPART(Month, hd.Date))
			  or ((DATEPART(Month, vw.Date) = DATEPART(Month, hd.Date)) and (DATEPART(Year, vw.Date) <> DATEPART(Year, hd.Date))
			  ))   '
       set @sql+=' order by vw.'+@OrderBy+''
       
       print @sql
       exec sp_executesql @sql

	END


	else
	begin
		declare @sql1 nvarchar(max)
       set @sql1='select ROW_NUMBER() over (order by (select 1)) as STT,vw.sua,
              vw.Ma,RefId,(' + '''' + @division +'''' + ' 
             '+ ' +
              isnull(cast(vw.RegisterID2 as nvarchar),(select BookingId from sp2100 where Ma = vw.RentalRoomId2))) as RegisterID2,
              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end as Guest,
              vw.DescriptionServive,vw.OriginalRate,vw.ServiceChargeAmount,vw.TaxAmount,vw.TienQDTD,vw.Status,vw.Username,vw.OpenTime,vw.ServiceId,vw.Username,vw.OpenTime,
              vw.ServiceCharge,vw.Tax,vw.SpecialTax
                     ,(select Service from SP1306 dv where ServiceId=dv.Ma) as FirstNameService
                     ,hd.Pack1 as PaymentMethod,  FORMAT(vw.Date, ''MM-yyyy'') as DateFormat,FORMAT(vw.Date, ''yyyy-MM'') as Date, (select ct.Company from SP1302 ct, SP2000 dk1 where dk1.TravelAgency = ct.Ma and dk1.Ma = isnull(dk.Ma,pt.BookingId)) as Company,
                     vw.ArrivalDateVW,vw.DepartureDateVW
					 
                     from vw_044 vw
                     left join SP2000 dk on dk.Ma=vw.RegisterID2
                     left join SP2300 k on k.Id=vw.CustomerId2
                     left join SP3003 hd on hd.Ma=vw.InvoiceId
					 left join SP2100 pt on vw.RentalRoomId2 = pt.Ma
                           where 1=1 and   vw.PaymentID is not null and hd.Pack1 not in (select Ma from SP1326 where HTMienPhi = 1) ' + ' and vw.ServiceId = ''' + @Service + ''''
       if @ShowDepDate = 1
	   begin
			set @sql1 += ' and (hd.Date between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''') '

              
		end
		else
              set @sql1+=' and vw.Date = '''+cast(@FromDate as varchar(10))+''''
		if @User is not null and @User <> ''
              set @sql1+=' and vw.username like '''+@User+''''
		set @sql1 += ' and vw.DepartmentId like ''%'+@Department+'%'''
		set @sql1 += ' and vw.Outlet like ''%'+@outlet+'%'''
		set @sql1 += '  and ((DATEPART(Month, vw.Date) <> DATEPART(Month, hd.Date))
			  or ((DATEPART(Month, vw.Date) = DATEPART(Month, hd.Date)) and (DATEPART(Year, vw.Date) <> DATEPART(Year, hd.Date))
			  ))   '
		set @sql1+=' order by vw.'+@OrderBy+''
       
       print @sql1
       exec sp_executesql @sql1

	END
END




CREATE   proc [dbo].[sp_046] 

(

@FromDate date,

@ToDate date,

@User varchar(20),

@OrderBy varchar(20),

@ShowDepDate bit

)

 as

begin

declare @division varchar(20); 

set @division = (select  top 1 isnull(PrefixBookingId,'') from sp1322)



       declare @sql nvarchar(max)

       set @sql='select ROW_NUMBER() over (order by (select 1)) as STT,

              vw.Ma,vw.Date,vw.RefId,vw.PaymentID,

              isnull(cast(vw.RegisterID2 as nvarchar),vw.RentalRoomId2+''''+vw.CustomerId2) as InformationChung,isnull(vw.ArrivalDate,dk.ArrivalDate) as ArrivalDate, isnull(vw.DepartureDate,dk.ArrivalDate + dk.NumOfDays) as DepartureDate, ct.BusinessName,


              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end as Guest,

              vw.DescriptionServive,sum(vw.OriginalRate) as OriginalRate,sum(vw.ServiceChargeAmount) as ServiceChargeAmount,sum(vw.TaxAmount) as TaxAmount,sum(vw.TienQDTD) as TienQDTD,vw.Status,vw.Username,vw.OpenTime,vw.ServiceId,vw.Username,

              vw.ServiceCharge,vw.Tax,vw.SpecialTax

                     ,(select Service from SP1306 dv where ServiceId=dv.Ma) as FirstNameService, '''+@division + '''+ cast(vw.BookingId as varchar(10)) as BookingId

                     from (SELECT dvct.Amount as TienQDTD,hddv.Ma, hddv.Date, hddv.RefId,hddv.PaymentID,hddv.RegisterID2, dvct.DescriptionServive,

			(((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * 100) / (100 + hddv.ServiceCharge) 

            AS OriginalRate, (((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * hddv.ServiceCharge) / (100 + hddv.ServiceCharge) AS ServiceChargeAmount,

			(hddv.TotalAmount0 * hddv.Tax) / (100 + hddv.Tax) AS TaxAmount,hddv.TotalAmount0 AS TienQDTDTemp,hddv.Status, hddv.Username,

			hddv.OpenTime,hddv.ServiceCharge,hddv.Tax,hddv.SpecialTax,hddv.ServiceId,hddv.CustomerId2,hddv.Edit,hddv.RentalRoomId2,

			pt.ArrivalDate, pt.ArrivalDate + pt.ActualNumOfDays as DepartureDate,

			Cast((CASE  WHEN hddv.RegisterID2 is null THEN case when pt.BookingId is null  

			then cast(pt.Room as varchar(10))  else cast(pt.BookingId as varchar(10)) +''_R:''+  cast(pt.Room as varchar(10)) end   

			ELSE cast(hddv.RegisterID2 as varchar(50))  END) as varchar(50)) as BookingId, pt.BookingId as BkId

FROM        SP3000 AS hddv 

			inner join SP3001 dvct on hddv.Ma =  dvct.BillServiceId

			LEFT OUTER JOIN

                         SP2100 AS pt ON hddv.RentalRoomId2 = pt.Ma LEFT OUTER JOIN

                         SP2200 AS ptk ON hddv.RentalRoomId2 = ptk.RentalRoomId AND hddv.CustomerId2 = ptk.CustomerId INNER JOIN

                         SP1306 AS dv ON hddv.ServiceId = dv.Ma LEFT OUTER JOIN

                         SP3003 AS hdbh ON hddv.InvoiceId = hdbh.Ma) vw

                     left join SP2000 dk on dk.Ma= case when vw.BkId is not null then vw.BkId else vw.BookingId end

                     left join SP2300 k on k.Id=vw.CustomerId2

					 left join SP1302 ct on ct.Ma = dk.TravelAgency

                  where 1=1 and vw.Edit=0 and vw.PaymentID is null'

       

              set @sql+=' and vw.Date between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''''

       

              

       if @User is not null and @User <> ''

              set @sql+=' and vw.username like '''+@User+''''

       set @sql+=' Group by vw.Ma,vw.Date,vw.RefId,vw.PaymentID,

              isnull(cast(vw.RegisterID2 as nvarchar),vw.RentalRoomId2+''''+vw.CustomerId2) ,

              case when vw.RegisterID2 is null then k.FirstName else dk.BookingName end,

              vw.DescriptionServive

              ,vw.OriginalRate,vw.ServiceChargeAmount,vw.TaxAmount,vw.TienQDTD

              ,vw.Status,vw.Username

              ,vw.OpenTime

              ,vw.ServiceId,vw.Username, vw.BkId,

              

              vw.ServiceCharge,vw.Tax,vw.SpecialTax, isnull(vw.ArrivalDate,dk.ArrivalDate), isnull(vw.DepartureDate,dk.ArrivalDate + dk.NumOfDays),vw.BookingId ,ct.BusinessName 

                     

                     '

       set @sql+=' order by vw.'+@OrderBy+''

       print @sql

       exec sp_executesql @sql

end








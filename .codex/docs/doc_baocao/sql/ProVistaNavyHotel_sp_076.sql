

CREATE   Proc [dbo].[sp_076]

(

     @DateFrom Datetime,

     @DateTo Datetime,

     @Option int,

     @User varchar(20),

     @SortBy varchar(20),

     @OrderBy varchar(20),

	 @Company varchar(20)

)

 as

Begin

	 declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

     declare @sql nvarchar(max)

     if @Option = 1 

     set @sql ='select tt.Ma as MaDatCoc,tt.Date as PaymentDate,tt.OpenTime as TimePayment , tt.PaymentMethod as PaymentMethod , PaymentMethod.FirstName as FirstNamePaymentMethod , tt.Description,

     tt.PaymentTotalAmount0 as Amount , tt.Username , ' + '''' + @prefix +'''' + ' + cast(dk.Ma as varchar(20)) as BookingId , dk.BookingName, dk.ArrivalDate , dk.ArrivalDate + dk.NumOfDays as DepartureDate ,

      pt.Ma as RentalRoomId , pt.ArrivalDate as RoomArrivalDate, pt.ArrivalDate+pt.ActualNumOfDays  as RoomDepartureDate ,ct.Company as BusinessName,isnull(tt.PaymentID,'''') as MTT, case when (tt.PaymentID = '''' or tt.PaymentID is null) then 0 else 1 en
d as MTTGROUP, isnull(pt.Room,'''') as Room, ISNULL(tt.Pack4,'''') as AdvancePayment , ISNULL(tt.InvoiceId,'''') as InvoiceId,ISNULL(tt.PaymentID,'''') as PaymentID, ' + '''' + @prefix +'''' + ' + cast(isnull(pt.BookingId,'''') as varchar(20)) as MaBookin
gRoomTax,tt.Guest

       from SP3002 tt left join  SP2000 dk on tt.RegisterID2 = dk.Ma left join SP2100 pt on tt.RentalRoomId2 = pt.Ma left join SP1302 ct  on dk.TravelAgency = ct.Ma

	   left join SP1326 PaymentMethod on PaymentMethod.Ma = tt.PaymentMethod

        where  tt.Edit = 0 and (isnull( pt.Room,'''') not like ''0%'') and tt.Date between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +''' and (MONTH(Date) <> MONTH(dk.ArrivalDate +dk.NumOfDays) or MONTH(Date) <> MONTH(pt.Arri
valDate + pt.ActualNumOfDays))  and (isnull( tt.Pack2,'''') =''DPR'' or  isnull( tt.Pack4,'''')= ''AP'') '

     if @Option = 2

     set @sql = '

          select tt.Ma as MaDatCoc,tt.Date as PaymentDate,tt.OpenTime as TimePayment , tt.PaymentMethod as PaymentMethod , PaymentMethod.FirstName as FirstNamePaymentMethod , tt.Description,

     tt.PaymentTotalAmount0 as Amount , tt.Username , ' + '''' + @prefix +'''' + ' + cast(dk.Ma as varchar(20)) as BookingId , dk.BookingName, dk.ArrivalDate , dk.ArrivalDate + dk.NumOfDays as DepartureDate ,

      pt.Ma as RentalRoomId , pt.ArrivalDate as RoomArrivalDate, pt.ArrivalDate+pt.ActualNumOfDays  as RoomDepartureDate, ct.Company as BusinessName, isnull(tt.PaymentID,'''') as MTT, case when (tt.PaymentID = '''' or tt.PaymentID is null) then 0 else 1 e
nd as MTTGROUP , isnull(pt.Room,'''') as Room,ISNULL(tt.Pack4,'''') as AdvancePayment,ISNULL(tt.InvoiceId,'''') as InvoiceId,ISNULL(tt.PaymentID,'''') as PaymentID, ' + '''' + @prefix +'''' + ' + cast(isnull(pt.BookingId,'''') as varchar(20)) as MaBooking
RoomTax,tt.Guest

	   from SP3002 tt left join  SP2000 dk on tt.RegisterID2 = dk.Ma left join SP2100 pt on tt.RentalRoomId2 = pt.Ma left join SP1302 ct  on dk.TravelAgency = ct.Ma

	   left join SP1326 PaymentMethod on PaymentMethod.Ma = tt.PaymentMethod

        where tt.Edit = 0 and (isnull( pt.Room,'''') not like ''0%'')

         and (((dk.ArrivalDate + dk.NumOfDays) between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +''') or ((pt.ArrivalDate + pt.ActualNumOfDays) between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) 
+''')) and (isnull( tt.Pack2,'''') =''DPR'' or  isnull( tt.Pack4,'''')= ''AP'')'

	if @Option = 3

     set @sql ='select tt.Ma as MaDatCoc,tt.Date as PaymentDate,tt.OpenTime as TimePayment , tt.PaymentMethod as PaymentMethod , PaymentMethod.FirstName as FirstNamePaymentMethod ,tt.Description,

     tt.PaymentTotalAmount0 as Amount , tt.Username , ' + '''' + @prefix +'''' + ' + cast(dk.Ma as varchar(20)) as BookingId , dk.BookingName, dk.ArrivalDate , dk.ArrivalDate + dk.NumOfDays as DepartureDate ,

      pt.Ma as RentalRoomId , pt.ArrivalDate as RoomArrivalDate, pt.ArrivalDate+pt.ActualNumOfDays  as RoomDepartureDate ,ct.Company as BusinessName,isnull(tt.PaymentID,'''') as MTT, case when (tt.PaymentID = '''' or tt.PaymentID is null) then 0 else 1 en
d as MTTGROUP ,isnull(pt.Room,'''') as Room,ISNULL(tt.Pack4,'''') as AdvancePayment,ISNULL(tt.InvoiceId,'''') as InvoiceId,ISNULL(tt.PaymentID,'''') as PaymentID, ' + '''' + @prefix +'''' + ' + cast(isnull(pt.BookingId,'''') as varchar(20)) as MaBookingRo
omTax,tt.Guest

       from SP3002 tt left join  SP2000 dk on tt.RegisterID2 = dk.Ma left join SP2100 pt on tt.RentalRoomId2 = pt.Ma left join SP1302 ct  on dk.TravelAgency = ct.Ma

	   left join SP1326 PaymentMethod on PaymentMethod.Ma = tt.PaymentMethod

        where  tt.Edit = 0 and (isnull( pt.Room,'''') not like ''0%'')

         and tt.Date between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +''' and (isnull( tt.Pack2,'''') =''DPR'' or  isnull( tt.Pack4,'''')= ''AP'') '

	if @Option = 4

     set @sql ='select tt.Ma as MaDatCoc,tt.Date as PaymentDate,tt.OpenTime as TimePayment , tt.PaymentMethod as PaymentMethod, PaymentMethod.FirstName as FirstNamePaymentMethod , tt.Description, ct.Ma MaCT,

     tt.PaymentTotalAmount0 as Amount , tt.Username , ' + '''' + @prefix +'''' + ' + cast(dk.Ma as varchar(20)) as BookingId , dk.BookingName, dk.ArrivalDate , dk.ArrivalDate + dk.NumOfDays as DepartureDate ,

      pt.Ma as RentalRoomId , pt.ArrivalDate as RoomArrivalDate, pt.ArrivalDate+pt.ActualNumOfDays  as RoomDepartureDate ,ct.Company as BusinessName,isnull(tt.PaymentID,'''') as MTT, case when (tt.PaymentID = '''' or tt.PaymentID is null) then 0 else 1 en
d as MTTGROUP ,isnull(pt.Room,'''') as Room,ISNULL(tt.Pack4,'''') as AdvancePayment,ISNULL(tt.InvoiceId,'''') as InvoiceId,ISNULL(tt.PaymentID,'''') as PaymentID, ' + '''' + @prefix +'''' + ' + cast(isnull(pt.BookingId,'''') as varchar(20)) as MaBookingRo
omTax

       from SP3002 tt left join  SP2000 dk on tt.RegisterID2 = dk.Ma left join SP2100 pt on tt.RentalRoomId2 = pt.Ma left join SP1302 ct on tt.CompanyId2 = ct.Ma

	   left join SP1326 PaymentMethod on PaymentMethod.Ma = tt.PaymentMethod

        where  tt.Edit = 0 and (isnull( pt.Room,'''') not like ''0%'')

         and tt.Date between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +''' and (isnull( tt.Pack2,'''') =''DPR'' or  isnull( tt.Pack4,'''')= ''AP'') and (tt.PaymentID is null or InvoiceId is null )'

     set @sql+=' and tt.Username like ''%'+@User+'%'''

	  if @Option = 5	

     set @sql = '

          select tt.Ma as MaDatCoc,tt.Date as PaymentDate,tt.OpenTime as TimePayment , tt.PaymentMethod as PaymentMethod , PaymentMethod.FirstName as FirstNamePaymentMethod , tt.Description,

     tt.PaymentTotalAmount0 as Amount , tt.Username , ' + '''' + @prefix +'''' + ' + cast(dk.Ma as varchar(20)) as BookingId , dk.BookingName, dk.ArrivalDate , dk.ArrivalDate + dk.NumOfDays as DepartureDate ,

      pt.Ma as RentalRoomId , pt.ArrivalDate as RoomArrivalDate, pt.ArrivalDate+pt.ActualNumOfDays  as RoomDepartureDate, ct.Company as BusinessName, isnull(tt.PaymentID,'''') as MTT, case when (tt.PaymentID = '''' or tt.PaymentID is null) then 0 else 1 e
nd as MTTGROUP ,isnull(pt.Room,'''') as Room,ISNULL(tt.Pack4,'''') as AdvancePayment,ISNULL(tt.InvoiceId,'''') as InvoiceId,ISNULL(tt.PaymentID,'''') as PaymentID, ' + '''' + @prefix +'''' + ' + cast(isnull(pt.BookingId,'''') as varchar(20)) as MaBookingR
oomTax,tt.Guest

       from SP3002 tt left join  SP2000 dk on tt.RegisterID2 = dk.Ma left join SP2100 pt on tt.RentalRoomId2 = pt.Ma left join SP1302 ct  on dk.TravelAgency = ct.Ma

	   left join SP1326 PaymentMethod on PaymentMethod.Ma = tt.PaymentMethod

        where  tt.Edit = 0 and (isnull( pt.Room,'''') not like ''0%'')

         and (((dk.ArrivalDate ) between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +''') or ((pt.ArrivalDate) between  '''+ cast(@DateFrom as varchar)+ ''' and '''+ cast(@DateTo as varchar) +'''))  and (isnull( tt.Pack2,'''')
 =''DPR'' or  isnull( tt.Pack4,'''')= ''AP'')'

	 if(@Company != '')

	 begin

		set @sql+=' and ct.Ma = '''+@Company+''''

	 end

     set @sql+=' order by '+@SortBy+' '+@OrderBy+''

     PRINT @sql

   exec sp_executesql @sql

End




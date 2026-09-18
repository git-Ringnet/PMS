



 create     proc [dbo].[sp_068]

(

@FromDate date,

@ToDate date,

@User varchar(20),

@OrderBy varchar(20),

@OrderType varchar(20),

@ShowDepDate bit,

@Department varchar(20)= '',

@outlet varchar(20) ='',

@Service varchar(20)

)

 as

begin

	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	if(@Service = '')

	begin







       declare @sql nvarchar(max)

       set @sql='select pt.Room,pt.Ma as RoomCode,isnull(pt.Status,dk.Status) as Status, isnull(Concat(pt.ma,ptk.CustomerId),convert(nvarchar,pt.BookingId)) as CommonGuestId2,duong.Ma,case when pt.Room is null and convert(varchar,isnull(duong.RegisterID2,
pt.BookingId)) is not null then ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId))

			when pt.Room is not null  and convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) is null then convert(varchar,pt.Room)

			else ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) + ''/''+CONVERT(varchar,pt.Room)

			end as Room1,a.Service,duong.Date,duong.OpenTime,duong.TotalAmount0 as AmountDuong,duong.Username,duong.Outlet,duong.DepartmentId,b.Service,am.Date as CreatedDate,am.OpenTime  as CreatedHour,am.TotalAmount0 as AmountAm,am.CreatedUser,duong.DescriptionS
ervive as Description

					from SP3000 duong inner join SP3000 am

				on duong.Pack1 = am.Ma

				join SP1306 a on  a.Ma=duong.ServiceId

				left join SP1306 b on b.Ma=am.ServiceId

				left join SP2000 dk on dk.Ma = duong.RegisterID2

				left join SP2100 pt on pt.Ma=duong.RentalRoomId2

				left join (select * from SP2200 where IsMainGuest=1 or IsMainGuest is null) ptk on pt.Ma=ptk.RentalRoomId

                where 1=1 '+' and duong.Status=3 and duong.Edit=1'

       if @ShowDepDate = 1

              set @sql+=' and cast(am.Date as Date) between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''''

       else

              set @sql+=' and cast(am.UpdatedDate as Date) = '''+cast(@FromDate as varchar(10))+''''

       if @User is not null and @User <> ''

	   begin

              set @sql+=' and duong.Username like '''+@User+''''

		end

       set @sql += ' and am.DepartmentId like ''%'+@Department+'%'''

       set @sql += ' and duong.Outlet like ''%'+@outlet+'%'''

	   set @sql+='and duong.Pack1 is not null'

       if(@OrderBy='Room')

	   begin

		       set @sql+=' order by pt.'+@OrderBy+' '+@OrderType+''

	   end

	   else if(@OrderBy='CreatedHour')

	   begin

				set @sql+=' order by am.'+@OrderBy+' '+@OrderType+''

	   end

	   else

	   begin

				set @sql+=' order by am.'+@OrderBy+' '+@OrderType+''

	   end

       print @sql

       exec sp_executesql @sql

	   set @sql = '';



	end

	else

	begin





       set @sql ='select pt.Room,pt.Ma as RoomCode, isnull(pt.Status,dk.Status) as Status,isnull(Concat(pt.ma,ptk.CustomerId),convert(nvarchar,pt.BookingId)) as CommonGuestId2,duong.Ma,case when pt.Room is null and convert(varchar,isnull(duong.RegisterID2
,pt.BookingId)) is not null then ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId))

			when pt.Room is not null  and convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) is null then convert(varchar,pt.Room)

			else ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) + ''/''+CONVERT(varchar,pt.Room)

			end as Room1,a.Service,duong.Date,duong.OpenTime,duong.TotalAmount0 as AmountDuong,duong.Username,duong.Outlet,duong.DepartmentId,b.Service,am.Date as CreatedDate,am.OpenTime as CreatedHour,am.TotalAmount0 as AmountAm,am.CreatedUser,duong.DescriptionSe
rvive as Description

					from SP3000 duong inner join SP3000 am

				on duong.Pack1 = am.Ma

				join SP1306 a on  a.Ma=duong.ServiceId

				left join SP1306 b on b.Ma=am.ServiceId

				left join SP2100 pt on pt.Ma=duong.RentalRoomId2

				left join (select * from SP2200 where IsMainGuest=1 or IsMainGuest is null) ptk on pt.Ma=ptk.RentalRoomId

                where 1=1' + ' and duong.ServiceId = ''' + @Service + '''' +' and duong.Status=3 and duong.Edit=1'

       if @ShowDepDate = 1

              set @sql+=' and cast(am.Date as Date) between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+''''

       else

              set @sql+=' and cast(am.UpdatedDate as Date) = '''+cast(@FromDate as varchar(10))+''''



       if @User is not null and @User <> ''

	   begin

           set @sql+=' and duong.Username like '''+@User+''''

		end

       set @sql += ' and am.DepartmentId like ''%'+@Department+'%'''

       set @sql += ' and duong.Outlet like ''%'+@outlet+'%'''

	    set @sql+='and duong.Pack1 is not null'

          if(@OrderBy='Room')

	   begin

		       set @sql+=' order by pt.'+@OrderBy+' '+@OrderType+''

	   end

	   else if(@OrderBy='CreatedHour')

	   begin

				set @sql+=' order by am.'+@OrderBy+' '+@OrderType+''

	   end

	   else

	   begin

				set @sql+=' order by am.'+@OrderBy+' '+@OrderType+''

	   end



       print @sql

       exec sp_executesql @sql

	   set @sql = '';

	end

end







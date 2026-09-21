create   proc [dbo].[sp_070]

(

@FromDate varchar(10),

@ToDate varchar(10),

@User varchar(50),

@SortBy varchar(50),

@SortType varchar(10),

@DepartmentId varchar(10) = '',

@MaOutlet varchar(10) = ''

)

 as

begin

	declare @prefix varchar(20) = (select  top 1 isnull(PrefixBookingId,'') from sp1322)

	declare @sql nvarchar(max)

		set @sql='select pt.Room,pt.Ma as RoomCode,duong.Ma,isnull(pt.Status,dk.Status) as Status, isnull(Concat(pt.ma,ptk.CustomerId),convert(nvarchar,pt.BookingId)) as CommonGuestId2,case when pt.Room is null and convert(varchar,isnull(duong.RegisterID2,pt.Bo
okingId)) is not null then ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId))

			when pt.Room is not null  and convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) is null then convert(varchar,pt.Room)

			else ' + '''' + @prefix +'''' + ' + convert(varchar,isnull(duong.RegisterID2,pt.BookingId)) + ''/''+CONVERT(varchar,pt.Room)

			end as Room1,duong.DepartmentId,duong.PaymentMethod,duong.Date,duong.OpenTime,duong.Amount as AmountDuong,duong.Username as UsernameDuong,am.Username as UsernameAm,am.Ma,am.DepartmentId,am.PaymentMethod,am.CreatedDate,am.CreatedHour,am.Amount as Amount
Am,am.CreatedUser,am.Ma,duong.Description as Description   from SP3002 duong inner join SP3002 am

			on duong.Pack1 = am.Ma

			left join SP2100 pt on pt.Ma=duong.RentalRoomId2

			left join sp2000 dk on dk.Ma = duong.RegisterID2

			left join (select * from SP2200 where IsMainGuest=1 or IsMainGuest is null ) ptk on pt.Ma=ptk.RentalRoomId

			where 1 = 1 and duong.Status =3 and duong.Edit = 1

			and cast(am.CreatedDate as Date) between '''+cast(@FromDate as varchar(10))+''' and '''+cast(@ToDate as varchar(10))+'''

			and duong.Username like ''%'+@User+'%''

			and ('''+@DepartmentId+''' ='''' or duong.DepartmentId= '''+@DepartmentId+''')

			and ('''+@MaOutlet+''' ='''' or duong.Outlet = '''+@MaOutlet+''')'



		  if (@SortBy='CreatedHour')

		  begin

			set @sql+=' order by '+@SortBy+' '+@SortType+''

		  end

		   else if(@SortBy='Room')

		   begin

				   set @sql+=' order by pt.'+@SortBy+' '+@SortType+''

		   end

		   else if(@SortBy='CreatedHour')

		   begin

					set @sql+=' order by am.'+@SortBy+' '+@SortType+''

		   end

		   else

		   begin

					set @sql+=' order by am.'+@SortBy+' '+@SortType+''

		   end



		   print @sql

		   exec sp_executesql @sql

		   set @sql = '';

end









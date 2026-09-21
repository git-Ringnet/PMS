SQL_STORED_PROCEDURE 1936 
CREATE   proc [dbo].[sp_095]
	(
	@date date
	)
	 as
		begin
		
		declare @division varchar(20); 
		set @division = (select  top 1 isnull(PrefixBookingId,'') from sp1322)
		select @division + cast(vw.BookingId as varchar(20)) + ' - ' + vw.BookingName as BookingId,dk.ma AS BookingId2,vw.BookingName , case when ServiceId = 'RM' then vw.Room else null end as Room, vw.CustomerId,
		case when ServiceId = 'RM' then vw.Guest else null end as Guest, 
		case when ServiceId = 'RM' then vw.ArrivalDate else null end as ArrivalDate, case when ServiceId = 'RM' then vw.DepartureDate else null end as DepartureDate, 
		case when ServiceId = 'RM' then vw.Adult else null end as Adult,case when ServiceId = 'RM' then vw.Child else null end as Child,lst.ServiceId,Quantity,
		isnull(lst.Total,0) AS RateTotal, vw.RoomRateCode, vw.NoteBooking, dv.Service, vw.Breakfast, dk.Provide2
		from vw_031 vw 
		left join ( 
			select RentalRoomId,ServiceId,Sum(Total) as Total, count(*) as Quantity
			from dbo.func_031(@date,@date)
			where ServiceId = 'RM'
			group by RentalRoomId,ServiceId
		union
			select RentalRoomId,ServiceId, sum(Total) as Total, sum(Quantity) as Quantity
			from SP2102
			where ServiceId != 'RM' and FromDate = @date and Quantity != 0
			group by RentalRoomId,ServiceId
		--union 
		--	select RentalRoomId, 'BD' as ServiceId, sum(Rate) as Total, 1 as Quantity
		--	from sp2401
		--	where Date = @date
		--	group by RentalRoomId
		) lst 
		on vw.RentalRoomId = lst.RentalRoomId 
		left join SP1306 dv on dv.Ma = lst.ServiceId
		left join SP2000 dk on dk.Ma = vw.BookingId
		where @date between vw.ArrivalDate and  vw.DepartureDate - 1 and vw.Status in (0,1) and vw.BookingId is not null -- AND (vw.Room is null or vw.Room not like '0%')
		order by case  when ServiceId = 'RM' then 0 else 1 end ASC,
		ServiceId ASC

		
		
		
		
		
		
		
		
		
		
		
		
		
		
		end
CREATE FUNCTION [dbo].[func_044] (@Date datetime,@RoomTypeGroup NVARCHAR(10))
RETURNS int AS  
BEGIN 
	declare @kq int = 0
	declare @Dateht date
	select @Dateht = SystemDate from SP1500

	if @Date = @Dateht
		begin
			select @kq += isnull(Sum(pt.Adult),0) from SP2100 pt
			left join vw_001 bk on bk.Ma = pt.BookingId
			where pt.ArrivalDate in ( select SystemDate from SP1500)  
			and pt.Status in (0, 1, 2, 5, 101, 102) and pt.Room is not null and pt.Room not like '0%'
			and pt.ma not in (select MoveRoom from SP2100 where Status = 100 and CheckoutDate in ( select SystemDate from SP1500))
			and pt.RoomType in (select Ma from SP1100 where GroupRoomType  = @RoomTypeGroup) and bk.IsAvailability = 1
		end
	else
		begin
			if @Date > @Dateht	
				begin
					select @kq += isnull(Sum(pt.Adult),0)
					from SP2100 pt
					left join vw_001 bk on bk.Ma = pt.BookingId	
					where @Date=pt.ArrivalDate
					and pt.Status in(0,1,100)
					and pt.ActualNumOfDays<>0
					and (pt.Room is null or pt.Room not like '0%%') 
					and pt.RoomType in (select Ma from SP1100 where GroupRoomType  = @RoomTypeGroup) and bk.IsAvailability = 1				
				end
			else
				begin 
					select @kq += isnull(Sum(pt.Adult),0)
					from SP2100 pt
					left join vw_001 bk on bk.Ma = pt.BookingId
					where @Date=pt.ArrivalDate
					and pt.Status in(1,2,100)
					and (pt.Room is null or pt.Room not like '0%%')
					and pt.Ma not in( select MoveRoom 
					from SP2100 
					where @Date = ArrivalDate + ActualNumOfDays
					and Status in(100)
					and (ActualNumOfDays <> 0 or (ActualNumOfDays = 0 and ArrivalDate = ArrivalDate + ActualNumOfDays))
					and (Room is null or Room not like '0%%')
					and MoveRoom is not null)
					and pt.RoomType in (select Ma from SP1100 where GroupRoomType  = @RoomTypeGroup)
					and bk.IsAvailability = 1
				end
		end

	return @kq
END
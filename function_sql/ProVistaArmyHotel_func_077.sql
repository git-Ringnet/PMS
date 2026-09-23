-- Source: ProVistaArmyHotel
USE [ProVistaArmyHotel]
GO
--select * from func_077 ('G0000527','20230715','20230716','PK3',36,1)

create    function [dbo].[func_077]
(
	@RentalRoomId varchar(10), 
	@Arrival Date, 
	@Depature date, 
	@PackageCode varchar(10), 
	@RoomType smallint,
	@RoomKind smallint
)
returns @re table(
		[RentalRoomId] varchar(10),
		[DepartmentId] varchar(5),
		[ServiceId] varchar(20),
		[Date] date,
		[PackageCode] varchar(20),
		[Quantity] smallint,
		[Rate] money,
		[DetailMenu] nvarchar(max)
	)
 as
begin
	declare @AutoRestart bit, @TypeBreakdown varchar(20), @BeginDate date, @EndDate date, @Nights smallint
	declare @DepartmentId varchar(2), @ServiceId varchar(5), @Per varchar(20), @RoomTypeId int, @FromDay smallint, @ToDay smallint, @DetailMenu nvarchar(max), @FromAge smallint, @ToAge smallint, @Amount money

	declare @Index smallint, @WeekDay smallint, @Quantity smallint, @AdultNum int

	declare @systemDate datetime = (select * from SP1500)

	declare cursorPackageDetail cursor for
	select DepartmentId, ServiceId, Per, RoomTypeId, FromDay, ToDay, DetailMenu, FromAge, ToAge, Amount
	from SP1344 
	where PkgCode = @PackageCode

	select @AutoRestart = AutoRestart, @TypeBreakdown = TypeBreakdown, @BeginDate = DateFrom, @EndDate = DateTo, @Nights = Nights from SP1343 where PkgCode = @PackageCode
	
	if @PackageCode is not null
	begin
		
		set @Index = 1
		WHILE (@Arrival <= @Depature)
		BEGIN
			open cursorPackageDetail
			fetch next from cursorPackageDetail	
			into @DepartmentId, @ServiceId, @Per, @RoomTypeID, @FromDay, @ToDay, @DetailMenu, @FromAge, @ToAge, @Amount
			WHILE @@FETCH_STATUS = 0
			BEGIN
				set @Quantity = 1

				if(@Per = 'adult')
					set @Quantity = (select Adult from SP2100 where ma = @RentalRoomId)

				if(@Per = 'child')
					set @Quantity = (select count(ptte.ChildId) from SP2500 ptte left join SP2400 te on te.Id = ptte.ChildID where ptte.RentalRoomId = @RentalRoomId and ptte.Status in (0,1) and (te.Birthday is null or cast(datediff(dd,te.Birthday,@systemDate) / 365.25 as int) between @FromAge and @ToAge ))

				if(@TypeBreakdown = 'dayofweek')
					set @WeekDay = case when (@AutoRestart = 1 or @Index <= @Nights) then (select DATEPART(weekday, @Arrival)) else 8 end 
				else
					set @WeekDay = case when @AutoRestart = 1 then case when @Index % @Nights = 0 then @Nights else @Index % @Nights end else @Index end

				if((@Per != 'roomtype' or @RoomType = @RoomTypeId) and @Quantity != 0)
				BEGIN
					if @WeekDay between @FromDay and @ToDay
						insert into @re values(@RentalRoomId, @DepartmentId, @ServiceId, @Arrival, 'P|' + @PackageCode, @Quantity, @Amount * @Quantity, @DetailMenu)
					--else if (@ServiceId = 'RM')
					--	insert into @re values(@RentalRoomId, 'RM', @Arrival, '', 1, 0)
				END

			FETCH NEXT from cursorPackageDetail
			into @DepartmentId, @ServiceId, @Per, @RoomTypeID, @FromDay, @ToDay, @DetailMenu, @FromAge, @ToAge, @Amount
			END			
			close cursorPackageDetail
			set @Index = @Index + 1
			set @Arrival = DATEADD(day, 1, @Arrival);
		END	
		deallocate cursorPackageDetail
	end
	return
end
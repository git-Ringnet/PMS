-- Source: ProVistaArmyHotel
USE [ProVistaArmyHotel]
GO
CREATE    function [dbo].[func_076]
(
	@RentalRoomId varchar(10), 
	@Arrival Date, 
	@Depature date, 
	@RateCode varchar(10), 
	@RoomType smallint,
	@RoomKind smallint
)
returns @re table(
		[RentalRoomId] varchar(10),
		[Date] date,
		[RoomRateCode] varchar(20),
		[Rate] money
	)
 as
begin
	declare @RateCodeType varchar(10), @RateCodeValue nvarchar(max), @BeginDate date, @EndDate date, @RateType varchar(10), @AllowChangeRate bit

	declare @Upgrade varchar(20) = (select case when CHARINDEX(',', pack4) > 0 then substring(Pack4, 1, CHARINDEX(',', pack4) - 1) else pack4 end from SP2100 where Ma = @RentalRoomId)

	declare @RateRentalRoom money = (select Rate from SP2100 where Ma = @RentalRoomId)

	if(@Upgrade is not null)
	begin
		set @RoomType = (select SUBSTRING(@Upgrade, 1, CHARINDEX('-', @Upgrade) - 1))
		set @RoomKind = (select SUBSTRING(@Upgrade, CHARINDEX('-', @Upgrade) + 1, LEN(@Upgrade)))
	end

	select @RateCodeType = Type, @RateCodeValue = Value, @BeginDate = BeginDate, @EndDate = EndDate, @AllowChangeRate = AllowChangeRate from SP1340 where Ma = @RateCode
	
	if @RateCode is not null
	begin
		WHILE (@Arrival <= @Depature)
		BEGIN
			if @RateCodeType = 'fixed'
			begin
				if @Arrival between @BeginDate and @EndDate
					insert into @re values(@RentalRoomId, @Arrival, 'F|' + @RateCode, isnull((select top 1 Price from openjson(@RateCodeValue) with (RoomTypeId smallint '$.RoomTypeId', RoomKindId smallint '$.RoomKindId', Price money '$.Price') where RoomTypeId = @RoomType and RoomKindId = @RoomKind), 0))
			end
			else if @RateCodeType = 'dailyrate'
			begin
				select @RateType = Code from SP1342 where RateCode = @RateCode and Date = @Arrival and Date between @BeginDate and @EndDate

				if @RateType is not null
				begin
					select @RateCodeValue = Period from SP1341 where RateCode = @RateCode and Code = @RateType

					insert into @re values(@RentalRoomId, @Arrival, 'D|' + @RateType, isnull((select top 1 Price from openjson(@RateCodeValue) with (RoomTypeId smallint '$.RoomTypeId', RoomKindId smallint '$.RoomKindId', Price money '$.Price') where RoomTypeId = @RoomType and RoomKindId = @RoomKind), 0))
				end
			end

		set @Arrival = DATEADD(day, 1, @Arrival);
		END;
		
		update @re
		set Rate = @RateRentalRoom
		where @AllowChangeRate = 1 and Rate != @RateRentalRoom

	end
	return
end
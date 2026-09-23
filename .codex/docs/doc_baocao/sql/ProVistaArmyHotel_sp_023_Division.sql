--exec sp_023 '2023/04/01','2023/04/10',1, 1
--exec sp_023_Division '2023/06/02','2023/06/02','0',1

CREATE     PROCEDURE [dbo].[sp_023_Division](
	@FromDate date,
	@ToDate date,
	@Division nvarchar(100),	
	@BF bit = 1,
	@ListDivision varchar(200) = ''
)
--WITH ENCRYPTION
AS
BEGIN
	--Tính khoảng ngày ở min max khoảng ngày ở của nhưng Booking đăng ký trong khoảng @StartDATE - @EndDATE

	--declare @StartDATE DateTime = '2023/01/01'
	--declare @EndDATE DateTime = '2023/01/01'
	declare @NameDivision varchar(200) = (select Value from SP1600 where Parameter = 'ConfigDataReportForChain')

	if (@ListDivision != '')
		set @NameDivision = @ListDivision

	declare @Database varchar(50) = ''
	declare @Sql nvarchar(max)=''

					Create table #table(
					Date Datetime,
					DepAdult int,
					DepChild int,
					DepRooms int,
					ArrAdult int,
					ArrChild int,
					ArrRooms int,
					OccAdult int,
					OccChild int,
					OccRooms int,
					HouseUse int,
					FOCAll int,
					FOC int,
					FOCOwner int,
					RoomSales int,
					ExtraBed int,
					Revenue decimal(18,2),
					AvgRate decimal(18,2),
					AvgRate2 decimal(18,2),
					RoomAvible int,
					PercentOccupancy decimal(18,2),
					PercentOccupancy1 decimal(18,2),
					BabyCot int,
					RM decimal(18,2),
					EB decimal(18,2),
					ER decimal(18,2),
					OOO int,
					DayUse decimal(18,2),
					Division varchar(50))


if(@Division = '0')
	BEGIN
			insert into #table
			exec sp_023 @FromDate,@ToDate, '',@BF

			select * from #table
	END
else

	BEGIN
		Declare cursorDatabase CURSOR FOR
			select  data from dbo.func_061(@NameDivision,',')
		Open cursorDatabase
		Fetch next From cursorDatabase
					into @Database
		While @@FETCH_STATUS = 0
			Begin
							set @Sql = ' use ['+@Database+']
									insert into #table
									exec sp_023 '''+format(@FromDate,'yyyy/MM/dd')+''','''+format(@ToDate,'yyyy/MM/dd')+''','''','+ cast(@BF as varchar(1))+''
									print (@Sql)
							exec (@Sql)
							--Fetch next From cursorDatabase
							--into @Database
							--		insert into #table
							--		exec sp_023 @FromDate,@ToDate, '',@BF

							Fetch next From cursorDatabase
							into @Database
						End

					Close cursorDatabase
					Deallocate cursorDatabase


					select 	* from #table

	END


	drop table #table
					
END

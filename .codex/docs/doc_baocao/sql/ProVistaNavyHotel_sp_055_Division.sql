Changed database context to 'ProVistaNavyHotel'.

CREATE     PROCEDURE [dbo].[sp_055_Division](
	@Division int =0,
	@From date,
	@To date,
	@Area int,
	@Company varchar(50),
	@Segment int,
	@UserSale varchar(20),
	@TypeGroup varchar(20),
	@BF bit = 1,
	@ListDivision varchar(200) = ''
)
--WITH ENCRYPTION
AS
BEGIN
	--T�nh kho?ng ng�y ? min max kho?ng ng�y ? c?a nhung Booking dang ky trong kho?ng @StartDATE - @EndDATE

	--declare @StartDATE DateTime = '2023/01/01'
	--declare @EndDATE DateTime = '2023/01/01'
	declare @NameDivision varchar(500) = (select Value from SP1600 where Parameter = 'ConfigDataReportForChain')

	if (@ListDivision != '')
		set @NameDivision = @ListDivision

	declare @totalRAV3 int = 0
	declare @Database varchar(50) = ''
	declare @Sql nvarchar(max)=''

					Create table #table(
					Date date,
					SourceCode int,
					SourceCodeName nvarchar(50),
					MaSegment int,
					MarketSegment nvarchar(50),
					CompanyId varchar(20),
					Company nvarchar(500),
					NumOfRoom int,
					RoomNight int,
					FOC int,
					HU int,
					GuestNight int,
					RM money,
					EB money,
					ER money,
					NumOdGuest int,
					Revenue money,
					PerRoomNight money,
					PerRevenue money,
					AVGRevenue money,
					FBRevenue money,
					OthersRevenue money,
					TotalByDate money,
					OriginalTotal money,
					RAV int,
					RAV3 int,
					GRAV int,
					Division varchar(20))


if(@Division = 0)
	BEGIN
			set @Company = case when @Company = '0' then '' else @Company end

			insert into #table
			exec sp_055 @From, @To, @Area, @Company, @Segment, @UserSale, @TypeGroup, @BF

			--select * from #table
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
									--insert into #table
									--exec sp_246 @StartDATE,@EndDATE, @showCancel
									set @Sql = ' use ['+@Database+']
									insert into #table
									exec sp_055 '''+format(@from,'yyyy/MM/dd')+''','''+format(@to,'yyyy/MM/dd')+''','''+ (case when cast(@Area as varchar(10)) = '0' then '' else cast(@Area as varchar(10)) end) + ''','''+ (case when cast(@Company as varchar(10)) = '0' then '' else cast(@Company as varchar(10)) end) +''','''+ (case when cast(@Segment as varchar(10)) = '0' then '' else  cast(@Segment as varchar(10)) end)+''','''+ @UserSale +''','''+ @TypeGroup +''',' + cast(@BF as varchar(10)) + ''
							exec (@Sql)
							print (@Sql)
							Fetch next From cursorDatabase
							into @Database 
						End

					Close cursorDatabase
					Deallocate cursorDatabase


	END
	
					set @totalRAV3 = (select sum(RAV3) from (select max(RAV3) RAV3 from #table group by Division) T1)

					select 	*, @totalRAV3 as TotalRAV3 from #table
					where roomnight != 0 or Revenue + FBRevenue + OthersRevenue != 0
					order by Revenue + FBRevenue + OthersRevenue desc

	drop table #table
					
END
--exec sp_246 '2023-02-01', '2023-02-01'
--select * from sp8037


(1 rows affected)

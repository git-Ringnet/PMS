

CREATE     PROCEDURE [dbo].[sp_078_Division](

	@Division int =0,

	@From date,

	@To date,

	@Area int,

	@Company varchar(50),

	@Segment int,

	@UserSale varchar(20),

	@SourceCode int = 0

)

--WITH ENCRYPTION

AS

BEGIN

	--Tính khoảng ngày ở min max khoảng ngày ở của nhưng Booking đăng ký trong khoảng @StartDATE - @EndDATE



	--declare @StartDATE DateTime = '2023/01/01'

	--declare @EndDATE DateTime = '2023/01/01'

	declare @NameDivision varchar(200) = (select Value from SP1600 where Parameter = 'ConfigDataReportForChain')

	declare @Database varchar(50) = ''

	declare @Sql nvarchar(max)=''



					Create table #table(

					BookingCode varchar(200),

					CompanyId int,

					Company nvarchar(200),

					BookingId nvarchar(20),

					ArrivalDate datetime,

					DepartureDate datetime,

					BookingName nvarchar(200),

					BookerName nvarchar(200),

					FullName nvarchar(200),

					RoomNight int,

					GuestNight int,

					RoomAmount money,

					OriginalAmount money,

					RoomRevenue money,

					FbRevenue money,

					OtherRevenue money,

					FOC int,

					RoomType varchar(100),

					FirstName nvarchar(100),

					Nationality varchar(100),

					BookingDate datetime,

					RoomRateCode varchar(20),

					MarketSegment varchar(100),

					SourceCode varchar(100),

					NoOfNight int,

					NoOfRoom int,

					Division varchar(20))





if(@Division = 0)

	BEGIN

			declare @ar varchar(50), @comp varchar(50), @seg varchar(50), @source varchar(50)

			set @ar = case when @Area = 0 then '' else cast (@Area as varchar(50)) end

			set @comp = case when @Company = '0' then '' else @Company end

			set @seg = case when @Segment = 0 then '' else cast (@Segment as varchar(50)) end

			set @source = case when @SourceCode = 0 then '' else cast (@SourceCode as varchar(50)) end



			insert into #table

			exec sp_078 @From, @To, @ar, @comp, @seg, @UserSale, @source



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

									exec sp_078 '''+format(@from,'yyyy/MM/dd')+''','''+format(@to,'yyyy/MM/dd')+''','''+ (case when cast(@Area as varchar(10)) = '0' then '' else cast(@Area as varchar(10)) end) + ''','''+ (case when cast(@Company as varchar(10)) = '0' then '' else c
ast(@Company as varchar(10)) end) +''','''+ (case when cast(@Segment as varchar(10)) = '0' then '' else  cast(@Segment as varchar(10)) end)+''','''+ @UserSale +''','''+ (case when cast(@SourceCode as varchar(10)) = '0' then '' else  cast(@SourceCode as va
rchar(10)) end) +''''

							exec (@Sql)

							print (@Sql)

							Fetch next From cursorDatabase

							into @Database 

						End



					Close cursorDatabase

					Deallocate cursorDatabase





	END



					select * from #table

					where RoomNight != 0 or RoomRevenue + FbRevenue + OtherRevenue != 0

	



	drop table #table

					

END

--exec sp_246 '2023-02-01', '2023-02-01'

--select * from sp8037


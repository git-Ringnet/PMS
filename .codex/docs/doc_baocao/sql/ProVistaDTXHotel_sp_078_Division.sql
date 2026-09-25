

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

					CompanyId int,

					Company nvarchar(200),

					BookingId nvarchar(20),

					ArrivalDate datetime,

					DepartureDate datetime,

					BookingName nvarchar(200),

					RoomNight int,

					GuestNight int,

					RM money,

					EB money,

					ER money,

					Revenue money,

					Division varchar(20))





if(@Division = 0)

	BEGIN



			set @Company = case when @Company = '0' then '' else @Company end



			insert into #table

			exec sp_078 @From, @To, @Area, @Company, @Segment, @UserSale, @SourceCode



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



					select * from #table





	END

	



	drop table #table

					

END

--exec sp_246 '2023-02-01', '2023-02-01'

--select * from sp8037


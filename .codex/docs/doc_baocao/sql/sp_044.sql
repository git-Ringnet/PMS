CREATE   proc [dbo].[sp_044]
(
	@Date datetime,
	@TDate datetime,
	@type int,
	@IsCL bit = 0,
	@isViewByTime  bit = 0,
	@FromTime char(5),
	@ToTime char(5)
)
 as
	begin
		create table #TempLocation
(
 Date date,
 Room money,
 EB money,
 EP money,
 ER money,
 Tour money,
 Transportation money,
 Miscell money,
 Food money,
 Beverage money,
 MoneyBreakfastRoom money,
 BF money,
 Others money,
 Minibar money,
 Laudry money,
 Broken money,
 Beauty money,
 Massage money,
 SPA money,
 Karaoke money,
 Banquet money,
 Shop money,
 Total money,
 TotalRC money,
 ServiceChargeAmount money,
 TaxAmount money,
)

insert into #TempLocation 
(
Date ,
 Room ,
 EB ,
 EP,
 ER ,
 Tour,
 Transportation,
 Miscell ,
 Food ,
 Beverage ,
 MoneyBreakfastRoom ,
 BF ,
 Others ,
 Minibar ,
 Laudry ,
 Broken ,
 Beauty ,
 Massage ,
 SPA ,
 Karaoke ,
 Banquet ,
 Shop ,
 Total ,
 TotalRC ,
 ServiceChargeAmount ,
 TaxAmount 
) exec sp_018 @Date,@TDate, @type,@IsCL, @isViewByTime, @FromTime, @ToTime


create table #TempResult
(
	FirstName varchar(50),
	TotalAmount money
)
insert into #TempResult values ('Room'  , (select SUM(Room) from #TempLocation))
insert into #TempResult values ('EB'  , (select SUM(EB) from #TempLocation))
insert into #TempResult values ('EP'  , (select SUM(EP) from #TempLocation))
insert into #TempResult values ('ER'  , (select SUM(ER) from #TempLocation))
insert into #TempResult values ('Tour'  , (select SUM(Tour) from #TempLocation))
insert into #TempResult values ('Transportation'  , (select SUM(Tour) from #TempLocation))
insert into #TempResult values ('Miscell'  , (select SUM(Miscell) from #TempLocation))
insert into #TempResult values ('Food'  , (select SUM(Food) from #TempLocation))
insert into #TempResult values ('Beverage'  , (select SUM(Beverage) from #TempLocation))
insert into #TempResult values ('MoneyBreakfastRoom'  , (select SUM(MoneyBreakfastRoom) from #TempLocation))
insert into #TempResult values ('BF'  , (select SUM(BF) from #TempLocation))
insert into #TempResult values ('Others'  , (select SUM(Others) from #TempLocation))
insert into #TempResult values ('Minibar'  , (select SUM(Minibar) from #TempLocation))
insert into #TempResult values ('Laudry'  , (select SUM(Laudry) from #TempLocation))
insert into #TempResult values ('Broken'  , (select SUM(Broken) from #TempLocation))
insert into #TempResult values ('Beauty'  , (select SUM(Beauty) from #TempLocation))
insert into #TempResult values ('Massage'  , (select SUM(Massage) from #TempLocation))
insert into #TempResult values ('SPA'  , (select SUM(SPA) from #TempLocation))
insert into #TempResult values ('Karaoke'  , (select SUM(Karaoke) from #TempLocation))
insert into #TempResult values ('Banquet'  , (select SUM(Banquet) from #TempLocation))
insert into #TempResult values ('Shop'  , (select SUM(Shop) from #TempLocation))


select * from #TempResult

drop table #TempLocation
drop table #TempResult
	end
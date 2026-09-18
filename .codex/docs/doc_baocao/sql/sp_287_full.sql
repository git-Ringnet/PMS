-- exec sp_287 '20250711', '20250711', ''



create   procedure sp_287(@From date, @To date, @RateCode varchar(500))

as

begin

	select dk.Ma as BookingCode, pt.Ma, dk.BookingName, ct.Company, pt.ArrivalDate, pt.CheckoutDate, pt.NumOfDays, pt.Room, pt.Adult, pt.Child, lp.ShortName, pt.RoomRateCode, rc.Description as RateCode from sp2100 pt

	left join sp2000 dk on pt.BookingId = dk.Ma

	left join SP1100 lp on lp.Ma = pt.RoomType

	left join SP1302 ct on ct.Ma = dk.TravelAgency

	left join Sp1340 rc on rc.Ma = pt.RoomRateCode

	where RoomRateCode is not null and RoomRateCode != '' and Room not like '0%'

	and pt.status not in (3, 4)

	and (@RateCode = '' or RoomRateCode in (select value from string_split(@RateCode, ',')))

	and (pt.ArrivalDate between @From and @To or pt.CheckoutDate between @From and @To or (pt.ArrivalDate < @From and pt.CheckoutDate > @To))

end



--select * from SP1340

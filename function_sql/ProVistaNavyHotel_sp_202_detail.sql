 



 

 create       procedure [dbo].[sp_202_detail]

(

	@from date,

	@to date,

	@outlet char(2),

	@user varchar(20),

	@sorttype varchar(4)

)

 as

begin

 select hd.Guest,

		 hd.Date,

		 hd.Ca,

		 hd.NumOfRoom,

		 hd.RoomNo,

		 ct.TotalAmount * 1 / (1 + Tax/100) * 1/ (1 + SpecialTax/100) * 1 / (1 + ServiceCharge/100) as BillOriginalAmount,

		 ct.TotalAmount * 1 / (1 + Tax/100) * 1/ (1 + SpecialTax/100) * ServiceCharge/100 / (1 + ServiceCharge/100) as BillServicesChargeAmount,

		 ct.TotalAmount * Tax/100 / (1 + Tax/100) as TaxAmountTotal,

		 hd.PaymentId,

		 hd.BillNote,

		 hd.BillUsername,

		 ct.Product,

		 ct.Quantity,

		 ct.TotalAmount,

		 ct.Rate,

		 hd.BookingId,

		 hd.RentalRoomId2

 from vw_039 hd 

 left join SP3002 tt on tt.PaymentID=hd.PaymentID 

 left join SP6001 ct on ct.BillId = hd.Ma

 where hd.Date between @from and @to and hd.Outlet = @outlet 

 and (tt.PaymentMethod in ('CL'))  

 and hd.username like '%'+@user+'%' 

 order by  (case when @sorttype = 'ASC' then hd.Ma end)  DESC,  (case when @sorttype = 'DESC' then hd.Ma end)  ASC 

end










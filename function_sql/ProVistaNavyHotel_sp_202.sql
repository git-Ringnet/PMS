



 

 CREATE     procedure [dbo].[sp_202]

(

	@from date,

	@to date,

	@outlet char(2),

	@user varchar(20),

	@sorttype varchar(4)

)

 as

begin

 select hd.BookingId, hd.NumOfRoom, hd.Guest, hd.FOCType,tt.PaymentMethod,hd.BillDiscountAmount, hd.BillTotalAmount, hd.PaymentID, hd.BillNote, hd.BillUsername,hd.ca, hddv.DescriptionServive,

 STUFF((select concat('* ', t2.Product , ' - ',sum(t2.Quantity))

	from sp6001 as t2

	where hd.Ma = t2.BillId 

	group by t2.Product

	for xml path(''),type).value('.', 'nvarchar(max)'),1,2,'') as Product

 from vw_039 hd left join SP3002 tt on tt.PaymentID=hd.PaymentID 

left join sp6001 mnct on hd.ma=mnct.BillId

left join SP3000 hddv on hddv.Ma = hd.BillServiceId

 where hd.Date between @from and @to and hd.Outlet = @outlet and tt.PaymentMethod in ('CL')  and hd.username like '%'+@user+'%' 

 group by hd.BookingId, hd.NumOfRoom, hd.Guest, hd.FOCType,tt.PaymentMethod,hd.BillDiscountAmount, hd.BillTotalAmount, hd.PaymentID, hd.BillNote, hd.BillUsername,hd.ca, hddv.DescriptionServive, hd.Ma

 order by  (case when @sorttype = 'ASC' then hd.Ma end)  DESC,  (case when @sorttype = 'DESC' then hd.Ma end)  ASC 

end











--exec SP_202 '20250101','20251231','MB','',''

--select * from vw_039 where FOCtype=1




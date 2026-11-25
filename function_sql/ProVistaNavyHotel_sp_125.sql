





CREATE     procedure [dbo].[sp_125]

(

	@from date,

	@to date,

	@outlet char(2),

	@department varchar(2),

	@user varchar(20),

	@shift varchar(5),

	@viewtype varchar(10),

	@orderby varchar(10),

	@ordertype varchar(4)

)

 as

begin



select hd.Ma,hd.RefId,hd.BookingId, case when (hd.RegisterID2 is null and hd.RentalRoomId2 is not null) then hd.Room else ''/*'C_'+Convert(varchar(20),hd.RegisterID2) */end as Room,

hd.Guest,hd.DescriptionServive,isnull((case when hd.Edit = 1 then mnb.BillOriginalAmount else hd.OriginalRate end),0) as OriginalRate, isnull((case when hd.Edit = 1 then mnb.BillServicesChargeAmount else hd.ServiceChargeAmount end), 0) as ServiceChargeAmo
unt, isnull((case when hd.Edit = 1 then mnb.BillTaxAmount else  hd.TaxAmount end),0) as TaxAmount,

mnb.BillDiscountAmount,mnb.BillTotalAmount, /*Vy bsung*/

hd.PaymentID,hd.Username,hd.Ca,hd.Date, mnb.BillNote, mnb.RoomNo, case when tt.PaymentMethod = 'CL' then 'Free' when hd.Edit = 0 then 'Post' else 'Correct' end as Status,

STUFF((select concat('* ', t2.Product , ' - ',sum(t2.Quantity))

	from sp6001 as t2

	where mnb.Ma = t2.BillId 

	group by t2.Product

	for xml path(''),type).value('.', 'nvarchar(max)'),1,2,'') as Product

from vw_018 hd 

left join SP6000 mnb on hd.Ma = mnb.BillServiceId 

left join sp6001 mnct on mnb.ma=mnct.BillId

left join SP3002 tt on tt.Ma= (select min(Ma) from SP3002 where PaymentId = hd.PaymentID)

where 1=1  and hd.Date between @from and @to and hd.Outlet like '%'+@outlet+'%' 

--and hd.Amount <> 0

and hd.Ca like '%'+@shift+'%' and hd.Username like '%'+@user+'%' and hd.DepartmentId like '%'+@department+'%' and (@viewtype ='all' or hd.Edit in (case @viewtype when 'post' then (0) when 'correct' then (1) end)) and (@viewtype != 'post' or tt.PaymentMeth
od != 'CL' or tt.PaymentMethod is null) and (@viewtype != 'correct' or mnb.BillEdit = 1) and hd.Status != 4 and mnb.ma is not null

group by hd.Ma,hd.RefId,hd.BookingId,hd.Room,hd.Guest,hd.DescriptionServive,hd.OriginalRate,hd.ServiceId,hd.ServiceChargeAmount,hd.TaxAmount,mnb.BillDiscountAmount,mnb.BillTotalAmount,hd.PaymentID,hd.Username,hd.Ca,hd.Date, mnb.BillNote,hd.RegisterID2,hd.
RentalRoomId2 , hd.Edit, mnb.BillOriginalAmount, mnb.BillServicesChargeAmount,  mnb.BillTaxAmount, mnb.RoomNo, tt.PaymentMethod, mnb.Ma

order by

case @ordertype when 'ASC'  then (

case when @orderby = 'Ma' then hd.Ma when @OrderBy = 'Room' then try_cast(hd.Room as numeric ) when @OrderBy = 'Date' then (-datediff(day, hd.Date, 0)) when @orderby = 'ServiceId' then try_cast(hd.ServiceId as numeric ) when @orderby ='RefId' then hd.RefI
d end) end DESC, 

case @ordertype when 'DESC'  then (

case when @orderby = 'Ma' then hd.Ma when @OrderBy = 'Room' then try_cast(hd.Room as numeric ) when @OrderBy = 'Date' then (-datediff(day, hd.Date, 0)) when @orderby = 'ServiceId' then try_cast(hd.ServiceId as numeric ) when @orderby ='RefId' then hd.RefI
d end ) end ASC 

end



--exec sp_125 '20250801', '20250820','BR','','','','all','Ma','ASC'




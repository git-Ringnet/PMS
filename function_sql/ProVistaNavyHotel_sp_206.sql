

CREATE     procedure [dbo].[sp_206]

(

	@from date,

	@to date,

	@outlet char(2),

	@department varchar(2),

	@user varchar(20),

	@shift varchar(5),

	@freeitem bit

)

 as

	begin

		if @freeitem = 1

		begin		

		select Name=min(t.Name),v.MaProduct, v.ProductType, v.Product, Currency=min(v.Currency), Quantity=sum(v.Quantity),Rate=avg(v.Rate), StandardRate=avg(v.StandardRate), Total=sum(v.Total), sum((v.StandardRate - v.Rate)* v.Quantity) as DiscountAmount

		from vw_040 as v LEFT JOIN SP5401 as t on v.Currency=t.Id 

		left join vw_039 hd on hd.Ma = v.BillId 

		left join SP3002 tt on tt.PaymentID=hd.PaymentID 

		where (v.Date between @from and @to) and v.Username like '%'+@user+'%' and v.Ca like '%'+@shift+'%' and v.Department like '%'+@department+'%' and v.Status=1 and v.Outlet= @outlet  and (MaFOCType <> 0 or tt.PaymentMethod='CL') and BillEdit = 0  group by 
v.MaProduct, v.ProductType, v.Product

		end

		if @freeitem = 0

		begin	

		select Name=min(Name),MaProduct, ProductType, Product, Currency=min(Currency), Quantity=sum(Quantity),Rate=avg(Rate), StandardRate=avg(StandardRate), Total=sum(Total) , sum(DiscountAmount) DiscountAmount from (select v.BillId,v.MaProduct,v.Quantity,v.Cu
rrency,v.ProductType, v.Product,t.Name,v.Rate,v.StandardRate,v.Total , sum((v.StandardRate - v.Rate)* v.Quantity) as DiscountAmount

		from vw_040 v left join vw_039 hd on hd.Ma = v.BillId 

		LEFT JOIN SP5401 as t on v.Currency=t.Id 

		left join SP3002 tt on tt.PaymentID=hd.PaymentID 

		where MaFOCType = 0 and (v.Date between @from and @to) and v.Username like '%'+ @user+ '%' and v.Ca like '%'+@shift+'%' and v.Status=1 and v.Outlet= @outlet and v.Department like '%'+@department+'%'  and (tt.PaymentMethod is null or tt.PaymentMethod  no
t in ('CL'))  and BillEdit = 0 group by v.BillId,tt.PaymentID,v.Quantity,v.Currency, v.MaProduct,v.ProductType, v.Product,t.Name,v.Rate,v.StandardRate,v.Total) a group by MaProduct, ProductType, Product having sum(Quantity) > 0  

			end

	end





	--exec sp_206 '20250801', '20250831','LA','','','',0




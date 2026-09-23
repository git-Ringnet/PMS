Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
-- ==================================================
                                                                                                                                                                                                        
-- D? li?u t? file: C:\Users\sproud-admin\Desktop\UPDATE\POS\Army_Store_20260322\store_pos_hotel_demo_20260302(2)\20251021_ReportRTM\sp_TotalRevenueGuestBill.sql
                                                                                            
-- ==================================================
                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             
 
                                                                                                                                                                                                                                                            
--exec sp_TotalRevenueGuestBill '20241014','20261014'
                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
Create     PROC [dbo].[sp_TotalRevenueGuestBill] (@DateFrom DATETime,	@DateTo DATETime, @outlet varchar(10)= '')
                                                                                                                                             
AS
                                                                                                                                                                                                                                                           

                                                                                                                                                                                                                                                             
CREATE TABLE #GuestReport (
                                                                                                                                                                                                                                  
    InGuest money,
                                                                                                                                                                                                                                           
    InBill money,
                                                                                                                                                                                                                                            
    OutGuest money,
                                                                                                                                                                                                                                          
    OutBill money,
                                                                                                                                                                                                                                           
	InTotalAmount money,
                                                                                                                                                                                                                                        
	OutTotalAmount money,
                                                                                                                                                                                                                                       
);
                                                                                                                                                                                                                                                           

                                                                                                                                                                                                                                                             
insert into #GuestReport values
                                                                                                                                                                                                                              
(0,0,0,0,0,0)
                                                                                                                                                                                                                                                

                                                                                                                                                                                                                                                             
select 
                                                                                                                                                                                                                                                      
case when PaymentMethod in ('Room','Group') then 'In' else 'Out' end as Type,
                                                                                                                                                                                
sum(NoOfGuest) as TotalGuest,
                                                                                                                                                                                                                                
sum(TotalAmount) as TotalAmount,
                                                                                                                                                                                                                             
count(BillId) as TotalBill
                                                                                                                                                                                                                                   
into #tempBill
                                                                                                                                                                                                                                               
from SP5000
                                                                                                                                                                                                                                                  
where ServicePayId > 0
                                                                                                                                                                                                                                       
and Edit = 0
                                                                                                                                                                                                                                                 
and Deleted =0
                                                                                                                                                                                                                                               
and (@outlet = '' OR @outlet = Oulet)
                                                                                                                                                                                                                        
and Convert(date,CreatedDate) between  Convert(date,@DateFrom) and Convert(date,@DateTo)
                                                                                                                                                                     
group by PaymentMethod
                                                                                                                                                                                                                                       

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
update #GuestReport set InGuest = isnull((select SUM(TotalGuest) from #tempBill where Type = 'In'),0)
                                                                                                                                                        
update #GuestReport set InBill = isnull((select SUM(TotalBill) from #tempBill where Type = 'In'),0)
                                                                                                                                                          
update #GuestReport set OutGuest = isnull((select SUM(TotalGuest) from #tempBill where Type = 'Out'),0)
                                                                                                                                                      
update #GuestReport set OutBill = isnull((select SUM(TotalBill) from #tempBill where Type = 'Out'),0)
                                                                                                                                                        
update #GuestReport set InTotalAmount = isnull((select SUM(TotalAmount) from #tempBill where Type = 'In'),0)
                                                                                                                                                 
update #GuestReport set OutTotalAmount = isnull((select SUM(TotalAmount) from #tempBill where Type = 'Out'),0)
                                                                                                                                               

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
select * from #GuestReport;
                                                                                                                                                                                                                                  

                                                                                                                                                                                                                                                             
drop table #GuestReport;
                                                                                                                                                                                                                                     
drop table #tempBill;
                                                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             
 
                                                                                                                                                                                                                                                            

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

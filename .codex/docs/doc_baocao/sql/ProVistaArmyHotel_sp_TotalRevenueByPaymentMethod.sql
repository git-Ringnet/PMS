Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
-- ==================================================
                                                                                                                                                                                                        
-- D? li?u t? file: C:\Users\sproud-admin\Desktop\UPDATE\POS\Army_Store_20260322\store_pos_hotel_demo_20260302(2)\20251021_ReportRTM\sp_TotalRevenueByPaymentMethod.sql
                                                                                      
-- ==================================================
                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             
CREATE   PROC [dbo].[sp_TotalRevenueByPaymentMethod] (@DateFrom DATETime,	@DateTo DATETime,@outlet varchar(10)= '')
                                                                                                                                          

                                                                                                                                                                                                                                                             
As
                                                                                                                                                                                                                                                           
SELECT httt.FirstName AS Type,
                                                                                                                                                                                                                               
	sum(bill.Amount) AS Amount,
                                                                                                                                                                                                                                 
	count(httt.ma) AS CheckCount
                                                                                                                                                                                                                                
FROM sp3000 bill
                                                                                                                                                                                                                                             
LEFT JOIN SP3002 tt ON tt.PaymentID = bill.PaymentID
                                                                                                                                                                                                         
LEFT JOIN SP1326 httt ON httt.Ma = tt.PaymentMethod
                                                                                                                                                                                                          
WHERE bill.Edit = 0 AND bill.PaymentID IS NOT NULL
                                                                                                                                                                                                           
and (@outlet = '' OR bill.Outlet =@outlet)
                                                                                                                                                                                                                   
and Convert(date,bill.CreatedDate) between Convert(date,@DateFrom) and Convert(date,@DateTo) 
                                                                                                                                                                
GROUP BY httt.FirstName
                                                                                                                                                                                                                                      

                                                                                                                                                                                                                                                             
 
                                                                                                                                                                                                                                                            

                                                                                                                                                                                                                                                             

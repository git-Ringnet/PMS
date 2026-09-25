Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
-- ==================================================
                                                                                                                                                                                                        
-- D? li?u t? file: C:\Users\sproud-admin\Desktop\UPDATE\POS\Army_Store_20260322\store_pos_hotel_demo_20260302(2)\20251021_ReportRTM\sp_TotalRevenueTopProducts.sql
                                                                                          
-- ==================================================
                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             
CREATE   PROC [dbo].[sp_TotalRevenueTopProducts]
                                                                                                                                                                                                             
(@outlet varchar(10)= '')
                                                                                                                                                                                                                                    
As
                                                                                                                                                                                                                                                           
SELECT top 10
                                                                                                                                                                                                                                                
sp.NameProduct as Product,
                                                                                                                                                                                                                                   
sum(ctbill.Quantity) as Quantity,
                                                                                                                                                                                                                            
sum(ctbill.TotalAmount) as TotalAmount
                                                                                                                                                                                                                       
FROM sp5000 bill
                                                                                                                                                                                                                                             
LEFT JOIN sp5100 ctbill ON ctbill.BillId = bill.BillId
                                                                                                                                                                                                       
LEFT JOIN sp5300 sp ON sp.ProductId = ctbill.ProductId
                                                                                                                                                                                                       
WHERE bill.Edit = 0 AND bill.Deleted = 0
                                                                                                                                                                                                                     
and (@outlet = '' OR bill.Oulet = @outlet)
                                                                                                                                                                                                                   
GROUP BY sp.NameProduct
                                                                                                                                                                                                                                      
order by Quantity desc
                                                                                                                                                                                                                                       

                                                                                                                                                                                                                                                             
 
                                                                                                                                                                                                                                                            

                                                                                                                                                                                                                                                             

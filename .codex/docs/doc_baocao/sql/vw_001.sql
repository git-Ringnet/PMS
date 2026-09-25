Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
CREATE   view [dbo].[vw_001] as
                                                                                                                                                                                                                              
select bk.*, ttdk.IsAvailability, ct.Id
                                                                                                                                                                                                                      
from SP2000 bk 
                                                                                                                                                                                                                                              
left join SP1311 ttdk on bk.BookingStatus = ttdk.BookingStatusId
                                                                                                                                                                                             
left join SP1302 ct on ct.Ma = bk.TravelAgency
                                                                                                                                                                                                               

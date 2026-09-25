Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             
-- Optimized func_075 (v2 - set-based)
                                                                                                                                                                                                                       
-- v1 (CROSS APPLY dedup) REGRESSED: the COUNT subqueries run once per GROUP, not per row,
                                                                                                                                                                   
-- so moving them to a per-row APPLY added work. Reverted.
                                                                                                                                                                                                   
--
                                                                                                                                                                                                                                                           
-- Real cost in block 1: the date-series OUTER APPLY was correlated by M.Date, so SP2100 +
                                                                                                                                                                   
-- SP2200 were re-scanned once per day (~365x). Rewritten set-based: SP2100/SP2200 join the
                                                                                                                                                                  
-- date tally directly (scan once), stays expand via JOIN. Semantics preserved:
                                                                                                                                                                              
--   * OUTER APPLY + "where A.BookingId is not null" == INNER JOIN (kept as inner).
                                                                                                                                                                          
--   * inner per-(day,room) GROUP BY preserved (same key list + M.Date).
                                                                                                                                                                                     
--   * Nationality comes from vw_001 (the old inner SP2300 k join was dead -> dropped).
                                                                                                                                                                      
--   * Adult COUNT kept as a per-group scalar subquery (cheap, as in the original).
                                                                                                                                                                          
-- NOTE: func_012(pt.Ma, M.Date) is still called per (room x day) - it takes a single date and
                                                                                                                                                               
-- its body isn't available to range-hoist. If func_075 is still slow after this, func_012 is
                                                                                                                                                                
-- the cost: share it. VALIDATE (financial): DIFF old vs new output before deploy.
                                                                                                                                                                           
CREATE   function [dbo].[func_075](@From date,@To date)
                                                                                                                                                                                                      
	returns @re table(
                                                                                                                                                                                                                                          
		[Date] date,
                                                                                                                                                                                                                                               
		[AmountNotTax] decimal(18,2),
                                                                                                                                                                                                                              
		[Amount] decimal(18,2),
                                                                                                                                                                                                                                    
		[Company] varchar(20),
                                                                                                                                                                                                                                     
		[MarketSegment] varchar(20),
                                                                                                                                                                                                                               
		[SourceCode] varchar(20),
                                                                                                                                                                                                                                  
		[National] varchar(20),
                                                                                                                                                                                                                                    
		[BokingCode] varchar(20),
                                                                                                                                                                                                                                  
		[Room] varchar(20),
                                                                                                                                                                                                                                        
		[RentalRoomId] varchar(20)
                                                                                                                                                                                                                                 
	)
                                                                                                                                                                                                                                                           
as
                                                                                                                                                                                                                                                           
begin
                                                                                                                                                                                                                                                        

                                                                                                                                                                                                                                                             
	declare @TaxAmount decimal(18,4)=(select top 1 ((1+(ServiceCharge/100))*(1+(Tax/100)))TaxAmount from SP1306 where Ma='BF')
                                                                                                                                  
	declare @BreakfastRate decimal(18,4)=(select Value FROM SP1600 where Parameter='BreakfastRateLon')
                                                                                                                                                          

                                                                                                                                                                                                                                                             
	declare @DateHT Date = (select top 1 SystemDate from SP1500)
                                                                                                                                                                                                
	declare @tTo date =@To, @tFrom date =@DateHT
                                                                                                                                                                                                                
	if(@tFrom>@From and @tFrom<@To)
                                                                                                                                                                                                                             
	begin
                                                                                                                                                                                                                                                       
		set @tFrom=dateadd(day,1,@tFrom)
                                                                                                                                                                                                                           
		set @tTo=dateadd(day,1,@To)
                                                                                                                                                                                                                                
	end
                                                                                                                                                                                                                                                         
	else if ((@tFrom=@From and @tFrom=@To) or (@tFrom>@From and @tFrom=@To))
                                                                                                                                                                                    
	begin
                                                                                                                                                                                                                                                       
		set @tFrom=dateadd(day,1,@tFrom)
                                                                                                                                                                                                                           
		set @tTo=@tFrom
                                                                                                                                                                                                                                            
	end
                                                                                                                                                                                                                                                         
	else if (@tFrom>@From and @tFrom>@To)
                                                                                                                                                                                                                       
	begin
                                                                                                                                                                                                                                                       
		set @tFrom=dateadd(day,1,@tFrom)
                                                                                                                                                                                                                           
		set @tTo=dateadd(day,-1,@tFrom)
                                                                                                                                                                                                                            
	end
                                                                                                                                                                                                                                                         
	else
                                                                                                                                                                                                                                                        
	begin
                                                                                                                                                                                                                                                       
		set @tFrom=dateadd(day,1,@From)
                                                                                                                                                                                                                            
		set @tTo=dateadd(day,1,@To)
                                                                                                                                                                                                                                
	end
                                                                                                                                                                                                                                                         

                                                                                                                                                                                                                                                             
	insert into @re
                                                                                                                                                                                                                                             
	select
                                                                                                                                                                                                                                                      
	dateadd(day, -1, X.Date)  as Date,
                                                                                                                                                                                                                          
	(sum(X.BreakfatsAmount) /@TaxAmount) BreakfatsAmount,
                                                                                                                                                                                                       
	sum(X.BreakfatsAmount) BreakfatsAmountTax,
                                                                                                                                                                                                                  
	vw_001.Id TravelAgency,
                                                                                                                                                                                                                                     
	vw_001.MarketSegment,
                                                                                                                                                                                                                                       
	vw_001.SourceCode,
                                                                                                                                                                                                                                          
	vw_001.Nationality,
                                                                                                                                                                                                                                         
	X.BookingId,
                                                                                                                                                                                                                                                
	X.Room,
                                                                                                                                                                                                                                                     
	X.Ma
                                                                                                                                                                                                                                                        
	from
                                                                                                                                                                                                                                                        
	(
                                                                                                                                                                                                                                                           
		select
                                                                                                                                                                                                                                                     
			M.Date,
                                                                                                                                                                                                                                                   
			pt.Ma,
                                                                                                                                                                                                                                                    
			pt.Room,
                                                                                                                                                                                                                                                  
			pt.BookingId,
                                                                                                                                                                                                                                             
			@BreakfastRate*(select COUNT(CustomerId) from SP2200 where RentalRoomId=pt.Ma and M.Date between ArrivalDate+1 and CheckoutDate and Status != 3) +
                                                                                                        
			(SELECT ISNULL(SUM(Rate), 0) FROM dbo.func_012 (pt.Ma,M.Date) WHERE Breakfast = 1 and  ExtraBreakfast=0) as BreakfatsAmount
                                                                                                                               
		from SP2100 pt
                                                                                                                                                                                                                                             
		join SP2200 ptk on pt.Ma = ptk.RentalRoomId
                                                                                                                                                                                                                
		join (
                                                                                                                                                                                                                                                     
			select  top(Datediff(day,@tFrom,@tTo)+1)
                                                                                                                                                                                                                  
			Date=DATEADD(DAY,row_number()over (order by a.object_id)-1,@tFrom)
                                                                                                                                                                                        
			from sys.all_objects a
                                                                                                                                                                                                                                    
			cross join sys.all_objects b
                                                                                                                                                                                                                              
		) M on M.Date between ptk.ArrivalDate+1 and ptk.CheckoutDate
                                                                                                                                                                                               
		where (pt.Room is null or pt.Room not like '0%')
                                                                                                                                                                                                           
			and pt.Status  in (0,1,2,100) and pt.Breakfast <> 0
                                                                                                                                                                                                       
			and ptk.Status != 3 and ptk.IsMainGuest = 1
                                                                                                                                                                                                               
			and pt.Ma not in (select Ma from SP4003 where ActualArrivalDate = M.Date)
                                                                                                                                                                                 
		group by M.Date, pt.Ma ,pt.Adult,pt.Room, pt.BookingId, ptk.Status, ptk.CheckoutDate, pt.Status,pt.CheckoutDate,ptk.RentalRoomId,ptk.ArrivalDate
                                                                                                           
	) X
                                                                                                                                                                                                                                                         
	left join vw_001 on vw_001.Ma=X.BookingId
                                                                                                                                                                                                                   
	where X.BookingId is not null and (X.Date < @DateHT or vw_001.IsAvailability = 1)
                                                                                                                                                                           
	group by X.Date, vw_001.Id, X.BookingId, vw_001.MarketSegment, vw_001.Nationality, X.Room, X.Ma, vw_001.SourceCode
                                                                                                                                          

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             
	insert into @re
                                                                                                                                                                                                                                             
	select
                                                                                                                                                                                                                                                      
	hd.Date,
                                                                                                                                                                                                                                                    
	isnull(sum(case when hddvct.ServiceId = 'BF' and hd.ServiceId='RM' then hddvct.OriginalRate else 0 end),0) as MoneyBreakfastRoom ,
                                                                                                                          
	isnull(sum(case when hddvct.ServiceId = 'BF' and hd.ServiceId='RM' then hddvct.Amount else 0 end),0) as MoneyBreakfastRoomTax ,
                                                                                                                             
	ct.Id TravelAgency,
                                                                                                                                                                                                                                         
	dk.MarketSegment,
                                                                                                                                                                                                                                           
	dk.SourceCode,
                                                                                                                                                                                                                                              
	Nationality,
                                                                                                                                                                                                                                                
	pt.BookingId,
                                                                                                                                                                                                                                               
	pt.Room,
                                                                                                                                                                                                                                                    
	pt.Ma
                                                                                                                                                                                                                                                       
	from SP3000 hd
                                                                                                                                                                                                                                              
	left join SP3001 hddvct on hd.Ma=hddvct.BillServiceId
                                                                                                                                                                                                       
	inner join SP2100 pt on pt.Ma=hd.RentalRoomId1
                                                                                                                                                                                                              
	inner join vw_001 dk on dk.Ma=pt.BookingId
                                                                                                                                                                                                                  
			left join SP1302 ct on ct.Ma = dk.TravelAgency
                                                                                                                                                                                                            
	where hd.ServiceId='RM' and Edit=0 and hd.Date between @From and @To and
                                                                                                                                                                                    
	(hd.Date < @DateHT or dk.IsAvailability = 1)
                                                                                                                                                                                                                
	group by  hd.Date,ct.Id,dk.MarketSegment,Nationality, pt.BookingId,Room,pt.Ma, dk.SourceCode
                                                                                                                                                                

                                                                                                                                                                                                                                                             
	return
                                                                                                                                                                                                                                                      
end
                                                                                                                                                                                                                                                          

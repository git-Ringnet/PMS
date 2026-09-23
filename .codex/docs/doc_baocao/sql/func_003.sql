Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

                                                                                                                                                                                                                                                             
-- func_003 - REVERTED TO ORIGINAL (working, ~2461ms).
                                                                                                                                                                                                       
-- Two optimization attempts were made and BOTH failed:
                                                                                                                                                                                                      
--   v1 (inline func_058, base-table joins): no measurable gain.
                                                                                                                                                                                             
--   v2 (@ooo table variable): HUNG (>1m46s) - table-variable cardinality misestimation
                                                                                                                                                                      
--       (@ooo estimated at 1 row) produced a runaway nested-loop plan.
                                                                                                                                                                                      
-- Conclusion: func_003 / func_058 (OOO over SP4001) is not safely optimizable from here without
                                                                                                                                                             
-- a live execution plan + SP4001 sizing. The ~2.5s is left as-is. Real fix = daily-summary table.
                                                                                                                                                           
CREATE     function [dbo].[func_003](@fromDate Date, @toDate Date)
                                                                                                                                                                                           
returns @re table(
                                                                                                                                                                                                                                           
		Date date,
                                                                                                                                                                                                                                                 
		Quantity int
                                                                                                                                                                                                                                               
)
                                                                                                                                                                                                                                                            
 as
                                                                                                                                                                                                                                                          
begin
                                                                                                                                                                                                                                                        
		;WITH n AS
                                                                                                                                                                                                                                                 
		(
                                                                                                                                                                                                                                                          
		  SELECT TOP (DATEDIFF(DAY, @fromDate, @toDate) + 1) n = ROW_NUMBER() OVER (ORDER BY [object_id])
                                                                                                                                                          
		  FROM sys.all_objects
                                                                                                                                                                                                                                     
		)
                                                                                                                                                                                                                                                          
		insert into @re
                                                                                                                                                                                                                                            
		SELECT  DATEADD(DAY, n-1, @fromDate) as Date ,(select dbo.func_058(-1, DATEADD(DAY, n-1, @fromDate))) as Qty FROM n;
                                                                                                                                       

                                                                                                                                                                                                                                                             
	return
                                                                                                                                                                                                                                                      
end
                                                                                                                                                                                                                                                          

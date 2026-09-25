Text                                                                                                                                                                                                                                                           
---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
CREATE function [dbo].[func_061]
                                                                                                                                                                                                                             
(
                                                                                                                                                                                                                                                            
    @String NVARCHAR(4000),
                                                                                                                                                                                                                                  
    @Delimiter NCHAR(1)
                                                                                                                                                                                                                                      
)
                                                                                                                                                                                                                                                            
RETURNS TABLE
                                                                                                                                                                                                                                                
AS
                                                                                                                                                                                                                                                           
RETURN
                                                                                                                                                                                                                                                       
(
                                                                                                                                                                                                                                                            
    WITH func_061(stpos,endpos)
                                                                                                                                                                                                                              
    AS(
                                                                                                                                                                                                                                                      
        SELECT 0 AS stpos, CHARINDEX(@Delimiter,@String) AS endpos
                                                                                                                                                                                           
        UNION ALL
                                                                                                                                                                                                                                            
        SELECT endpos+1, CHARINDEX(@Delimiter,@String,endpos+1)
                                                                                                                                                                                              
            FROM func_061
                                                                                                                                                                                                                                    
            WHERE endpos > 0
                                                                                                                                                                                                                                 
    )
                                                                                                                                                                                                                                                        
    SELECT 'Id' = ROW_NUMBER() OVER (ORDER BY (SELECT 1)),
                                                                                                                                                                                                   
        'Data' = LTrim(RTrim(SUBSTRING(@String,stpos,COALESCE(NULLIF(endpos,0),LEN(@String)+1)-stpos)))
                                                                                                                                                      
    FROM func_061
                                                                                                                                                                                                                                            
)
                                                                                                                                                                                                                                                            

                                                                                                                                                                                                                                                             

                                                                                                                                                                                                                                                             

USE [ProVistaDTXHotel]
GO

/****** Object:  UserDefinedFunction [dbo].[func_081]    Script Date: 8/27/2026 4:37:27 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

create   function [dbo].[func_081] ()
returns table as
return
	with ChangeRoom as (
	select *, Ma as ma_ban_dau, Ma as ma_dang_xet, MoveRoom as MoveRoom1, Status as Status1, 0 as cap_do from Sp2100 
	where moveroom is not null and status = 100
	union all
	select pt.*, cr.ma_ban_dau as ma_hien_tai, pt.Ma as ma_dang_xet, pt.MoveRoom as MoveRoom1, pt.Status as Status1, cr.cap_do + 1 from SP2100 pt 
	inner join ChangeRoom cr
	on cr.MoveRoom = pt.Ma
	)
	select distinct * from (
	SELECT 
		cp.*,
		LAST_VALUE(cp.ma_dang_xet) OVER (
			PARTITION BY cp.ma_ban_dau 
			ORDER BY cp.cap_do 
			ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
		) as ma_phong_cuoi
	FROM ChangeRoom cp ) A 
	where A.Status = 100 and cap_do = (select max(cap_do) from ChangeRoom where A.ma_dang_xet = ChangeRoom.ma_dang_xet and status = 100)
	and cap_do = 0
--ORDER BY cp.ma_ban_dau, cp.cap_do;
GO


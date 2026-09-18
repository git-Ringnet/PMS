USE [ProVistaDTXHotel]
GO

/****** Object:  View [dbo].[vw_018]    Script Date: 9/17/2026 10:45:45 AM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


 
 


CREATE   VIEW [dbo].[vw_018]
as
SELECT     ReferenceId,hddv.*, 
			isnull((select Sum(OriginalRate) from SP3001 where BillServiceId = hddv.Ma), hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100))) OriginalRate, 
			isnull((select Sum(ServiceChargeAmount) from SP3001 where BillServiceId = hddv.Ma),  hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) * (hddv.ServiceCharge / 100)) ServiceChargeAmount,
			isnull((select Sum(SpecialTaxAmount) from SP3001 where BillServiceId = hddv.Ma), hddv.Amount / (((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) + hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) * (hddv.ServiceCharge / 100)) * (hddv.SpecialTax / 100)) SpecialTaxAmount,
			isnull((select Sum(TaxAmount) from SP3001 where BillServiceId = hddv.Ma), (hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) +  hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) * (hddv.ServiceCharge / 100) + hddv.Amount / (((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) + hddv.Amount / ((1 +hddv.ServiceCharge / 100) * (1 + hddv.SpecialTax / 100) * (1 + hddv.Tax /100)) * (hddv.ServiceCharge / 100)) * (hddv.SpecialTax / 100)) * (hddv.Tax / 100)) TaxAmount,
            CASE WHEN hddv.RegisterId1 IS NULL THEN CONVERT(varchar, hddv.RentalRoomId1 + hddv.CustomerId1) ELSE CONVERT(varchar,hddv.RegisterId1) END AS CommonGuestId1, 
			CASE WHEN RegisterID2 IS NULL THEN CONVERT(varchar, hddv.RentalRoomId2 + hddv.CustomerId2) ELSE CONVERT(varchar, hddv.RegisterID2) END AS CommonGuestId2, 
			pt.Room, 
            CASE hddv.Status WHEN 1 THEN 0 ELSE hddv.TotalAmount0 END AS Cash, 
            CASE hddv.Status WHEN 1 THEN hddv.TotalAmount0 ELSE 0 END AS Credit,
			dv.Service, 
			pt.Adult, 
			pt.Child, 
			pt.ArrivalDate, 
			pt.NumOfDays, 
            isnull(pt.BookingId,hddv.RegisterId1) as BookingId, 
			dv.STT, 
			ptk.Status AS GuestStatus, 
			hdbh.Ma as BillID, 
			SP6000.Ma BillHK, 
			SP5000.BillId BillFB, 
			hdbh.PaymentDate, 
			hdbh.OpenTime as TimePayment, 
			hdbh.Department as PaymentDepartment, 
			SP5000.TableId,
			SP5000.BillCode,
			hddv.Username as Payer,
			(SP5000.CreatedDate + CAST(SP5000.CreateTime AS DATETIME)) as BillCreate,
			pt.CheckoutTime,
			pt.CheckoutDate,
			vat.IsTemp as IsTempVat,
			k.FirstName
FROM        dbo.SP3000 hddv 
			LEFT JOIN dbo.SP2100 pt ON isnull(hddv.RentalRoomId2, hddv.RentalRoomId1) = pt.Ma 
			LEFT JOIN dbo.SP2200 ptk ON isnull(hddv.RentalRoomId2, hddv.RentalRoomId1) = ptk.RentalRoomId AND isnull(hddv.CustomerId2,hddv.CustomerId1) = ptk.CustomerId 
			LEFT JOIN dbo.SP2300 k ON k.Id = ptk.CustomerId 
			LEFT JOIN dbo.SP1306 dv ON hddv.ServiceId = dv.Ma 
			LEFT JOIN dbo.SP3003 hdbh ON hddv.InvoiceId = hdbh.Ma 
			LEFT JOIN dbo.SP8004 vat on vat.ID = hddv.VatId and vat.Deleted = 0 
			left join dbo.SP6000 on hddv.Ma = dbo.SP6000.BillServiceId and Edit = 0 
			left join dbo.SP5000 on hddv.Ma = dbo.SP5000.ServicePayId and hddv.Edit = 0



















GO


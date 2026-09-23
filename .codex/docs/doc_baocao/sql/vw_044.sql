CREATE    VIEW [dbo].[vw_044]
as
SELECT     hddv.*, (((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * 100) / (100 + hddv.ServiceCharge) AS OriginalRate, 
                      (((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * hddv.ServiceCharge) / (100 + hddv.ServiceCharge) AS ServiceChargeAmount, 
                      ((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * hddv.SpecialTax) / (100 + hddv.SpecialTax) AS SpecialTaxAmount, 
                      (hddv.TotalAmount0 * hddv.Tax) / (100 + hddv.Tax) AS TaxAmount, (hddv.TotalAmount0 * hddv.Tax) / (100 + hddv.Tax) 
                      + ((hddv.TotalAmount0 * 100) / (100 + hddv.Tax) * hddv.SpecialTax) / (100 + hddv.SpecialTax) AS TaxAmountTotal, 
                      hddv.TotalAmount0 AS TienQDTD, (((hddv.BillExchangeAmount * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * 100) 
                      / (100 + hddv.ServiceCharge) AS HDOriginalRate, (((hddv.BillExchangeAmount * 100) / (100 + hddv.Tax) * 100) / (100 + hddv.SpecialTax) * hddv.ServiceCharge) 
                      / (100 + hddv.ServiceCharge) AS HDServiceChargeAmount, ((hddv.BillExchangeAmount * 100) / (100 + hddv.Tax) * hddv.SpecialTax) / (100 + hddv.SpecialTax) 
                      AS HDSpecialTaxAmount, (hddv.BillExchangeAmount * hddv.Tax) / (100 + hddv.Tax) AS HDTaxAmount, (hddv.BillExchangeAmount * hddv.Tax) / (100 + hddv.Tax) 
                      + ((hddv.BillExchangeAmount * 100) / (100 + hddv.Tax) * hddv.SpecialTax) / (100 + hddv.SpecialTax) AS HDTotalTaxAmount, 
                      CASE WHEN hddv.RegisterId1 IS NULL THEN CONVERT(varchar, hddv.RentalRoomId1 + hddv.CustomerId1) ELSE CONVERT(varchar, 
                      hddv.RegisterId1) END AS CommonGuestId1, CASE WHEN RegisterID2 IS NULL THEN CONVERT(varchar, hddv.RentalRoomId2 + hddv.CustomerId2) 
                      ELSE CONVERT(varchar, hddv.RegisterID2) END AS CommonGuestId2, pt.Room, 
                      CASE hddv.Status WHEN 1 THEN 0 ELSE hddv.TotalAmount0 END AS Cash, 
                      CASE hddv.Status WHEN 1 THEN hddv.TotalAmount0 ELSE 0 END AS Credit, dv.Service, pt.Adult, pt.Child, pt.ArrivalDate, pt.NumOfDays, 
                    pt.BookingId, dv.STT, ptk.Status AS GuestStatus, hdbh.BillID,
					  case when RegisterID2 is null then pt.ArrivalDate else dk.ArrivalDate end as ArrivalDateVW,
					  case when RegisterID2 is null then pt.ArrivalDate + pt.ActualNumOfDays else dk.ArrivalDate + dk.NumOfDays end as DepartureDateVW
FROM         dbo.SP3000 hddv LEFT OUTER JOIN
                      dbo.SP2100 pt ON hddv.RentalRoomId2 = pt.Ma LEFT OUTER JOIN
                      dbo.SP2000 dk ON hddv.RegisterID2 = dk.Ma LEFT OUTER JOIN
                      dbo.SP2200 ptk ON hddv.RentalRoomId2 = ptk.RentalRoomId AND hddv.CustomerId2 = ptk.CustomerId INNER JOIN
                      dbo.SP1306 dv ON hddv.ServiceId = dv.Ma LEFT OUTER JOIN
                      dbo.SP3003 hdbh ON hddv.InvoiceId = hdbh.Ma
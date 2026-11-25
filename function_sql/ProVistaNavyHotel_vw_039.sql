







CREATE   VIEW [dbo].[vw_039]

as

SELECT     dbo.SP1312.FOCType AS FirstNameFOCType, dbo.SP6000.*, dbo.vw_018.OpenTime, 

                      dbo.vw_018.RefId, dbo.vw_018.Guest, dbo.vw_018.ServiceCharge, dbo.vw_018.SpecialTax, dbo.vw_018.Tax, 

                      dbo.vw_018.Edit, dbo.vw_018.PaymentID, dbo.vw_018.VATNumber, dbo.vw_018.RentalRoomId2, dbo.vw_018.CustomerId2, 

                      dbo.vw_018.Username, dbo.vw_018.Ca, dbo.vw_018.CommonGuestId1, dbo.vw_018.CommonGuestId2, 

	MienPhi=case when SP6000.FOCType=0 then convert(bit, 0) else convert(bit, 1) end,

	TaxAmount = SP6000.BillAmount * Tax/100 / (1 + Tax/100),

	SpecialTaxAmount = SP6000.BillAmount * 1 / (1 + Tax/100) * SpecialTax/100,

	TaxAmountTotal = SP6000.BillAmount * Tax/100 / (1 + Tax/100)

		+ SP6000.BillAmount * 1 / (1 + Tax/100) * SpecialTax/100,

	ServicesChargeAmount = SP6000.BillAmount * 1 / (1 + Tax/100) * 1/ (1 + SpecialTax/100) * ServiceCharge/100 / (1 + ServiceCharge/100),

	OriginalRate = SP6000.BillAmount * 1 / (1 + Tax/100) * 1/ (1 + SpecialTax/100) * 1 / (1 + ServiceCharge/100)



FROM         dbo.SP6000 left JOIN

                      dbo.vw_018 ON dbo.SP6000.BillServiceId = dbo.vw_018.Ma LEFT OUTER JOIN

                      dbo.SP1312 ON dbo.SP6000.FOCType = dbo.SP1312.Ma






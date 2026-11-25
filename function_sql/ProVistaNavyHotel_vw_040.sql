





CREATE     VIEW [dbo].[vw_040]

as

SELECT        dbo.SP6001.BillId, dbo.vw_039.Date, dbo.vw_039.ServiceCharge, dbo.vw_039.SpecialTax, dbo.vw_039.Tax, dbo.SP6001.MaProduct, 

                         dbo.SP6001.Product, dbo.SP6001.Rate * (1 - dbo.SP6001.Discount / 100) AS Rate, dbo.SP6001.Rate as StandardRate, dbo.SP6001.Discount, 

                         dbo.SP6001.Quantity, dbo.SP6005.Currency, dbo.SP6002.TypeName as ProductType, dbo.SP6001.TotalAmount * (100 - dbo.SP6001.Discount) 

                         / 100  AS Total, isnull(dbo.sp5403.Id,0) AS MaFOCType, dbo.sp5403.Name, dbo.vw_039.Status, 

                         dbo.vw_039.Username, dbo.vw_039.Ca, dbo.vw_039.MienPhi, dbo.vw_039.Outlet, dbo.vw_039.Department

FROM            dbo.SP6002 INNER JOIN

                         dbo.SP6005 ON dbo.SP6002.Ma = dbo.SP6005.ProductType INNER JOIN

                         dbo.vw_039 INNER JOIN

                         dbo.SP6001 ON dbo.vw_039.Ma = dbo.SP6001.BillId ON dbo.SP6005.Ma = dbo.SP6001.MaProduct LEFT OUTER JOIN

                         dbo.SP5403 ON dbo.vw_039.FOCType = dbo.SP5403.Id










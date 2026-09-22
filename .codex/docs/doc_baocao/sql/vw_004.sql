create VIEW [dbo].[vw_004]
AS
SELECT     bcdt.*, CASE PaymentMethod WHEN 'AC' THEN NULL ELSE TienQDTD END AS CashVND, CASE PaymentMethod WHEN 'AC' THEN TienQDTD ELSE NULL 
                      END AS AccountVND, CASE PaymentMethod WHEN 'AC' THEN NULL ELSE Amount END AS Cash, CASE PaymentMethod WHEN 'AC' THEN Amount ELSE NULL END AS Account, 
                      CASE PaymentMethod WHEN 'AC' THEN NULL ELSE Currency END AS CashCurrency, CASE PaymentMethod WHEN 'AC' THEN Currency ELSE NULL END AS AccountCurrency
FROM         dbo.vw_003 bcdt
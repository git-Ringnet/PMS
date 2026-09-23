create VIEW [dbo].[vw_003]
AS
SELECT     tt.*, pt.Room AS Room, httt.STT AS STT, httt.ShortName, hdbh.BillID
FROM         dbo.vw_025 tt LEFT OUTER JOIN
                      dbo.SP3003 hdbh ON tt.Ma = hdbh.Ma LEFT OUTER JOIN
                      dbo.SP1326 httt ON tt.PaymentMethod = httt.Ma LEFT OUTER JOIN
                      dbo.SP2100 pt ON tt.RentalRoomId2 = pt.Ma
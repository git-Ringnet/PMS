create VIEW [dbo].[vw_031]
AS
SELECT     BookingId, BookingName, Room, CustomerId, Guest, ArrivalDate, DepartureDate, ActualDepartureDate, Information, GuestName, GroupStatus, Adult, Rate, ExtraBed, ExtraBedRate, RoomType, ArrivalTime, 
                      Username, Ca, RoomTypeId, KindRoomId, RoomKind, RentalRoomId, Color, NumOfDays, Child, RoomRateCode, Note, RateTotal, Company, ArrivalDateGuest, BreakfastIncluded, Passport, 
                       NoteBooking, ActualNumOfDays, ActualArrivalDate, TravelAgency, Status, WaitingList, HouseUse, Nationality, RoomHouseUse, WalkIn, Title, Breakfast,Pack3
FROM         dbo.vw_030 AS A
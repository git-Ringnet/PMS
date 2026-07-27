# Table: DetailBreakfastVoucher
- **Est. Row Count**: 180

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Date** | `date` | YES | NO |  |  |
| 2 | **RentalRoom** | `varchar(10)` | YES | NO |  |  |
| 3 | **Room** | `varchar(10)` | YES | NO |  |  |
| 4 | **GuestName** | `nvarchar(200)` | YES | NO |  |  |
| 5 | **Nationality** | `nvarchar(100)` | YES | NO |  |  |
| 6 | **ArrivalDate** | `date` | YES | NO |  |  |
| 7 | **DepartureDate** | `date` | YES | NO |  |  |
| 8 | **Note** | `nvarchar(200)` | YES | NO |  |  |
| 9 | **BookingId** | `bigint` | YES | NO |  |  |
| 10 | **BookingName** | `nvarchar(1000)` | YES | NO |  |  |
| 11 | **Adult** | `bit` | YES | NO |  |  |
| 12 | **Child** | `bit` | YES | NO |  |  |
| 13 | **ChildMP** | `bit` | YES | NO |  |  |
| 14 | **ChildKAS** | `bit` | YES | NO |  |  |
| 15 | **Status** | `varchar(5)` | YES | NO |  |  |
| 16 | **CheckoutDate** | `date` | YES | NO |  |  |
| 17 | **isMainGuest** | `bit` | YES | NO |  |  |
| 18 | **RoomType** | `nvarchar(50)` | YES | NO |  |  |
| 19 | **Breakfast** | `int` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

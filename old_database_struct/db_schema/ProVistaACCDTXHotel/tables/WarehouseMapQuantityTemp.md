# Table: WarehouseMapQuantityTemp
- **Est. Row Count**: 0

## Columns
| # | Column Name | Data Type | Nullable | Identity | Default | Key |
|---|-------------|-----------|----------|----------|---------|-----|
| 1 | **Id** | `int` | NO | NO |  | PK |
| 2 | **Orders** | `int` | YES | NO |  |  |
| 3 | **WarehouseInput** | `varchar(50)` | YES | NO |  |  |
| 4 | **WarehouseOutput** | `varchar(50)` | YES | NO |  |  |
| 5 | **InventoryID** | `varchar(50)` | YES | NO |  |  |
| 6 | **InventoryName** | `varchar(250)` | YES | NO |  |  |
| 7 | **UnitID** | `varchar(10)` | YES | NO |  |  |
| 8 | **Quantity** | `money` | YES | NO |  |  |
| 9 | **DeliveryDateCreate** | `datetime` | YES | NO |  |  |
| 10 | **TranMonth** | `int` | YES | NO |  |  |
| 11 | **TranYear** | `int` | YES | NO |  |  |
| 12 | **DivisionID** | `varchar(20)` | YES | NO |  |  |
| 13 | **IsTransfered** | `bit` | YES | NO |  |  |
| 14 | **IsDelivery** | `bit` | YES | NO |  |  |
| 15 | **TransferedDate** | `datetime` | YES | NO |  |  |
| 16 | **DeliveryDate** | `datetime` | YES | NO |  |  |
| 17 | **TransferedUser** | `varchar(50)` | YES | NO |  |  |
| 18 | **DeliveryUser** | `varchar(50)` | YES | NO |  |  |
| 19 | **BeginVoucherID** | `varchar(50)` | YES | NO |  |  |
| 20 | **DeliveryVoucherID** | `varchar(50)` | YES | NO |  |  |
| 21 | **TransferedVoucherID** | `varchar(50)` | YES | NO |  |  |

## Relationships
### Outgoing Foreign Keys (References)
*None*

### Incoming Foreign Keys (Referenced By)
*None*

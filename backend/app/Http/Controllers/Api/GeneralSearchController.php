<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingChild;
use App\Models\BookingRoom;
use App\Models\BookingRoomGuest;
use App\Models\Booker;
use App\Models\Company;
use App\Models\CustomerSource;
use App\Models\Market;
use App\Models\RegistrationStatus;
use App\Models\HotelSetting;
use App\Models\RoomClass;
use App\Models\RoomRateCode;
use App\Models\User;
use App\Services\GeneralSearchProjectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/** API for three Common Search tabs; a Laravel/MySQL implementation, not a legacy SP port. */
class GeneralSearchController extends Controller
{
    public function __construct(private readonly GeneralSearchProjectionService $projection) {}

    public function options()
    {
        return response()->json(['success' => true, 'data' => [
            'room_classes' => RoomClass::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'rate_codes' => RoomRateCode::query()->selectRaw('Ma as id, Ma as code, Description as name')->orderBy('Ma')->get(),
            'users' => User::where('is_active_user', true)->orderBy('name')->get(['id', 'name', 'username']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'markets' => Market::orderBy('name')->get(['id', 'name']),
            'registration_statuses' => RegistrationStatus::orderBy('name')->get(['id', 'name']),
            'customer_sources' => CustomerSource::orderBy('name')->get(['id', 'name']),
            'bookers' => Booker::orderBy('name')->get(['id', 'name']),
        ]]);
    }

    /** Lightweight type-ahead for the displayed booking code (for example GAL1). */
    public function suggestions(Request $request)
    {
        $term = trim((string) $request->validate(['q' => 'required|string|min:1|max:100'])['q']);
        $prefix = strtoupper(HotelSetting::query()->value('prefix_booking_id') ?: 'GAL');
        $upperTerm = strtoupper($term);
        $query = Booking::query()->select(['id', 'booking_name', 'external_booking_code', 'arrival_date'])
            ->orderByDesc('id');

        $query->where(function (Builder $where) use ($term, $upperTerm, $prefix) {
            // This input is explicitly "Mã BK", so never suggest a booking
            // merely because a guest/contact name happens to contain a letter.
            $where->where('external_booking_code', 'like', "%{$term}%");
            $numericId = preg_replace('/[^0-9]/', '', $term);
            if ($numericId !== '') $where->orWhere('id', (int) $numericId);
            // The prefix is an accessor, not a database column.  If the user
            // types its first characters (G/GA/GAL), every current prefix code
            // is a valid suggestion, so include them and cap to eight rows.
            if (str_starts_with($prefix, $upperTerm) || str_starts_with($upperTerm, $prefix)) $where->orWhereRaw('1 = 1');
        });

        return response()->json(['success' => true, 'data' => $query->limit(8)->get()->map(fn (Booking $booking) => [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_name' => $booking->booking_name,
            'reference_code' => $booking->external_booking_code,
            'arrival_date' => $booking->arrival_date?->toDateString(),
        ])]);
    }

    public function index(Request $request)
    {
        // Query-string values arrive as strings ("true"/"false"). Normalize
        // before validation so opening the screen with its default false value
        // does not reject every search request.
        $request->merge([
            'use_date' => filter_var($request->input('use_date', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $data = $request->validate([
            'tab' => 'nullable|in:booking,room,guest', 'from_date' => 'nullable|date', 'to_date' => 'nullable|date',
            'use_date' => 'nullable|boolean', 'search' => 'nullable|string|max:255', 'status' => 'nullable|string|max:50',
            'rate_code_id' => 'nullable|string|max:50', 'room_class_id' => 'nullable|integer', 'original_room_class_id' => 'nullable|integer',
            'created_by_user_id' => 'nullable|integer', 'sort_by' => 'nullable|string|max:40', 'sort_dir' => 'nullable|in:asc,desc',
            'booking_code' => 'nullable|string|max:50', 'reference_code' => 'nullable|string|max:100',
            'booking_name' => 'nullable|string|max:255', 'contact' => 'nullable|string|max:100',
            'company_id' => 'nullable|integer', 'market_id' => 'nullable|integer', 'registration_status_id' => 'nullable|integer',
            'booker_id' => 'nullable|integer', 'customer_source_id' => 'nullable|integer', 'booking_date' => 'nullable|date',
            'sales_person' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:10|max:200', 'page' => 'nullable|integer|min:1',
        ]);

        return match ($data['tab'] ?? 'booking') {
            'room' => $this->rooms($data), 'guest' => $this->guests($data), default => $this->bookings($data),
        };
    }

    private function bookings(array $data)
    {
        $query = Booking::with($this->bookingRelations())
            ->whereHas('bookingRooms', fn (Builder $rooms) => $this->applyRoomFilters($rooms, $data));
        $this->applyBookingFilters($query, $data);
        $this->applyBookingSearch($query, $data['search'] ?? '');
        $this->applySort($query, $data, ['booking_code' => 'id', 'arrival_date' => 'arrival_date', 'departure_date' => 'departure_date', 'nights' => 'num_of_days', 'booking_date' => 'booking_date', 'created_at' => 'created_at'], 'id');

        $page = $query->paginate($data['per_page'] ?? 50);
        return response()->json(['success' => true, 'data' => $page->through(fn (Booking $booking) => $this->projection->bookingRow($booking))]);
    }

    private function rooms(array $data)
    {
        // Paginate booking groups, not individual rooms: a booking must never
        // be split over two pages in the Room tab.
        $query = Booking::with($this->bookingRelations())->whereHas('bookingRooms', function (Builder $rooms) use ($data) {
            $rooms->where('status', '!=', BookingRoom::STATUS_MOVED);
            $this->applyRoomFilters($rooms, $data);
            $this->applyRoomSearch($rooms, $data['search'] ?? '');
        });
        $this->applyBookingFilters($query, $data);
        $this->applySort($query, $data, ['arrival_date' => 'arrival_date', 'departure_date' => 'departure_date', 'nights' => 'num_of_days'], 'arrival_date');

        $page = $query->paginate($data['per_page'] ?? 50);
        $groups = $page->getCollection()->map(function (Booking $booking) use ($data) {
            $rooms = $booking->bookingRooms->filter(fn (BookingRoom $room) => $this->roomMatches($room, $data));
            $sort = $data['sort_by'] ?? 'arrival_date';
            if (in_array($sort, ['room_number', 'arrival_date', 'departure_date', 'nights', 'rate'], true)) {
                $rooms = ($data['sort_dir'] ?? 'desc') === 'asc' ? $rooms->sortBy($sort) : $rooms->sortByDesc($sort);
            }
            return $this->projection->roomGroupRow($booking, $rooms->values());
        })->values();
        $page->setCollection($groups);
        return response()->json(['success' => true, 'data' => $page]);
    }

    private function guests(array $data)
    {
        $query = BookingRoomGuest::query()->select('booking_room_guests.*')->join('guests', 'guests.id', '=', 'booking_room_guests.guest_id')
            ->with(['guest', 'bookingRoom.booking.company', 'bookingRoom.roomClass'])
            ->whereHas('bookingRoom', fn (Builder $rooms) => $this->applyRoomFilters($rooms, $data));
        $query->whereHas('bookingRoom.booking', fn (Builder $booking) => $this->applyBookingFilters($booking, $data));
        $this->applyGuestSearch($query, $data['search'] ?? '');
        $adults = $query->get()->map(fn (BookingRoomGuest $assignment) => $this->projection->guestRow($assignment));

        // Legacy sp_043 returns both adults and children.  The Laravel schema
        // keeps children in booking_children, so merge them into one result.
        $childrenQuery = BookingChild::with(['booking.company', 'bookingRoom.roomClass'])
            ->whereNotNull('booking_room_id')
            ->whereHas('bookingRoom', fn (Builder $rooms) => $this->applyRoomFilters($rooms, $data));
        $childrenQuery->whereHas('booking', fn (Builder $booking) => $this->applyBookingFilters($booking, $data));
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $childrenQuery->where(function (Builder $where) use ($search) {
                foreach (['full_name', 'phone', 'email', 'passport_number', 'id_number'] as $column) $where->orWhere($column, 'like', "%{$search}%");
            });
        }
        $children = $childrenQuery->get()->map(fn (BookingChild $child) => $this->projection->childRow($child));

        $rows = $adults->concat($children);
        $sort = $data['sort_by'] ?? 'guest_name';
        if (in_array($sort, ['guest_name', 'arrival_date', 'departure_date', 'nights', 'room_number', 'booking_code'], true)) {
            $rows = ($data['sort_dir'] ?? 'asc') === 'desc' ? $rows->sortByDesc($sort) : $rows->sortBy($sort);
        }
        $perPage = $data['per_page'] ?? 50;
        $currentPage = (int) ($data['page'] ?? 1);
        $page = new LengthAwarePaginator($rows->forPage($currentPage, $perPage)->values(), $rows->count(), $perPage, $currentPage, [
            'path' => $requestPath = request()->url(), 'query' => request()->query(),
        ]);

        return response()->json(['success' => true, 'data' => $page]);
    }

    private function bookingRelations(): array
    {
        return ['company', 'market', 'registrationStatus', 'creator', 'bookingRooms.roomClass', 'bookingRooms.originalRoomClass', 'bookingRooms.guests.guest'];
    }

    private function applyRoomFilters(Builder $query, array $data): void
    {
        if (($data['status'] ?? '') !== '') $query->whereIn('status', array_map('intval', explode(',', $data['status'])));
        if ($data['rate_code_id'] ?? null) $query->where('rate_code', $data['rate_code_id']);
        if ($data['room_class_id'] ?? null) $query->where('room_class_id', $data['room_class_id']);
        if ($data['original_room_class_id'] ?? null) $query->whereRaw('CAST(SUBSTRING_INDEX(original_room_class_id, "-", 1) AS UNSIGNED) = ?', [$data['original_room_class_id']]);
        if (($data['use_date'] ?? false) && ($data['from_date'] ?? null) && ($data['to_date'] ?? null)) {
            $query->whereDate('arrival_date', '<=', $data['to_date'])->whereDate('departure_date', '>=', $data['from_date']);
        }
    }

    private function applyBookingSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') return;
        $query->where(function (Builder $where) use ($search) {
            foreach (['booking_name', 'contact_name', 'contact_phone', 'external_booking_code'] as $column) {
                $where->orWhere($column, 'like', "%{$search}%");
            }
            if (preg_match('/(\d+)$/', $search, $matches)) $where->orWhere('id', (int) $matches[1]);
        });
    }

    /** Apply business filters shared by Booking, Room, and Guest tabs. */
    private function applyBookingFilters(Builder $query, array $data): void
    {
        if ($data['booking_code'] ?? null) {
            $code = trim($data['booking_code']);
            $query->where(function (Builder $where) use ($code) {
                $where->where('external_booking_code', 'like', "%{$code}%");
                // booking_code is an accessor such as GAL1.  The database
                // only stores its numeric id, so remove the hotel prefix.
                $numericId = preg_replace('/[^0-9]/', '', $code);
                if ($numericId !== '') $where->orWhere('id', (int) $numericId);
            });
        }
        foreach ([
            'reference_code' => 'external_booking_code', 'booking_name' => 'booking_name', 'sales_person' => 'sales_person',
        ] as $input => $column) {
            if ($data[$input] ?? null) $query->where($column, 'like', '%'.trim($data[$input]).'%');
        }
        if ($data['contact'] ?? null) {
            $value = trim($data['contact']);
            $query->where(fn (Builder $where) => $where->where('contact_name', 'like', "%{$value}%")->orWhere('contact_phone', 'like', "%{$value}%"));
        }
        foreach (['company_id', 'market_id', 'registration_status_id', 'booker_id', 'customer_source_id', 'created_by_user_id'] as $column) {
            if ($data[$column] ?? null) $query->where($column, $data[$column]);
        }
        if ($data['booking_date'] ?? null) $query->whereDate('booking_date', $data['booking_date']);
    }

    private function applyRoomSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') return;
        $query->where(function (Builder $where) use ($search) {
            $this->applyLikeSearch($where, $search, ['room_number', 'id']);
            $where->orWhereHas('booking', fn (Builder $booking) => $this->applyBookingSearch($booking, $search));
        });
    }

    private function applyGuestSearch(Builder $query, string $search): void
    {
        $this->applyLikeSearch($query, $search, ['guests.full_name', 'guests.phone', 'guests.email', 'guests.passport_number', 'guests.id_number']);
    }

    private function applyLikeSearch(Builder $query, string $search, array $columns): void
    {
        $search = trim($search);
        if ($search === '') return;
        $query->where(function (Builder $where) use ($search, $columns) {
            foreach ($columns as $column) $where->orWhere($column, 'like', "%{$search}%");
        });
    }

    private function applySort(Builder $query, array $data, array $allowed, string $default): void
    {
        $query->orderBy($allowed[$data['sort_by'] ?? ''] ?? $default, $data['sort_dir'] ?? 'desc');
    }

    private function roomMatches(BookingRoom $room, array $data): bool
    {
        if ((int) $room->status === BookingRoom::STATUS_MOVED) return false;
        if (($data['status'] ?? '') !== '' && !in_array((int) $room->status, array_map('intval', explode(',', $data['status'])), true)) return false;
        if (($data['rate_code_id'] ?? null) && $room->rate_code !== $data['rate_code_id']) return false;
        if (($data['room_class_id'] ?? null) && (int) $room->room_class_id !== (int) $data['room_class_id']) return false;
        if (($data['original_room_class_id'] ?? null) && (int) $room->original_room_class_only_id !== (int) $data['original_room_class_id']) return false;
        if (($data['use_date'] ?? false) && ($data['from_date'] ?? null) && ($data['to_date'] ?? null)
            && ($room->arrival_date?->toDateString() > $data['to_date'] || $room->departure_date?->toDateString() < $data['from_date'])) return false;

        $search = trim($data['search'] ?? '');
        if ($search === '') return true;
        $booking = $room->booking;
        return collect([$room->id, $room->room_number, $booking?->id, $booking?->booking_code, $booking?->booking_name, $booking?->external_booking_code, $booking?->contact_name, $booking?->contact_phone])
            ->filter(fn ($value) => $value !== null)->contains(fn ($value) => Str::contains(Str::lower((string) $value), Str::lower($search)));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = BankAccount::query()->withCount('payments');

        if ($request->has('is_intermediary')) {
            $query->where('is_intermediary', $request->boolean('is_intermediary'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $like = '%' . $search . '%';
            $query->where(function ($subQuery) use ($like) {
                $subQuery->where('code', 'like', $like)
                    ->orWhere('bank_account_number', 'like', $like)
                    ->orWhere('accounting_account', 'like', $like)
                    ->orWhere('currency_code', 'like', $like)
                    ->orWhere('bank_name', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        return response()->json([
            'success' => true,
            'data' => BankAccountResource::collection(
                $query->orderBy('code')->orderBy('id')->get()
            ),
        ]);
    }

    /**
     * The current PMS chart of accounts is represented by the account values
     * maintained on payment methods. Keep this lookup read-only until a
     * dedicated chart-of-accounts catalogue is introduced.
     */
    public function lookups()
    {
        $accountingAccounts = PaymentMethod::query()
            ->whereNotNull('account')
            ->where('account', '<>', '')
            ->orderBy('account')
            ->get(['account', 'account_name'])
            ->unique('account')
            ->values()
            ->map(fn (PaymentMethod $method) => [
                'code' => $method->account,
                'name' => $method->account_name ?: $method->account,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'accounting_accounts' => $accountingAccounts,
                'currencies' => Currency::query()
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get(['code', 'name']),
            ],
        ]);
    }

    public function show(BankAccount $bankAccount)
    {
        return response()->json([
            'success' => true,
            'data' => new BankAccountResource($bankAccount->loadCount('payments')),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        try {
            $account = BankAccount::create($validated);
        } catch (QueryException $exception) {
            if ($this->isDuplicateCode($exception)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã tài khoản ngân hàng đã tồn tại.',
                    'errors' => ['code' => ['Mã tài khoản ngân hàng đã tồn tại.']],
                ], 422);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => new BankAccountResource($account),
            'message' => 'Đã thêm tài khoản ngân hàng.',
        ], 201);
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $this->validated($request, $bankAccount);

        try {
            $bankAccount->update($validated);
        } catch (QueryException $exception) {
            if ($this->isDuplicateCode($exception)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã tài khoản ngân hàng đã tồn tại.',
                    'errors' => ['code' => ['Mã tài khoản ngân hàng đã tồn tại.']],
                ], 422);
            }
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => new BankAccountResource($bankAccount->fresh()),
            'message' => 'Đã cập nhật tài khoản ngân hàng.',
        ]);
    }

    public function destroy(BankAccount $bankAccount)
    {
        if (Payment::withTrashed()->where('bank_account_id', $bankAccount->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa tài khoản ngân hàng đã được dùng trên chứng từ.',
            ], 422);
        }

        $bankAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa tài khoản ngân hàng.',
        ]);
    }

    private function validated(Request $request, ?BankAccount $bankAccount = null): array
    {
        foreach (['code', 'bank_account_number', 'bank_name', 'description'] as $field) {
            if ($request->has($field) && $request->input($field) !== null) {
                $request->merge([$field => trim((string) $request->input($field))]);
            }
        }
        foreach (['accounting_account', 'currency_code'] as $field) {
            if ($request->has($field)) {
                $value = trim((string) $request->input($field));
                $request->merge([$field => $value === '' ? null : $value]);
            }
        }

        // Codes identify a bank account across its history, including a
        // soft-deleted row, so a new account cannot silently reuse one.
        $codeRule = Rule::unique('bank_accounts', 'code');
        if ($bankAccount) {
            $codeRule->ignore($bankAccount->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'bank_account_number' => ['required', 'string', 'max:255'],
            'accounting_account' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('payment_methods', 'account'),
            ],
            'currency_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::exists('currencies', 'code')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'bank_name' => ['required', 'string', 'max:255'],
            'opened_on' => ['nullable', 'date'],
            'closed_on' => ['nullable', 'date', 'after_or_equal:opened_on'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_intermediary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function isDuplicateCode(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}

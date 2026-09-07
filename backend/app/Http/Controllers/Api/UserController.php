<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($scope) use ($search) {
                $scope->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $sortField = $request->get('sort_field', 'id');
        $sortDirection = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $validSortFields = [
            'id', 'name', 'username', 'email', 'employee_code', 'job_title',
            'department', 'birth_date', 'phone', 'address', 'created_at',
        ];
        $query->orderBy(in_array($sortField, $validSortFields, true) ? $sortField : 'id', $sortDirection);

        $users = $query->paginate(min(200, max(1, (int) $request->get('per_page', 100))));

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $system = config('database_domains.system_connection');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['nullable', 'string', 'max:255', Rule::unique($system.'.users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($system.'.users', 'email')],
            'password' => 'nullable|string|min:6',
            'department_code' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'job_title_code' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'is_active_user' => 'nullable|boolean',
        ], $this->validationMessages());

        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', config('database_domains.employee_code_prefix')) ?: 'NV');
        $user = DB::connection($system)->transaction(function () use ($validated, $prefix) {
            $lastCode = User::query()
                ->where('employee_code', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(employee_code, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
                ->value('employee_code');
            $nextNumber = $lastCode && preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $lastCode, $matches)
                ? ((int) $matches[1]) + 1
                : 1;

            return User::create([
                ...$validated,
                'employee_code' => $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
                'username' => $validated['username'] ?: $validated['email'],
                'password' => Hash::make($validated['password'] ?: $validated['email']),
                'must_change_password' => true,
                'is_active_user' => $validated['is_active_user'] ?? true,
            ]);
        });

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function show($id)
    {
        $user = User::find($id);
        return $user
            ? response()->json(['success' => true, 'data' => $user])
            : response()->json(['message' => 'Không tìm thấy nhân viên.'], 404);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy nhân viên.'], 404);
        }

        $system = config('database_domains.system_connection');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['nullable', 'string', 'max:255', Rule::unique($system.'.users', 'username')->ignore($id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($system.'.users', 'email')->ignore($id)],
            'password' => 'nullable|string|min:6',
            'department_code' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'job_title_code' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'is_active_user' => 'nullable|boolean',
        ], $this->validationMessages());

        $validated['username'] = $validated['username'] ?: $validated['email'];
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            $validated['must_change_password'] = true;
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        if (array_key_exists('is_active_user', $validated) && !$validated['is_active_user']) {
            $user->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($user->email),
            'must_change_password' => true,
        ]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã đặt lại mật khẩu về email ('.$user->email.') và yêu cầu đổi ở lần đăng nhập tiếp theo.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy nhân viên.'], 404);
        }
        if ($request->user() && (int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không thể tự ngừng sử dụng tài khoản của chính mình.',
            ], 400);
        }

        // Giữ nguyên user để bảo toàn lịch sử giao dịch, chỉ khóa quyền truy cập.
        $user->update(['is_active_user' => false]);
        $user->tokens()->delete();

        return response()->json(['success' => true, 'message' => 'Đã ngừng sử dụng tài khoản nhân viên.']);
    }

    public function uploadSignature(Request $request, $id)
    {
        $request->validate([
            'signature' => 'required|image|max:10240',
        ], [
            'signature.required' => 'Vui lòng chọn ảnh chữ ký.',
            'signature.image' => 'File tải lên phải là hình ảnh.',
            'signature.max' => 'Dung lượng chữ ký không được vượt quá 10MB.',
            'signature.uploaded' => 'Tải chữ ký lên thất bại. Vui lòng kiểm tra dung lượng hoặc cấu hình PHP.',
        ]);

        $user = User::findOrFail($id);
        $oldSignature = $user->getRawOriginal('signature_url');
        if ($oldSignature && file_exists(public_path($oldSignature))) {
            @unlink(public_path($oldSignature));
        }

        $file = $request->file('signature');
        $filename = 'signature_'.$user->id.'_'.time().'_'.$file->getClientOriginalName();
        $directory = public_path('uploads/signatures');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $file->move($directory, $filename);

        $user->signature_url = 'uploads/signatures/'.$filename;
        $user->save();

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function deleteSignature($id)
    {
        $user = User::findOrFail($id);
        $signature = $user->getRawOriginal('signature_url');
        if ($signature && file_exists(public_path($signature))) {
            @unlink(public_path($signature));
        }
        $user->update(['signature_url' => null]);

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    private function validationMessages(): array
    {
        return [
            'name.required' => 'Họ tên nhân viên không được để trống.',
            'username.required' => 'Tên đăng nhập không được để trống.',
            'username.unique' => 'Tên đăng nhập đã được sử dụng.',
            'email.required' => 'Email không được để trống.',
            'email.unique' => 'Email đã được sử dụng.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 6 ký tự.',
        ];
    }
}

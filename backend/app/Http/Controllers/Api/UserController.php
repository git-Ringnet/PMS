<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Search name, email, employee_code
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort_field', 'id');
        $sortDir = $request->get('sort_dir', 'desc');
        
        $validSortFields = ['id', 'name', 'email', 'employee_code', 'job_title', 'department', 'birth_date', 'phone', 'address', 'created_at'];
        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = $request->get('per_page', 100);
        $users = $query->paginate($perPage);

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

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_code' => 'nullable|string|max:100|unique:users,employee_code',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'department_code' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:255',
            'job_title_code' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'is_active_user' => 'nullable|boolean',
        ], [
            'name.required' => 'Họ tên nhân viên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.unique' => 'Email đã được sử dụng bởi nhân viên khác.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 6 ký tự.',
            'employee_code.unique' => 'Mã nhân viên đã tồn tại.',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Employee not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $validated = $request->validate([
            'employee_code' => 'nullable|string|max:100|unique:users,employee_code,' . $id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
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
        ], [
            'name.required' => 'Họ tên nhân viên không được để trống.',
            'email.required' => 'Email không được để trống.',
            'email.unique' => 'Email đã được sử dụng bởi nhân viên khác.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 6 ký tự.',
            'employee_code.unique' => 'Mã nhân viên đã tồn tại.',
        ]);

        if (isset($validated['password']) && !empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified employee.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // Prevent self deletion
        if ($request->user() && $request->user()->id == $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không thể tự xóa tài khoản của chính mình!',
            ], 400);
        }

        // Remove signature file if exists
        if ($user->signature_url && file_exists(public_path($user->signature_url))) {
            @unlink(public_path($user->signature_url));
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    /**
     * Upload employee signature.
     */
    public function uploadSignature(Request $request, $id)
    {
        $request->validate([
            'signature' => 'required|image|max:10240',
        ], [
            'signature.required' => 'Vui lòng chọn ảnh chữ ký.',
            'signature.image' => 'File tải lên phải là hình ảnh.',
            'signature.max' => 'Dung lượng chữ ký không được vượt quá 10MB.',
            'signature.uploaded' => 'Tải chữ ký lên thất bại. Vui lòng kiểm tra lại dung lượng file (tối đa 10MB) hoặc cấu hình máy chủ PHP.',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if ($request->hasFile('signature')) {
            // Remove old file
            if ($user->signature_url && file_exists(public_path($user->signature_url))) {
                @unlink(public_path($user->signature_url));
            }

            $file = $request->file('signature');
            $filename = 'signature_' . $user->id . '_' . time() . '_' . $file->getClientOriginalName();
            
            // Ensure directory exists
            $dirPath = public_path('uploads/signatures');
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            $file->move($dirPath, $filename);
            
            $user->signature_url = 'uploads/signatures/' . $filename;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Delete employee signature.
     */
    public function deleteSignature($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        if ($user->signature_url && file_exists(public_path($user->signature_url))) {
            @unlink(public_path($user->signature_url));
        }
        $user->signature_url = null;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InfoBusinessResource;
use App\Models\InfoBusiness;
use Illuminate\Http\Request;

class InfoBusinessController extends Controller
{
    /**
     * Display the business information.
     */
    public function show()
    {
        $info = InfoBusiness::first();
        if (!$info) {
            $info = InfoBusiness::create([
                'company_name' => 'HKT Solutions 111',
                'bank_name' => 'HKT Solutions',
                'phone' => '0868 552 526',
                'address' => 'Lô 50 đường, 19 Tháng 5, Vĩnh Hiệp, Nha Trang, Khánh Hòa',
            ]);
        }
        return new InfoBusinessResource($info);
    }

    /**
     * Update the business information.
     */
    public function update(Request $request)
    {
        $info = InfoBusiness::first();
        if (!$info) {
            $info = new InfoBusiness();
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'chairman' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'director' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'system_branch_id' => 'nullable|exists:system_branches,id',
            'chief_accountant' => 'nullable|string|max:255',
        ], [
            'company_name.required' => 'Tên công ty không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
        ]);

        $info->fill($validated);
        $info->save();

        return new InfoBusinessResource($info);
    }

    /**
     * Upload business logo.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|max:10240',
        ], [
            'logo.required' => 'Vui lòng chọn ảnh logo.',
            'logo.image' => 'File tải lên phải là hình ảnh.',
            'logo.max' => 'Dung lượng logo không được vượt quá 10MB.',
            'logo.uploaded' => 'Tải logo lên thất bại. Vui lòng kiểm tra lại dung lượng file (tối đa 10MB) hoặc cấu hình máy chủ PHP.',
        ]);

        $info = InfoBusiness::first();
        if (!$info) {
            $info = InfoBusiness::create(['company_name' => 'HKT Solutions 111']);
        }

        if ($request->hasFile('logo')) {
            // Remove old file
            if ($info->logo_url && file_exists(public_path($info->logo_url))) {
                @unlink(public_path($info->logo_url));
            }

            $file = $request->file('logo');
            $filename = 'logo_' . time() . '_' . $file->getClientOriginalName();
            
            // Ensure directory exists
            $dirPath = public_path('uploads/business');
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            $file->move($dirPath, $filename);
            
            $info->logo_url = 'uploads/business/' . $filename;
            $info->save();
        }

        return new InfoBusinessResource($info);
    }

    /**
     * Delete business logo.
     */
    public function deleteLogo()
    {
        $info = InfoBusiness::first();
        if ($info) {
            if ($info->logo_url && file_exists(public_path($info->logo_url))) {
                @unlink(public_path($info->logo_url));
            }
            $info->logo_url = null;
            $info->save();
        }

        return new InfoBusinessResource($info);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::where('show', 1)->get();
        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
}

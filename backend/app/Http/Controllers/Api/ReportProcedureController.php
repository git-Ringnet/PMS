<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportProcedureCatalogService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class ReportProcedureController extends Controller
{
    public function __construct(
        private readonly ReportProcedureCatalogService $catalog,
        private readonly ReportDataExecutorService $executor
    ) {}

    public function index(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100']);

        return response()->json([
            'success' => true,
            'data' => $this->catalog->list($request->string('search')->toString()),
        ]);
    }

    public function show(string $procedure)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->catalog->describe($procedure),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    public function sample(Request $request)
    {
        $validated = $request->validate([
            'procedure' => 'required|string|max:128',
            'parameters' => 'nullable|array',
            'max_rows' => 'nullable|integer|min:1|max:'.config('reporting.maximum_max_rows', 5000),
        ]);

        try {
            $metadata = $this->catalog->describe($validated['procedure']);
            $result = $this->executor->executeProcedure(
                $validated['procedure'],
                $metadata['parameters'],
                $validated['parameters'] ?? [],
                $validated['max_rows'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'procedure' => $metadata,
                    ...$result,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chạy thử Stored Procedure. Vui lòng kiểm tra tham số và nhật ký máy chủ.',
            ], 422);
        }
    }
}

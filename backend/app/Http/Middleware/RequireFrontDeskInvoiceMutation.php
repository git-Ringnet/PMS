<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Require actual front-desk access for writes to the shared checkout invoice APIs. */
class RequireFrontDeskInvoiceMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin() || $user->hasPermission('fo.frontdesk.view', $request->attributes->get('_branch_id'))) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Front-desk invoice mutation is not allowed.',
            'required_permissions' => ['fo.frontdesk.view'],
        ], 403);
    }
}

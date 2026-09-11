<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /** Résultats de la recherche globale de la barre du haut, regroupés par type. */
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $term = mb_substr((string) $request->query('q', ''), 0, 100);

        return response()->json([
            'groups' => $search->search($request->user(), $term),
        ]);
    }
}

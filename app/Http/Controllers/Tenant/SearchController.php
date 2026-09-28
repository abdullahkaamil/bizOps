<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Search\SearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * Full search results page.
     */
    public function index(Request $request, SearchService $search): Response
    {
        $query = $request->string('q')->toString();

        return Inertia::render('search/Index', [
            'query' => $query,
            'groups' => $query !== '' ? $search->search($request->user(), $query) : [],
        ]);
    }

    /**
     * JSON results for the header search palette.
     */
    public function results(Request $request, SearchService $search): JsonResponse
    {
        return response()->json([
            'groups' => $search->search($request->user(), $request->string('q')->toString()),
        ]);
    }
}

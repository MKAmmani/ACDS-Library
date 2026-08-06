<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpeningHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpeningHourController extends Controller
{
    public function index(): JsonResponse
    {
        $hours = OpeningHour::orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $hours]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'day_label'  => ['required', 'string', 'max:60'],
            'time_label' => ['required', 'string', 'max:60'],
            'is_closed'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? ((OpeningHour::max('sort_order') ?? 0) + 1);

        return response()->json(OpeningHour::create($data), 201);
    }

    public function update(Request $request, OpeningHour $openingHour): JsonResponse
    {
        $data = $request->validate([
            'day_label'  => ['sometimes', 'string', 'max:60'],
            'time_label' => ['sometimes', 'string', 'max:60'],
            'is_closed'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $openingHour->update($data);

        return response()->json($openingHour->fresh());
    }

    public function destroy(OpeningHour $openingHour): JsonResponse
    {
        $openingHour->delete();

        return response()->json(['message' => 'Opening hours entry deleted successfully.']);
    }
}

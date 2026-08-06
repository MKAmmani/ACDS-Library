<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);

        $query = Event::query();

        // Public callers see only what's ahead; the admin manager passes
        // upcoming=0 to see the full history for editing.
        if ($request->boolean('upcoming', true)) {
            $query->where('starts_at', '>=', now()->startOfDay());
        }

        $events = $query->orderBy('starts_at')->limit($limit)->get();

        return response()->json(['data' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'starts_at'  => ['required', 'date'],
            'time_label' => ['nullable', 'string', 'max:40'],
            'place'      => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Event::create($data), 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'title'      => ['sometimes', 'string', 'max:255'],
            'starts_at'  => ['sometimes', 'date'],
            'time_label' => ['nullable', 'string', 'max:40'],
            'place'      => ['nullable', 'string', 'max:255'],
        ]);

        $event->update($data);

        return response()->json($event->fresh());
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}

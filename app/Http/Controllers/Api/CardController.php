<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Column;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    /**
     * Store a newly created card.
     */
    public function store(Request $request, Column $column)
    {
        try {
            // Check if column's board belongs to user
            if ($column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|string',
                'due_date' => 'nullable|date',
                'completed' => 'nullable|boolean',
                'position' => 'nullable|integer',
                'labels' => 'nullable|array',
                'labels.*' => 'string',
            ]);

            $position = $request->position ?? $column->cards()->max('position') + 1;

            $card = $column->cards()->create([
                'title' => $request->title,
                'description' => $request->description,
                'image' => $request->image,
                'due_date' => $request->due_date,
                'completed' => $request->completed ?? false,
                'position' => $position,
            ]);

            // Add labels if provided
            if ($request->has('labels')) {
                foreach ($request->labels as $label) {
                    $card->labels()->create(['label' => $label]);
                }
            }

            $card->load('labels');

            return response()->json([
                'success' => true,
                'message' => 'Card created successfully',
                'data' => [
                    'card' => $card
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified card.
     */
    public function show(Request $request, Card $card)
    {
        try {
            // Check if card's board belongs to user
            if ($card->column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $card->load('labels');

            return response()->json([
                'success' => true,
                'data' => [
                    'card' => $card
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified card.
     */
    public function update(Request $request, Card $card)
    {
        try {
            // Check if card's board belongs to user
            if ($card->column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|string',
                'due_date' => 'nullable|date',
                'completed' => 'nullable|boolean',
                'position' => 'nullable|integer',
                'labels' => 'nullable|array',
                'labels.*' => 'string',
            ]);

            $card->update($request->only([
                'title', 'description', 'image', 'due_date', 'completed', 'position'
            ]));

            // Update labels if provided
            if ($request->has('labels')) {
                $card->labels()->delete();
                foreach ($request->labels as $label) {
                    $card->labels()->create(['label' => $label]);
                }
            }

            $card->load('labels');

            return response()->json([
                'success' => true,
                'message' => 'Card updated successfully',
                'data' => [
                    'card' => $card
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified card.
     */
    public function destroy(Request $request, Card $card)
    {
        try {
            // Check if card's board belongs to user
            if ($card->column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $card->delete();

            return response()->json([
                'success' => true,
                'message' => 'Card deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move card to another column.
     */
    public function move(Request $request, Card $card)
    {
        try {
            // Check if card's board belongs to user
            if ($card->column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'column_id' => 'required|exists:columns,id',
                'position' => 'required|integer',
            ]);

            $newColumn = Column::findOrFail($request->column_id);

            // Check if new column's board belongs to user
            if ($newColumn->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $card->update([
                'column_id' => $request->column_id,
                'position' => $request->position,
            ]);

            $card->load('labels');

            return response()->json([
                'success' => true,
                'message' => 'Card moved successfully',
                'data' => [
                    'card' => $card
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle card completion status.
     */
    public function toggleComplete(Request $request, Card $card)
    {
        try {
            // Check if card's board belongs to user
            if ($card->column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $card->update([
                'completed' => !$card->completed
            ]);

            $card->load('labels');

            return response()->json([
                'success' => true,
                'message' => 'Card completion toggled successfully',
                'data' => [
                    'card' => $card
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle card completion: ' . $e->getMessage()
            ], 500);
        }
    }
}

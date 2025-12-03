<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    /**
     * Store a newly created column.
     */
    public function store(Request $request, Board $board)
    {
        try {
            // Check if board belongs to user
            if ($board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $request->validate([
                'title' => 'required|string|max:255',
                'color' => 'nullable|string',
                'position' => 'nullable|integer',
            ]);

            $position = $request->position ?? $board->columns()->max('position') + 1;

            $column = $board->columns()->create([
                'title' => $request->title,
                'color' => $request->color ?? '#E8EAF6',
                'position' => $position,
            ]);

            $column->load('cards.labels');

            return response()->json([
                'success' => true,
                'message' => 'Column created successfully',
                'data' => [
                    'column' => $column
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create column: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified column.
     */
    public function update(Request $request, Column $column)
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
                'title' => 'sometimes|required|string|max:255',
                'color' => 'nullable|string',
                'position' => 'nullable|integer',
            ]);

            $column->update($request->only(['title', 'color', 'position']));
            $column->load('cards.labels');

            return response()->json([
                'success' => true,
                'message' => 'Column updated successfully',
                'data' => [
                    'column' => $column
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update column: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified column.
     */
    public function destroy(Request $request, Column $column)
    {
        try {
            // Check if column's board belongs to user
            if ($column->board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $column->delete();

            return response()->json([
                'success' => true,
                'message' => 'Column deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete column: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder columns.
     */
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'columns' => 'required|array',
                'columns.*.id' => 'required|exists:columns,id',
                'columns.*.position' => 'required|integer',
            ]);

            foreach ($request->columns as $columnData) {
                $column = Column::find($columnData['id']);

                // Check if column's board belongs to user
                if ($column->board->user_id !== $request->user()->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }

                $column->update(['position' => $columnData['position']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Columns reordered successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder columns: ' . $e->getMessage()
            ], 500);
        }
    }
}

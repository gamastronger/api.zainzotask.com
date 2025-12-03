<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * Display a listing of boards for authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $boards = $request->user()->boards()->with('columns.cards.labels')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'boards' => $boards
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch boards: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created board.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $board = $request->user()->boards()->create([
                'title' => $request->title,
                'description' => $request->description,
            ]);

            // Create default columns
            $columns = [
                ['title' => 'Todo', 'color' => '#E8EAF6', 'position' => 0],
                ['title' => 'In Progress', 'color' => '#E3F2FD', 'position' => 1],
                ['title' => 'Done', 'color' => '#E0F2F1', 'position' => 2],
            ];

            foreach ($columns as $column) {
                $board->columns()->create($column);
            }

            $board->load('columns.cards.labels');

            return response()->json([
                'success' => true,
                'message' => 'Board created successfully',
                'data' => [
                    'board' => $board
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create board: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified board.
     */
    public function show(Request $request, Board $board)
    {
        try {
            // Check if board belongs to user
            if ($board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $board->load('columns.cards.labels');

            return response()->json([
                'success' => true,
                'data' => [
                    'board' => $board
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch board: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified board.
     */
    public function update(Request $request, Board $board)
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
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $board->update($request->only(['title', 'description']));
            $board->load('columns.cards.labels');

            return response()->json([
                'success' => true,
                'message' => 'Board updated successfully',
                'data' => [
                    'board' => $board
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update board: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified board.
     */
    public function destroy(Request $request, Board $board)
    {
        try {
            // Check if board belongs to user
            if ($board->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $board->delete();

            return response()->json([
                'success' => true,
                'message' => 'Board deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete board: ' . $e->getMessage()
            ], 500);
        }
    }
}

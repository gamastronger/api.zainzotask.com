<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Board;
use App\Models\Column;
use App\Models\Card;
use App\Models\CardLabel;
use Illuminate\Database\Seeder;

class KanbanSeeder extends Seeder
{
    /**
     * Seed kanban data with boards, columns, cards, and labels.
     */
    public function run(): void
    {
        // Ambil semua users yang ada
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->error('No users found! Please create users first.');
            return;
        }

        foreach ($users as $user) {
            // Ambil boards milik user ini
            $boards = Board::where('user_id', $user->id)->get();

            if ($boards->isEmpty()) {
                // Jika user tidak punya board, buat 1 board
                $board = Board::create([
                    'user_id' => $user->id,
                    'title' => 'My Kanban Board',
                    'description' => 'Sample kanban board for ' . $user->name,
                ]);
                $boards = collect([$board]);
            }

            foreach ($boards as $board) {
                // Ambil columns di board ini
                $columns = Column::where('board_id', $board->id)->get();

                if ($columns->isEmpty()) {
                    // Jika board tidak punya columns, buat default columns
                    $columns = collect([
                        Column::create([
                            'board_id' => $board->id,
                            'title' => 'To Do',
                            'color' => '#FF6B6B',
                            'position' => 0,
                        ]),
                        Column::create([
                            'board_id' => $board->id,
                            'title' => 'In Progress',
                            'color' => '#4ECDC4',
                            'position' => 1,
                        ]),
                        Column::create([
                            'board_id' => $board->id,
                            'title' => 'Done',
                            'color' => '#95E1D3',
                            'position' => 2,
                        ]),
                    ]);
                }

                // Buat sample cards untuk setiap column
                foreach ($columns as $index => $column) {
                    $cardsData = $this->getCardSamples($column->title, $index);

                    foreach ($cardsData as $position => $cardData) {
                        $card = Card::create([
                            'column_id' => $column->id,
                            'title' => $cardData['title'],
                            'description' => $cardData['description'],
                            'due_date' => $cardData['due_date'] ?? null,
                            'completed' => $cardData['completed'] ?? false,
                            'position' => $position,
                        ]);

                        // Tambahkan labels jika ada
                        if (isset($cardData['labels'])) {
                            foreach ($cardData['labels'] as $label) {
                                CardLabel::create([
                                    'card_id' => $card->id,
                                    'label' => $label,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $this->command->info('Kanban data seeded successfully!');
    }

    /**
     * Get sample cards based on column title
     */
    private function getCardSamples(string $columnTitle, int $columnIndex): array
    {
        $samples = [
            'To Do' => [
                [
                    'title' => 'Setup Project Environment',
                    'description' => 'Install dependencies and configure development environment',
                    'due_date' => now()->addDays(3),
                    'labels' => ['Setup', 'High Priority'],
                ],
                [
                    'title' => 'Design Database Schema',
                    'description' => 'Create ERD and define relationships between tables',
                    'due_date' => now()->addDays(5),
                    'labels' => ['Database', 'Planning'],
                ],
                [
                    'title' => 'Create API Documentation',
                    'description' => 'Document all API endpoints with examples',
                    'due_date' => now()->addDays(7),
                    'labels' => ['Documentation'],
                ],
            ],
            'In Progress' => [
                [
                    'title' => 'Implement User Authentication',
                    'description' => 'Setup Google OAuth login and session management',
                    'due_date' => now()->addDays(2),
                    'labels' => ['Backend', 'Auth'],
                ],
                [
                    'title' => 'Build Board Management',
                    'description' => 'Create, read, update, delete operations for boards',
                    'labels' => ['Backend', 'Feature'],
                ],
            ],
            'Done' => [
                [
                    'title' => 'Initial Project Setup',
                    'description' => 'Laravel project initialized with required dependencies',
                    'completed' => true,
                    'labels' => ['Setup', 'Completed'],
                ],
                [
                    'title' => 'Database Migration',
                    'description' => 'All database tables created and relationships established',
                    'completed' => true,
                    'labels' => ['Database', 'Completed'],
                ],
            ],
        ];

        // Default cards jika column title tidak cocok
        $defaultCards = [
            [
                'title' => 'Task in ' . $columnTitle,
                'description' => 'Sample task for testing purposes',
                'labels' => ['Sample'],
            ],
            [
                'title' => 'Another Task',
                'description' => 'Another sample task in ' . $columnTitle,
                'labels' => ['Sample', 'Test'],
            ],
        ];

        return $samples[$columnTitle] ?? $defaultCards;
    }
}

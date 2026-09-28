<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Default columns per board type. Names are user-editable data (Turkish, the
     * app default locale); the category is the workflow bucket that drives a
     * task's derived status.
     *
     * @var array<string, array<int, array{name: string, category: string}>>
     */
    private array $defaults = [
        'internal' => [
            ['name' => 'Yapılacak', 'category' => 'todo'],
            ['name' => 'Devam Ediyor', 'category' => 'in_progress'],
            ['name' => 'Tamamlandı', 'category' => 'completed'],
        ],
        'project' => [
            ['name' => 'Yapılacak', 'category' => 'todo'],
            ['name' => 'Devam Ediyor', 'category' => 'in_progress'],
            ['name' => 'İncelemede', 'category' => 'review'],
            ['name' => 'Tamamlandı', 'category' => 'completed'],
        ],
    ];

    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('board_column_id')->nullable()->after('board_id')
                ->constrained('board_columns')->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('status');
        });

        // Backfill: seed default columns for every existing board, then place each
        // task in the column whose category matches its current status.
        foreach (DB::table('boards')->get(['id', 'type']) as $board) {
            $defaults = $this->defaults[$board->type] ?? $this->defaults['internal'];
            $categoryToColumn = [];

            foreach ($defaults as $position => $column) {
                $id = DB::table('board_columns')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'board_id' => $board->id,
                    'name' => $column['name'],
                    'category' => $column['category'],
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $categoryToColumn[$column['category']] = $id;
            }

            $fallback = $categoryToColumn['in_progress'] ?? reset($categoryToColumn);

            $tasks = DB::table('tasks')->where('board_id', $board->id)
                ->orderBy('id')->get(['id', 'status']);

            foreach ($tasks as $index => $task) {
                DB::table('tasks')->where('id', $task->id)->update([
                    'board_column_id' => $categoryToColumn[$task->status] ?? $fallback,
                    'position' => $index,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_column_id');
            $table->dropColumn('position');
        });
    }
};

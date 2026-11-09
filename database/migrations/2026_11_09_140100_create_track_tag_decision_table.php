<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B8: решения ручной разметки (accepted/ignored) отдельной таблицей.
 *
 * Раньше ручное редактирование переписывало track_tag.source на 'manual' и
 * стирало исходный источник связи (file/openrouter). Теперь источник остаётся
 * неприкосновенным, а решение человека хранится здесь:
 *   accepted — пользователь подтвердил метку (в т.ч. запрет повторного ignored);
 *   ignored  — пользователь снял метку и сканер не должен возвращать её обратно.
 *
 * Миграция данных аддитивная: существующие связи track_tag не меняются, а
 * прежние ручные правки (source = 'manual') фиксируются как accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('track_tag_decision', function (Blueprint $table) {
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('decision', 16);
            $table->timestamps();
            $table->primary(['track_id', 'tag_id']);
        });

        $now = now();

        DB::table('track_tag')
            ->select('track_id', 'tag_id')
            ->where('source', 'manual')
            ->orderBy('track_id')
            ->chunk(500, function ($rows) use ($now) {
                $decisions = [];
                foreach ($rows as $row) {
                    $decisions[] = [
                        'track_id' => $row->track_id,
                        'tag_id' => $row->tag_id,
                        'decision' => 'accepted',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('track_tag_decision')->insertOrIgnore($decisions);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_tag_decision');
    }
};

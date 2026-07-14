<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_DEFAULT_SHA256 = '7f8bb209fcc52e897ad79af3cb46ca0cc8ad42e10fa857632f089ef8e652b9b5';

    private const TEMPLATE_NAMES = ['clash', 'clashmeta', 'stash'];

    public function up(): void
    {
        if (!Schema::hasTable('v2_subscribe_templates')) {
            return;
        }

        $templatePath = resource_path('rules/default.clash.yaml');
        if (!File::isFile($templatePath)) {
            return;
        }

        $newContent = File::get($templatePath);
        $updatedNames = DB::transaction(function () use ($newContent): array {
            $updated = [];
            $templates = DB::table('v2_subscribe_templates')
                ->whereIn('name', self::TEMPLATE_NAMES)
                ->lockForUpdate()
                ->get(['id', 'name', 'content']);

            foreach ($templates as $template) {
                $content = $template->content;
                if (!is_string($content) || !hash_equals(self::LEGACY_DEFAULT_SHA256, hash('sha256', $content))) {
                    continue;
                }

                DB::table('v2_subscribe_templates')
                    ->where('id', $template->id)
                    ->update([
                        'content' => $newContent,
                        'updated_at' => now(),
                    ]);
                $updated[] = $template->name;
            }

            return $updated;
        });

        foreach ($updatedNames as $name) {
            Cache::store()->forget("subscribe_template:v2:{$name}");
        }
    }

    public function down(): void
    {
        // Data migration: never restore an obsolete default over later edits.
    }
};

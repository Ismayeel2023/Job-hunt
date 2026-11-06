<?php

namespace Database\Seeders;

use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public const BASE_PLACEHOLDER = <<<'TEX'
        % PLACEHOLDER: replace with the real base resume LaTeX.
        \documentclass{article}
        \begin{document}
        Base resume placeholder.
        \end{document}
        TEX;

    /**
     * Seed the application's database.
     *
     * Safe to re-run: existing rows are left alone, so the real resume and a
     * changed password are never overwritten.
     */
    public function run(): void
    {
        $password = env('JOBHUNT_USER_PASSWORD');

        if (! $password) {
            throw new RuntimeException('Set JOBHUNT_USER_PASSWORD in .env before seeding.');
        }

        User::firstOrCreate(
            ['email' => env('JOBHUNT_USER_EMAIL', 'me@jobhunt.test')],
            ['name' => env('JOBHUNT_USER_NAME', 'Me'), 'password' => $password],
        );

        ResumeVersion::firstOrCreate(
            ['is_base' => true],
            ['label' => 'Base resume', 'tex_source' => self::BASE_PLACEHOLDER],
        );
    }
}

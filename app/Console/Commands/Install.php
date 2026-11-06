<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * First-time setup, safe to run again: it only fills in what is missing and never replaces
 * an existing app key (that would make saved Gmail tokens unreadable).
 */
class Install extends Command
{
    protected $signature = 'jobhunt:install';

    protected $description = 'Set up JobHunt: .env secrets, database, login and the skill template';

    public function handle(): int
    {
        $envFile = base_path('.env');

        if (! file_exists($envFile)) {
            copy(base_path('.env.example'), $envFile);
            $this->info('Created .env from .env.example.');
        }

        if ($this->envValue('APP_KEY') === '') {
            $this->call('key:generate', ['--force' => true]);
        }

        if ($this->envValue('AGENT_TOKEN') === '') {
            $this->setEnv('AGENT_TOKEN', bin2hex(random_bytes(32)));
            $this->info('Generated AGENT_TOKEN.');
        }

        $newPassword = null;

        if ($this->envValue('JOBHUNT_USER_PASSWORD') === '') {
            $newPassword = Str::password(20, symbols: false);
            $this->setEnv('JOBHUNT_USER_PASSWORD', $newPassword);
        }

        $database = database_path('database.sqlite');

        if (! file_exists($database)) {
            touch($database);
            $this->info('Created database/database.sqlite.');
        }

        // Child processes inherit this process's environment, which still holds the values from
        // before they were written above, so pass the fresh ones explicitly.
        $fresh = collect(['APP_KEY', 'AGENT_TOKEN', 'JOBHUNT_USER_PASSWORD'])
            ->mapWithKeys(fn (string $key) => [$key => $this->envValue($key)])
            ->all();

        foreach ([['migrate', '--force'], ['db:seed', '--force']] as $arguments) {
            $result = Process::path(base_path())->env($fresh)->run([PHP_BINARY, 'artisan', ...$arguments]);
            $this->output->write($result->output());

            if ($result->failed()) {
                $this->error($result->errorOutput());

                return self::FAILURE;
            }
        }

        $skill = base_path('.claude/skills/job-hunt-assistant/SKILL.md');

        if (! file_exists($skill)) {
            @mkdir(dirname($skill), 0777, true);
            copy(base_path('stubs/job-hunt-assistant.SKILL.md'), $skill);
            $this->info('Created your skill at .claude/skills/job-hunt-assistant/SKILL.md from the template.');
        }

        $this->newLine();
        $this->info('JobHunt is set up.');
        $this->line('  Login: '.$this->envValue('JOBHUNT_USER_EMAIL'));
        $this->line($newPassword
            ? "  Password: {$newPassword}  (also saved as JOBHUNT_USER_PASSWORD in .env)"
            : '  Password: the JOBHUNT_USER_PASSWORD value in .env');
        $this->newLine();
        $this->line('Next: fill in your profile in .claude/skills/job-hunt-assistant/SKILL.md (see README), then run: composer run dev');

        return self::SUCCESS;
    }

    private function envValue(string $key): string
    {
        preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents(base_path('.env')), $matches);

        return trim($matches[1] ?? '', " \t\"'");
    }

    private function setEnv(string $key, string $value): void
    {
        $file = base_path('.env');
        $contents = (string) file_get_contents($file);
        $line = "{$key}={$value}";

        $contents = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)
            ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents)
            : rtrim($contents, "\n")."\n{$line}\n";

        file_put_contents($file, $contents);
    }
}

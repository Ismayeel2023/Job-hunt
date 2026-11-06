<?php

namespace App\Services\Gmail;

use App\Models\Email;
use App\Models\GmailAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MailSync
{
    public function __construct(private GoogleOAuth $oauth)
    {
    }

    /**
     * Syncs every connected account, or only the one given. Returns null when another sync holds the lock.
     *
     * @return list<array{account: string, listed: int, stored: int, linked: int, skipped: int, error: ?string}>|null
     */
    public function run(?GmailAccount $only = null): ?array
    {
        $lock = Cache::lock('jobhunt:sync-mail', 900);

        if (! $lock->get()) {
            return null;
        }

        try {
            $filter = MailFilter::current();
            $matcher = new ApplicationMatcher;
            $accounts = $only ? collect([$only]) : GmailAccount::where('needs_reconnect', false)->orderBy('email')->get();

            $results = $accounts->map(fn (GmailAccount $account) => $this->syncAccount($account, $filter, $matcher))->all();

            $this->relinkUnmatched($matcher);

            return $results;
        } finally {
            $lock->release();
        }
    }

    private function syncAccount(GmailAccount $account, MailFilter $filter, ApplicationMatcher $matcher): array
    {
        $result = ['account' => $account->email, 'listed' => 0, 'stored' => 0, 'linked' => 0, 'skipped' => 0, 'error' => null];
        $limit = config('jobhunt.mail.max_messages_per_sync');

        try {
            $gmail = new GmailClient($this->oauth->accessToken($account));

            $ids = collect($filter->queries())
                ->flatMap(fn (string $query) => $gmail->messageIds($query, $limit))
                ->unique()
                ->take($limit);

            $result['listed'] = $ids->count();
            $known = Email::whereIn('gmail_message_id', $ids)->pluck('gmail_message_id')->all();

            foreach ($ids->diff($known) as $id) {
                $message = $gmail->metadata($id);
                $headers = collect($message['payload']['headers'] ?? [])->mapWithKeys(fn (array $header) => [strtolower($header['name']) => $header['value']]);
                $from = (string) $headers->get('from', '');
                $subject = (string) $headers->get('subject', '');
                [$address, $name] = MailFilter::parseSender($from);

                if (! $filter->matches($address, $subject)) {
                    $result['skipped']++;

                    continue;
                }

                $snippet = html_entity_decode($message['snippet'] ?? '', ENT_QUOTES | ENT_HTML5);

                $email = Email::firstOrCreate(['gmail_message_id' => $message['id']], [
                    'gmail_account' => $account->email,
                    'thread_id' => $message['threadId'] ?? null,
                    'from' => mb_substr($from, 0, 255),
                    'subject' => mb_substr($subject, 0, 255),
                    'snippet' => $snippet,
                    'received_at' => Carbon::createFromTimestampMs((int) $message['internalDate']),
                    'matched_application_id' => $matcher->match($address, $name, $subject, $snippet),
                    'classification' => null,
                    'processed' => false,
                ]);

                if ($email->wasRecentlyCreated) {
                    $result['stored']++;
                    $result['linked'] += $email->matched_application_id ? 1 : 0;
                }
            }

            $account->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (ReconnectRequired $exception) {
            $account->update(['needs_reconnect' => true, 'last_error' => $exception->getMessage()]);
            $result['error'] = $exception->getMessage();
        } catch (GmailException|ConnectionException $exception) {
            $account->update(['last_error' => $exception->getMessage()]);
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Applications added after an email arrived can still be linked, until Claude has processed it.
     */
    private function relinkUnmatched(ApplicationMatcher $matcher): void
    {
        Email::whereNull('matched_application_id')->where('processed', false)->each(function (Email $email) use ($matcher) {
            [$address, $name] = MailFilter::parseSender((string) $email->from);
            $applicationId = $matcher->match($address, $name, (string) $email->subject, (string) $email->snippet);

            if ($applicationId) {
                $email->update(['matched_application_id' => $applicationId]);
            }
        });
    }
}

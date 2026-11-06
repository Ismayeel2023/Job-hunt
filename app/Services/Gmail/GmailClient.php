<?php

namespace App\Services\Gmail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GmailClient
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public function __construct(private string $accessToken)
    {
    }

    public function profileEmail(): string
    {
        return $this->get('/profile')->json('emailAddress');
    }

    /**
     * Ids of messages matching a Gmail search query, newest first.
     *
     * @return list<string>
     */
    public function messageIds(string $query, int $limit): array
    {
        $ids = [];
        $pageToken = null;

        do {
            $response = $this->get('/messages', array_filter([
                'q' => $query,
                'maxResults' => min(100, $limit - count($ids)),
                'pageToken' => $pageToken,
            ]));

            foreach ($response->json('messages', []) as $message) {
                $ids[] = $message['id'];
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken && count($ids) < $limit);

        return $ids;
    }

    /**
     * Headers and snippet only. format=metadata never returns the body or attachments.
     */
    public function metadata(string $id): array
    {
        return $this->get('/messages/'.rawurlencode($id).'?format=metadata&metadataHeaders=From&metadataHeaders=Subject')->json();
    }

    private function get(string $path, array $query = []): Response
    {
        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 1000, fn ($exception) => in_array($exception->response?->status(), [429, 500, 502, 503], true), throw: false)
            // An empty query array would replace the query string already in the path, losing format=metadata.
            ->get(self::BASE_URL.$path, $query ?: null);

        if ($response->failed()) {
            throw new GmailException('Gmail API error: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        return $response;
    }
}

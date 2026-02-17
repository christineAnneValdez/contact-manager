<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SevDeskClient
{
    public function createContactPerson(string $firstName, string $lastName): string
    {
        $payload = [
            'category[id]' => '3',
            'category[objectName]' => 'Category',
            'surename' => $firstName,
            'familyname' => $lastName,
        ];

        $response = $this->post('/Contact', $payload);
        $contact = $this->firstObject($response);
        $id = (string) ($contact['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException('sevDesk did not return a contact id after create.');
        }

        return $id;
    }

    public function createCommunicationEmail(string $contactId, string $email, bool $main = true): void
    {
        $payload = [
            'contact[id]' => $contactId,
            'contact[objectName]' => 'Contact',
            'type' => 'EMAIL',
            'value' => $email,
            'key[id]' => '2',
            'key[objectName]' => 'CommunicationWayKey',
            'main' => $main ? '1' : '0',
        ];

        $this->post('/CommunicationWay', $payload);
    }

    public function listContacts(int $limit = 100, int $offset = 0): array
    {
        $response = $this->get('/Contact', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $objects = $response['objects'] ?? [];

        return is_array($objects) ? $objects : [];
    }

    public function getContactById(string $id): ?array
    {
        $response = $this->get('/Contact/'.$id);
        $objects = $response['objects'] ?? [];

        if (! is_array($objects) || empty($objects[0]) || ! is_array($objects[0])) {
            return null;
        }

        return $objects[0];
    }

    public function listContactAddresses(int $limit = 100, int $offset = 0): array
    {
        $response = $this->get('/ContactAddress', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $objects = $response['objects'] ?? [];

        return is_array($objects) ? $objects : [];
    }

    public function listCommunicationWays(int $limit = 100, int $offset = 0): array
    {
        $response = $this->get('/CommunicationWay', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $objects = $response['objects'] ?? [];

        return is_array($objects) ? $objects : [];
    }

    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->request()->get($this->normalizeEndpoint($endpoint), $query);

        return $this->decode($response);
    }

    public function post(string $endpoint, array $payload = []): array
    {
        $response = $this->request()
            ->asForm()
            ->post($this->normalizeEndpoint($endpoint), $payload);

        return $this->decode($response);
    }

    protected function request(): PendingRequest
    {
        $apiKey = (string) config('services.sevdesk.api_key');
        $baseUrl = rtrim((string) config('services.sevdesk.base_url'), '/');
        $authMode = (string) config('services.sevdesk.auth_mode');
        $timeout = (int) config('services.sevdesk.timeout', 20);

        if ($apiKey === '') {
            throw new RuntimeException('SEVDESK_API_KEY is missing.');
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout($timeout);

        if ($authMode === 'header') {
            return $request->withHeader('Authorization', $apiKey);
        }

        return $request->withQueryParameters(['token' => $apiKey]);
    }

    protected function decode(Response $response): array
    {
        try {
            $response->throw();
        } catch (RequestException $e) {
            $message = $response->body() ?: $e->getMessage();

            throw new RuntimeException('sevDesk request failed: '.$message, previous: $e);
        }

        return $response->json() ?? [];
    }

    protected function normalizeEndpoint(string $endpoint): string
    {
        return '/'.ltrim($endpoint, '/');
    }

    protected function firstObject(array $response): ?array
    {
        $objects = $response['objects'] ?? [];

        if (! is_array($objects) || empty($objects[0]) || ! is_array($objects[0])) {
            return null;
        }

        return $objects[0];
    }
}

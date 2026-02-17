<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\SevDeskClient;
use Illuminate\Console\Command;

class SyncSevDeskContacts extends Command
{
    protected $signature = 'sevdesk:sync-contacts
                            {--limit=100 : Contacts per request}
                            {--max-pages=50 : Maximum pages to fetch}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Sync contacts from sevDesk into local contacts table';

    public function handle(SevDeskClient $sevDesk): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $dryRun = (bool) $this->option('dry-run');
        $offset = 0;

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $processedIds = [];
        $emailByContactId = $this->loadEmailsByContactId($sevDesk, $limit, $maxPages);

        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $sevDesk->listContacts($limit, $offset);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $mapped = $this->mapContact((array) $row, $emailByContactId);

                if ($mapped === null) {
                    $skipped++;
                    continue;
                }

                $processedIds[] = $mapped['sevdesk_id'];

                [$wasCreated, $wasUpdated, $wasSkipped] = $this->upsertMappedContact($mapped, $dryRun);
                $created += (int) $wasCreated;
                $updated += (int) $wasUpdated;
                $skipped += (int) $wasSkipped;
            }

            $offset += $limit;
        }

        // Some sevDesk tenants return partial/empty /Contact listing.
        // Always fallback to ContactAddress-linked contacts to avoid missing records.
        $fallbackCounts = $this->syncFromAddressLinkedContacts(
            $sevDesk,
            $emailByContactId,
            $limit,
            $maxPages,
            $dryRun,
            $processedIds
        );

        $created += $fallbackCounts['created'];
        $updated += $fallbackCounts['updated'];
        $skipped += $fallbackCounts['skipped'];

        $this->info('sevDesk sync completed.');
        $this->line("Created: {$created}");
        $this->line("Updated: {$updated}");
        $this->line("Skipped: {$skipped}");
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'write'));

        return self::SUCCESS;
    }

    private function upsertMappedContact(array $mapped, bool $dryRun): array
    {
        $sevdeskId = (string) ($mapped['sevdesk_id'] ?? '');

        if ($sevdeskId !== '') {
            $bySevdeskId = Contact::query()->where('sevdesk_id', $sevdeskId)->first();
            if ($bySevdeskId) {
                if (! $dryRun) {
                    $bySevdeskId->fill($mapped)->save();
                }

                return [false, true, false];
            }

            if (! $dryRun) {
                Contact::create($mapped);
            }

            return [true, false, false];
        }

        $byEmail = Contact::query()->where('email', $mapped['email'])->first();
        if ($byEmail) {
            if (! $dryRun) {
                $byEmail->fill($mapped)->save();
            }

            return [false, true, false];
        }

        if (! $dryRun) {
            Contact::create($mapped);
        }

        return [true, false, false];
    }

    private function mapContact(array $row, array $emailByContactId = []): ?array
    {
        $sevdeskId = isset($row['id']) ? (string) $row['id'] : null;
        $name = $this->extractName($row);
        $email = $this->extractEmail($row, $emailByContactId);

        if (! $name || ! $email) {
            return null;
        }

        return [
            'sevdesk_id' => $sevdeskId,
            'name' => $name,
            'email' => $email,
        ];
    }

    private function syncFromAddressLinkedContacts(
        SevDeskClient $sevDesk,
        array $emailByContactId,
        int $limit,
        int $maxPages,
        bool $dryRun,
        array $alreadyProcessedIds = []
    ): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $offset = 0;
        $seen = array_fill_keys(array_filter($alreadyProcessedIds), true);

        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $sevDesk->listContactAddresses($limit, $offset);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $address) {
                $contactId = (string) ($address['contact']['id'] ?? '');
                if ($contactId === '' || isset($seen[$contactId])) {
                    continue;
                }
                $seen[$contactId] = true;

                $contactRow = $sevDesk->getContactById($contactId);
                if (! $contactRow) {
                    $skipped++;
                    continue;
                }

                $mapped = $this->mapContact($contactRow, $emailByContactId);
                if (! $mapped) {
                    $skipped++;
                    continue;
                }

                [$wasCreated, $wasUpdated, $wasSkipped] = $this->upsertMappedContact($mapped, $dryRun);
                $created += (int) $wasCreated;
                $updated += (int) $wasUpdated;
                $skipped += (int) $wasSkipped;
            }

            $offset += $limit;
        }

        return compact('created', 'updated', 'skipped');
    }

    private function extractName(array $row): ?string
    {
        $first = trim((string) ($row['givenName'] ?? $row['firstName'] ?? $row['surename'] ?? ''));
        $last = trim((string) ($row['familyname'] ?? $row['lastName'] ?? ''));
        $combined = trim($first.' '.$last);

        if ($combined !== '') {
            return $combined;
        }

        $direct = trim((string) ($row['name'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        return null;
    }

    private function extractEmail(array $row, array $emailByContactId = []): ?string
    {
        $candidates = [
            $row['email'] ?? null,
            $row['emailAddress'] ?? null,
            $row['mainEmail'] ?? null,
            $emailByContactId[(string) ($row['id'] ?? '')] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return strtolower($value);
            }
        }

        return null;
    }

    private function loadEmailsByContactId(SevDeskClient $sevDesk, int $limit, int $maxPages): array
    {
        $result = [];
        $offset = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $sevDesk->listCommunicationWays($limit, $offset);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $type = strtoupper((string) ($row['type'] ?? ''));
                $contactId = (string) ($row['contact']['id'] ?? '');
                $value = strtolower(trim((string) ($row['value'] ?? '')));

                if ($type !== 'EMAIL' || $contactId === '' || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if (! isset($result[$contactId])) {
                    $result[$contactId] = $value;
                }
            }

            $offset += $limit;
        }

        return $result;
    }
}

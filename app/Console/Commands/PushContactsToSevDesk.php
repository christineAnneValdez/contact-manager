<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\SevDeskClient;
use Illuminate\Console\Command;
use Throwable;

class PushContactsToSevDesk extends Command
{
    protected $signature = 'sevdesk:push-contacts
                            {--limit=100 : Maximum local contacts to push}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Push local contacts without sevdesk_id to sevDesk';

    public function handle(SevDeskClient $sevDesk): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $contacts = Contact::query()
            ->whereNull('sevdesk_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $created = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            if (! filter_var($contact->email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Skipped contact {$contact->id}: invalid email.");
                $skipped++;
                continue;
            }

            [$firstName, $lastName] = $this->splitName((string) $contact->name);

            if ($dryRun) {
                $created++;
                continue;
            }

            try {
                $sevdeskId = $sevDesk->createContactPerson($firstName, $lastName);
                $sevDesk->createCommunicationEmail($sevdeskId, strtolower($contact->email), true);

                $contact->sevdesk_id = $sevdeskId;
                $contact->save();
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("Failed contact {$contact->id}: ".$e->getMessage());
            }
        }

        $this->info('sevDesk push completed.');
        $this->line("Created: {$created}");
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'write'));

        return self::SUCCESS;
    }

    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['Unknown', 'Contact'];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], $parts[0]];
        }

        $first = array_shift($parts);
        $last = trim(implode(' ', $parts));

        return [$first ?: 'Unknown', $last ?: 'Contact'];
    }
}


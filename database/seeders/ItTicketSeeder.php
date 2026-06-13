<?php

namespace Database\Seeders;

use App\Models\ItTicket;
use App\Models\ItTicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItTicketSeeder extends Seeder
{
    /**
     * Seed a realistic spread of IT Board tickets across every column,
     * with varied types, priorities, categories, assignees and deadlines.
     */
    public function run(): void
    {
        $reporter = User::where('role', 'admin')->orderBy('id')->first();

        if (! $reporter) {
            $this->command?->warn('No admin user found — skipping IT ticket seeding.');

            return;
        }

        $categories = ItTicketCategory::pluck('id', 'name');

        // due: integer days from today (negative = overdue), or null for no deadline.
        $tickets = [
            // Backlog
            ['title' => 'Evaluate moving CDN to Cloudflare', 'type' => 'task', 'priority' => 'low', 'status' => 'backlog', 'category' => 'Infrastructure', 'assign' => false, 'due' => 20, 'desc' => 'Compare pricing and edge coverage against the current provider.'],
            ['title' => 'Spike: WebSocket vs SSE for live updates', 'type' => 'task', 'priority' => 'medium', 'status' => 'backlog', 'category' => 'Backend', 'assign' => true, 'due' => null, 'desc' => 'Prototype both approaches for the live dashboard and document trade-offs.'],
            ['title' => 'Refresh the login screen visuals', 'type' => 'improvement', 'priority' => 'low', 'status' => 'backlog', 'category' => 'Design', 'assign' => false, 'due' => 14, 'desc' => null],
            ['title' => 'Audit third-party JS bundle size', 'type' => 'improvement', 'priority' => 'medium', 'status' => 'backlog', 'category' => 'Frontend', 'assign' => false, 'due' => null, 'desc' => 'Identify and remove unused dependencies bloating the main bundle.'],

            // To Do
            ['title' => 'Implement Google Workspace SSO', 'type' => 'feature', 'priority' => 'high', 'status' => 'todo', 'category' => 'Backend', 'assign' => true, 'due' => 6, 'desc' => 'Allow staff to sign in with their company Google accounts.'],
            ['title' => 'Add 2FA for admin accounts', 'type' => 'feature', 'priority' => 'high', 'status' => 'todo', 'category' => 'Backend', 'assign' => true, 'due' => 9, 'desc' => null],
            ['title' => 'Fix dark-mode contrast on data tables', 'type' => 'bug', 'priority' => 'low', 'status' => 'todo', 'category' => 'Design', 'assign' => true, 'due' => 4, 'desc' => 'Secondary text fails WCAG AA on dark surfaces.'],
            ['title' => 'Set up staging deployment pipeline', 'type' => 'task', 'priority' => 'medium', 'status' => 'todo', 'category' => 'DevOps', 'assign' => true, 'due' => 3, 'desc' => null],

            // In Progress
            ['title' => 'Login returns 500 on Safari 17', 'type' => 'bug', 'priority' => 'urgent', 'status' => 'in_progress', 'category' => 'Backend', 'assign' => true, 'due' => -2, 'desc' => 'Session cookie SameSite handling breaks the OAuth callback on Safari.'],
            ['title' => 'Optimize slow orders report query', 'type' => 'improvement', 'priority' => 'high', 'status' => 'in_progress', 'category' => 'Database', 'assign' => true, 'due' => 0, 'desc' => 'Report takes 12s on large datasets; add covering indexes.'],
            ['title' => 'Refactor the notification service', 'type' => 'task', 'priority' => 'medium', 'status' => 'in_progress', 'category' => 'Backend', 'assign' => false, 'due' => 5, 'desc' => null],

            // Review
            ['title' => 'Dashboard charts load slowly', 'type' => 'improvement', 'priority' => 'medium', 'status' => 'review', 'category' => 'Frontend', 'assign' => true, 'due' => 0, 'desc' => 'Defer chart rendering until the data is in view.'],
            ['title' => 'Mobile nav overlaps content on iOS', 'type' => 'bug', 'priority' => 'high', 'status' => 'review', 'category' => 'Frontend', 'assign' => true, 'due' => 1, 'desc' => null],

            // Testing
            ['title' => 'Nightly DB backup verification job', 'type' => 'task', 'priority' => 'high', 'status' => 'testing', 'category' => 'DevOps', 'assign' => true, 'due' => 2, 'desc' => 'Restore the latest backup into a scratch DB and assert row counts.'],
            ['title' => 'Fix flaky session-timeout test', 'type' => 'bug', 'priority' => 'medium', 'status' => 'testing', 'category' => 'Backend', 'assign' => true, 'due' => -3, 'desc' => null],

            // Done
            ['title' => 'Migrate file storage to S3', 'type' => 'task', 'priority' => 'medium', 'status' => 'done', 'category' => 'Infrastructure', 'assign' => true, 'due' => -6, 'desc' => 'Moved uploads off local disk onto S3 with signed URLs.'],
            ['title' => 'Upgrade Laravel to 12.x', 'type' => 'task', 'priority' => 'high', 'status' => 'done', 'category' => 'Backend', 'assign' => true, 'due' => -9, 'desc' => null],
            ['title' => 'Rate-limit the webhook endpoint', 'type' => 'improvement', 'priority' => 'high', 'status' => 'done', 'category' => 'Backend', 'assign' => true, 'due' => -12, 'desc' => 'Added throttling to prevent abuse of the public webhook.'],
        ];

        $positions = [];

        // Suppress model events so seeding does not fire assignment notifications.
        ItTicket::withoutEvents(function () use ($tickets, $reporter, $categories, &$positions): void {
            foreach ($tickets as $t) {
                $status = $t['status'];
                $positions[$status] = ($positions[$status] ?? -1) + 1;

                ItTicket::create([
                    'ticket_number' => ItTicket::generateTicketNumber(),
                    'title' => $t['title'],
                    'description' => $t['desc'] ?? null,
                    'type' => $t['type'],
                    'priority' => $t['priority'],
                    'category_id' => isset($t['category']) ? ($categories[$t['category']] ?? null) : null,
                    'status' => $status,
                    'position' => $positions[$status],
                    'reporter_id' => $reporter->id,
                    'assignee_id' => $t['assign'] ? $reporter->id : null,
                    'due_date' => $t['due'] === null ? null : now()->addDays($t['due'])->toDateString(),
                    'completed_at' => $status === 'done' ? now()->subDays(rand(1, 10)) : null,
                ]);
            }
        });

        $this->command?->info('Seeded '.count($tickets).' IT Board tickets.');
    }
}

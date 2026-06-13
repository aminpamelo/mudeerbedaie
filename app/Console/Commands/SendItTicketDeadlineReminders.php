<?php

namespace App\Console\Commands;

use App\Models\ItTicket;
use App\Models\User;
use App\Notifications\ItTicketDeadlineReminderNotification;
use Illuminate\Console\Command;

class SendItTicketDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'it-board:send-deadline-reminders {--period=morning : Reminder period label (morning|evening)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify assignees about their overdue and due-today IT tickets (twice daily).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $period = $this->option('period') === 'evening' ? 'evening' : 'morning';
        $today = now()->startOfDay();

        $tickets = ItTicket::query()
            ->with('assignee')
            ->whereNotNull('assignee_id')
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today)
            ->get()
            ->groupBy('assignee_id');

        if ($tickets->isEmpty()) {
            $this->info('No overdue or due-today tickets with an assignee. Nothing to send.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($tickets as $assigneeId => $group) {
            $assignee = $group->first()->assignee ?? User::find($assigneeId);

            if (! $assignee) {
                continue;
            }

            $overdue = $group->filter(fn (ItTicket $t) => $t->deadlineStatus() === 'overdue')->values();
            $dueToday = $group->filter(fn (ItTicket $t) => $t->deadlineStatus() === 'due_today')->values();

            if ($overdue->isEmpty() && $dueToday->isEmpty()) {
                continue;
            }

            $assignee->notify(new ItTicketDeadlineReminderNotification($overdue, $dueToday, $period));
            $sent++;

            $this->line("Reminded {$assignee->name}: {$overdue->count()} overdue, {$dueToday->count()} due today.");
        }

        $this->info("Sent {$sent} deadline reminder(s) [{$period}].");

        return self::SUCCESS;
    }
}

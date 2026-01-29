<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateDepartmentUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-department-roles {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update users.role for existing department members to use pic_department or member_department roles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        // Get all department users with their roles
        $departmentUsers = DB::table('department_users')
            ->join('users', 'department_users.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'users.role as current_role', 'department_users.role as dept_role')
            ->get();

        if ($departmentUsers->isEmpty()) {
            $this->info('No department users found.');

            return self::SUCCESS;
        }

        $this->info("Found {$departmentUsers->count()} department user(s)");
        $this->newLine();

        $updated = 0;
        $skipped = 0;

        foreach ($departmentUsers as $du) {
            // Determine what the new role should be
            $newRole = $du->dept_role === 'department_pic' ? 'pic_department' : 'member_department';

            // Check if update is needed
            if ($du->current_role === $newRole) {
                $this->line("  [SKIP] {$du->name} ({$du->email}) - Already has role: {$newRole}");
                $skipped++;

                continue;
            }

            // Skip if user is admin, teacher, or other special role
            if (in_array($du->current_role, ['admin', 'teacher', 'class_admin', 'live_host', 'admin_livehost'])) {
                $this->warn("  [SKIP] {$du->name} ({$du->email}) - Has special role: {$du->current_role}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->info("  [WOULD UPDATE] {$du->name} ({$du->email}): {$du->current_role} -> {$newRole}");
            } else {
                User::where('id', $du->id)->update(['role' => $newRole]);
                $this->info("  [UPDATED] {$du->name} ({$du->email}): {$du->current_role} -> {$newRole}");
            }

            $updated++;
        }

        $this->newLine();
        $this->info("Summary: {$updated} updated, {$skipped} skipped");

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->comment('Run without --dry-run to apply changes.');
        }

        return self::SUCCESS;
    }
}

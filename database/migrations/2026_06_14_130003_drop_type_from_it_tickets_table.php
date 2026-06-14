<?php

use App\Models\ItTicket;
use App\Models\ItTicketType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the legacy enum-style `type` string column now that tickets reference
     * the customizable `it_ticket_types` table via `type_id`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('it_tickets', 'type')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Restore the string column and re-derive it from the related type so the
     * change stays fully reversible.
     */
    public function down(): void
    {
        if (Schema::hasColumn('it_tickets', 'type')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->string('type')->default('task')->after('description');
        });

        $typesById = ItTicketType::query()->get()->keyBy('id');

        ItTicket::query()->chunkById(200, function ($tickets) use ($typesById): void {
            foreach ($tickets as $ticket) {
                $name = $typesById->get($ticket->type_id)?->name;

                if ($name) {
                    $ticket->type = mb_strtolower($name);
                    $ticket->saveQuietly();
                }
            }
        });
    }
};

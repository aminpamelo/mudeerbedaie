<?php

use App\Models\ItTicket;
use App\Models\ItTicketType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('it_tickets', 'type_id')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->nullable()
                ->after('type')
                ->constrained('it_ticket_types')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Map each ticket's legacy `type` string onto the matching seeded type row.
     */
    private function backfill(): void
    {
        if (! Schema::hasColumn('it_tickets', 'type')) {
            return;
        }

        $typesByName = ItTicketType::query()->get()->keyBy(fn (ItTicketType $type): string => mb_strtolower($type->name));

        ItTicket::query()->chunkById(200, function ($tickets) use ($typesByName): void {
            foreach ($tickets as $ticket) {
                $legacy = mb_strtolower((string) $ticket->getRawOriginal('type'));
                $typeId = $typesByName->get($legacy)?->id;

                if ($typeId) {
                    $ticket->updateQuietly(['type_id' => $typeId]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('it_tickets', 'type_id')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });
    }
};

<?php

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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_id')->unique();
            $table->string('full_name');
            $table->string('ic_number');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female']);
            $table->enum('religion', ['islam', 'christian', 'buddhist', 'hindu', 'sikh', 'other']);
            $table->enum('race', ['malay', 'chinese', 'indian', 'other']);
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed']);
            $table->string('phone');
            $table->string('personal_email');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postcode');
            $table->string('profile_photo')->nullable();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern']);
            $table->date('join_date');
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('status', ['active', 'probation', 'resigned', 'terminated']);
            $table->date('resignation_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->string('bank_name');
            $table->string('bank_account_number');
            $table->string('epf_number')->nullable();
            $table->string('socso_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

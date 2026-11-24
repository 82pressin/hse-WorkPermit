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
        Schema::create('permit_requests', function (Blueprint $table) {
            $table->id();
            $table->string('permit_number')->nullable()->unique();   // diisi saat diterbitkan
            $table->foreignId('requester_id')->constrained('users');
            $table->string('department');                             // dept pemohon saat mengajukan

            $table->string('vendor_name');
            $table->text('job_description');                          // uraian pekerjaan
            $table->string('location');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('personnel_count');

            // 7 checkbox "Jenis Pekerjaan" di form FRM-HSE-007
            $table->boolean('is_hot_work')->default(false);           // Panas
            $table->boolean('is_work_at_height')->default(false);     // Di ketinggian > 1,8 m
            $table->boolean('is_confined_space')->default(false);     // Ruang terbatas
            $table->boolean('is_excavation')->default(false);         // Penggalian
            $table->boolean('is_chemical')->default(false);           // Pekerjaan kimia
            $table->boolean('is_electrical')->default(false);        // Elektrikal
            $table->boolean('is_mechanical')->default(false);         // Mekanikal

            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level')->nullable();

            $table->string('status')->default('diajukan');
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permit_requests');
    }
};

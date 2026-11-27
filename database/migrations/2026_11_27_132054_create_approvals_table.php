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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');            // jsea, surat_izin
            $table->string('approver_role');            // spv_hse, ka_dept_ga, gm
            $table->foreignId('approved_by')->constrained('users');
            $table->timestamp('approved_at');
            $table->text('note')->nullable();
            $table->timestamp('expired_at')->nullable(); // terisi = approval sudah tidak berlaku
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};

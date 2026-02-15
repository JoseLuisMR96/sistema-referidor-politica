<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_registrations', function (Blueprint $table) {
            $table->id();

            $table->string('full_name', 150);
            $table->string('document_type', 10);
            $table->string('document_number', 30);

            $table->unsignedTinyInteger('age');
            $table->string('gender', 20);

            $table->string('residence_municipality', 120);
            $table->string('voting_municipality', 120);

            $table->string('phone', 20);

            $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
            $table->string('ref_code_used', 20)->nullable();

            $table->string('status', 30)->default('pendiente');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Antiduplicados (ajústalo a tu negocio)
            $table->unique(['document_type', 'document_number'], 'uniq_doc_type_number');
            $table->index(['referrer_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_registrations');
    }
};

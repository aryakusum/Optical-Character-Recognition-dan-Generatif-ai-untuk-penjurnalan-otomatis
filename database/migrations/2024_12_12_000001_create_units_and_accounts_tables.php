<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel unit (prodi, fakultas, direktorat, dll)
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['prodi', 'fakultas', 'direktorat', 'lainnya'])->default('prodi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabel Chart of Accounts (COA)
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->default('expense');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pivot: mapping unit ke akun yang diizinkan
        Schema::create('unit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_accounts');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('units');
    }
};

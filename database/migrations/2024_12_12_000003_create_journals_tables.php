<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel header jurnal umum
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number')->unique(); // Nomor jurnal
            $table->date('transaction_date'); // Tanggal transaksi
            $table->string('document_type')->nullable(); // Tipe dokumen (kuitansi, invoice, dll)
            $table->string('document_number')->nullable(); // Nomor dokumen asli
            $table->string('vendor')->nullable(); // Nama vendor/toko
            $table->text('description')->nullable(); // Keterangan
            $table->decimal('total_amount', 15, 2)->default(0); // Total transaksi
            $table->string('currency', 10)->default('IDR');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->json('raw_data')->nullable(); // Data mentah dari AI
            $table->timestamps();
        });

        // Tabel detail jurnal (lines)
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->string('account_code'); // Kode akun
            $table->string('account_name'); // Nama akun
            $table->text('description')->nullable(); // Keterangan baris
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
    }
};

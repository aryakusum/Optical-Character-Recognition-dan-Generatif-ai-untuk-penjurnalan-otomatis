<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('raw_data');
            $table->string('document_original_name')->nullable()->after('document_path');

            $table->timestamp('verified_unit_at')->nullable()->after('document_original_name');
            $table->foreignId('verified_unit_by')->nullable()->constrained('users')->nullOnDelete()->after('verified_unit_at');
            $table->text('verified_unit_notes')->nullable()->after('verified_unit_by');

            $table->timestamp('verified_finance_at')->nullable()->after('verified_unit_notes');
            $table->foreignId('verified_finance_by')->nullable()->constrained('users')->nullOnDelete()->after('verified_finance_at');
            $table->text('verified_finance_notes')->nullable()->after('verified_finance_by');
        });

        DB::statement("ALTER TABLE journals MODIFY COLUMN status ENUM('draft', 'verified_unit', 'verified_finance', 'posted', 'rejected', 'void') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE journals MODIFY COLUMN status ENUM('draft', 'posted', 'void') DEFAULT 'draft'");

        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['verified_unit_by']);
            $table->dropForeign(['verified_finance_by']);
            $table->dropColumn([
                'document_path',
                'document_original_name',
                'verified_unit_at',
                'verified_unit_by',
                'verified_unit_notes',
                'verified_finance_at',
                'verified_finance_by',
                'verified_finance_notes',
            ]);
        });
    }
};

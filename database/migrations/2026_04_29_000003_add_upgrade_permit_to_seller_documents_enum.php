<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // PostgreSQL stores enum as a CHECK constraint — drop old one and add updated one
        DB::statement("ALTER TABLE seller_documents DROP CONSTRAINT IF EXISTS seller_documents_document_type_check");
        DB::statement("ALTER TABLE seller_documents ADD CONSTRAINT seller_documents_document_type_check CHECK (document_type IN ('valid_id','dti','business_permit','upgrade_permit'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE seller_documents DROP CONSTRAINT IF EXISTS seller_documents_document_type_check");
        DB::statement("ALTER TABLE seller_documents ADD CONSTRAINT seller_documents_document_type_check CHECK (document_type IN ('valid_id','dti','business_permit'))");
    }
};

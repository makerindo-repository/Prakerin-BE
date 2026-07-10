<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite tidak support ALTER COLUMN untuk ubah CHECK constraint,
        // jadi kita rebuild tabel mous dengan struktur yang benar,
        // sambil mempertahankan data yang sudah ada.

        Schema::disableForeignKeyConstraints();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                CREATE TABLE mous_new (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    company_id CHAR(36) NOT NULL,
                    school_id CHAR(36) NOT NULL,
                    message TEXT NOT NULL,
                    reason TEXT NULL,
                    file VARCHAR(255) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT "pending",
                    is_company_accepted BOOLEAN NULL,
                    is_school_accepted BOOLEAN NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
                )
            ');
        } else {
            DB::statement('
                CREATE TABLE mous_new (
                    id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
                    company_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                    school_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                    message TEXT NOT NULL,
                    reason TEXT NULL,
                    file VARCHAR(255) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    status ENUM("pending", "accepted", "rejected") NOT NULL DEFAULT "pending",
                    is_company_accepted BOOLEAN NULL,
                    is_school_accepted BOOLEAN NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
                    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
            ');
        }

        // Copy data lama ke tabel baru.
        // TRIM() untuk bersihkan spasi nyasar dari data yang mungkin sudah keburu
        // ke-insert dengan value lama (kalau ada).
        DB::statement('
            INSERT INTO mous_new (
                id, company_id, school_id, message, reason, file,
                start_date, end_date, status, is_company_accepted,
                is_school_accepted, created_at, updated_at
            )
            SELECT
                id, company_id, school_id, message, reason, file,
                start_date, end_date,
                TRIM(status),
                is_company_accepted, is_school_accepted, created_at, updated_at
            FROM mous
        ');

        DB::statement('DROP TABLE mous');
        DB::statement('ALTER TABLE mous_new RENAME TO mous');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback tidak didukung untuk migration data-preserving seperti ini.
        // Kalau perlu rollback, restore dari backup database.
    }
};

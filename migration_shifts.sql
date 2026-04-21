-- =============================================================================
-- MIGRATION: Tabel Shift Kasir
-- Jalankan di Navicat sebelum deploy kode
-- =============================================================================

USE pos_coffee_shop;

-- Tabel shift kasir
CREATE TABLE IF NOT EXISTS shifts (
    id              CHAR(36)        NOT NULL,
    outlet_id       CHAR(36)        NOT NULL,
    code            VARCHAR(20)     NULL,           -- SHF-0001
    cashier_id      CHAR(36)        NOT NULL,        -- kasir yang buka shift
    opened_by       CHAR(36)        NOT NULL,        -- bisa sama dengan cashier_id atau manager
    closed_by       CHAR(36)        NULL,
    status          ENUM('open','closed') NOT NULL DEFAULT 'open',
    opening_cash    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,  -- modal awal kas
    closing_cash    DECIMAL(15,2)   NULL,                    -- uang kas saat tutup
    expected_cash   DECIMAL(15,2)   NULL,                    -- seharusnya ada (otomatis hitung)
    difference      DECIMAL(15,2)   NULL,                    -- selisih (closing - expected)
    total_orders    INT             NOT NULL DEFAULT 0,
    total_revenue   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_cash      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,   -- total bayar cash
    total_qris      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_edc       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_ewallet   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    total_discount  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    notes_open      TEXT            NULL,
    notes_close     TEXT            NULL,
    opened_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at       DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shift_code        (outlet_id, code),
    KEY idx_shifts_outlet           (outlet_id, opened_at),
    KEY idx_shifts_cashier          (cashier_id),
    KEY idx_shifts_status           (status),
    CONSTRAINT fk_shifts_outlet     FOREIGN KEY (outlet_id)   REFERENCES outlets(id),
    CONSTRAINT fk_shifts_cashier    FOREIGN KEY (cashier_id)  REFERENCES users(id),
    CONSTRAINT fk_shifts_opened_by  FOREIGN KEY (opened_by)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambah kolom shift_id ke orders supaya order bisa dikaitkan ke shift
ALTER TABLE orders
    ADD COLUMN shift_id CHAR(36) NULL COMMENT 'Shift saat order dibuat'
    AFTER cashier_id;

ALTER TABLE orders
    ADD KEY idx_orders_shift (shift_id);

-- Tambah counter shift ke code_counters
INSERT IGNORE INTO code_counters (outlet_id, entity, prefix, last_number)
SELECT id, 'shifts', 'SHF', 0 FROM outlets LIMIT 1;

-- Verifikasi
SELECT 'Tabel shifts berhasil dibuat' AS status;
DESCRIBE shifts;

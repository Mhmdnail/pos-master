<?php

// =============================================================================
// CODE GENERATOR HELPER
// File: app/Helpers/code_helper.php
//
// Cara pakai di Controller:
//   helper('code');
//   $code = generate_code('products', $outletId);   // → PRD-0001
//   $code = generate_code('orders', $outletId);     // → ORD-20260407-0001
// =============================================================================

if (! function_exists('generate_code')) {
    /**
     * Generate kode human-readable untuk entitas tertentu
     * Menggunakan tabel code_counters sebagai sumber kebenaran
     * Transaction-safe: pakai SELECT ... FOR UPDATE supaya tidak tabrakan
     */
    function generate_code(string $entity, string $outletId): string
    {
        $db = \Config\Database::connect();

        // Entitas yang pakai format dengan tanggal (reset tiap hari)
        $withDate = ['orders', 'purchase_orders', 'journal_entries'];

        // Mapping entity → prefix
        $prefixes = [
            'outlets'         => 'OTL',
            'users'           => 'USR',
            'categories'      => 'CAT',
            'products'        => 'PRD',
            'raw_materials'   => 'MAT',
            'suppliers'       => 'SUP',
            'bundles'         => 'BDL',
            'discounts'       => 'DSC',
            'customers'       => 'CST',
            'orders'          => 'ORD',
            'purchase_orders' => 'PO',
            'journal_entries' => 'JRN',
            'accounts'        => 'ACC',
        ];

        $prefix = $prefixes[$entity] ?? strtoupper(substr($entity, 0, 3));

        $db->transStart();

        // Lock row supaya tidak ada dua request generate kode yang sama bersamaan
        $counter = $db->query(
            'SELECT * FROM code_counters
             WHERE outlet_id = ? AND entity = ?
             FOR UPDATE',
            [$outletId, $entity]
        )->getRowArray();

        if (! $counter) {
            // Belum ada counter untuk entity ini, buat baru
            $db->query(
                'INSERT INTO code_counters (outlet_id, entity, prefix, last_number)
                 VALUES (?, ?, ?, 1)',
                [$outletId, $entity, $prefix]
            );
            $nextNumber = 1;
        } else {
            $nextNumber = $counter['last_number'] + 1;
            $db->query(
                'UPDATE code_counters
                 SET last_number = ?
                 WHERE outlet_id = ? AND entity = ?',
                [$nextNumber, $outletId, $entity]
            );
        }

        $db->transComplete();

        // Format kode
        if (in_array($entity, $withDate, true)) {
            // Format dengan tanggal: ORD-20260407-0001
            return $prefix . '-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        // Format statis: PRD-0001
        return $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}


if (! function_exists('init_counters')) {
    /**
     * Inisialisasi semua counter untuk outlet baru
     * Dipanggil saat pertama kali outlet dibuat
     */
    function init_counters(string $outletId): void
    {
        $db = \Config\Database::connect();

        $entities = [
            ['outlet_id' => $outletId, 'entity' => 'outlets',         'prefix' => 'OTL', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'users',           'prefix' => 'USR', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'categories',      'prefix' => 'CAT', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'products',        'prefix' => 'PRD', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'raw_materials',   'prefix' => 'MAT', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'suppliers',       'prefix' => 'SUP', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'bundles',         'prefix' => 'BDL', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'discounts',       'prefix' => 'DSC', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'customers',       'prefix' => 'CST', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'orders',          'prefix' => 'ORD', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'purchase_orders', 'prefix' => 'PO',  'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'journal_entries', 'prefix' => 'JRN', 'last_number' => 0],
            ['outlet_id' => $outletId, 'entity' => 'accounts',        'prefix' => 'ACC', 'last_number' => 0],
        ];

        foreach ($entities as $row) {
            // INSERT IGNORE supaya tidak error kalau sudah ada
            $db->query(
                'INSERT IGNORE INTO code_counters (outlet_id, entity, prefix, last_number)
                 VALUES (?, ?, ?, ?)',
                [$row['outlet_id'], $row['entity'], $row['prefix'], $row['last_number']]
            );
        }
    }
}


if (! function_exists('peek_next_code')) {
    /**
     * Lihat kode berikutnya TANPA increment counter
     * Berguna untuk preview di form sebelum data disimpan
     */
    function peek_next_code(string $entity, string $outletId): string
    {
        $db = \Config\Database::connect();

        $withDate = ['orders', 'purchase_orders', 'journal_entries'];

        $prefixes = [
            'outlets'         => 'OTL',
            'users'           => 'USR',
            'categories'      => 'CAT',
            'products'        => 'PRD',
            'raw_materials'   => 'MAT',
            'suppliers'       => 'SUP',
            'bundles'         => 'BDL',
            'discounts'       => 'DSC',
            'customers'       => 'CST',
            'orders'          => 'ORD',
            'purchase_orders' => 'PO',
            'journal_entries' => 'JRN',
            'accounts'        => 'ACC',
        ];

        $prefix = $prefixes[$entity] ?? strtoupper(substr($entity, 0, 3));

        $counter = $db->query(
            'SELECT last_number FROM code_counters WHERE outlet_id = ? AND entity = ?',
            [$outletId, $entity]
        )->getRowArray();

        $nextNumber = ($counter['last_number'] ?? 0) + 1;

        if (in_array($entity, $withDate, true)) {
            return $prefix . '-' . date('Ymd') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $prefix . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

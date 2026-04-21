<?php
/**
 * UUID & Code Helpers
 * Semua fungsi generate ID di sini
 */

if (! function_exists('generate_uuid')) {
    /**
     * Generate UUID v4 yang benar-benar random menggunakan random_bytes.
     * JANGAN pakai uniqid() atau mt_rand() — tidak cukup unik untuk primary key.
     */
    function generate_uuid(): string
    {
        // Gunakan random_bytes — cryptographically secure, tidak terpengaruh waktu
        $data    = random_bytes(16);
        // Set versi 4 (0100xxxx)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant bits (10xxxxxx)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (! function_exists('generate_order_number')) {
    /**
     * Generate nomor order format: ORD-YYYYMMDD-NNNN
     */
    function generate_order_number(string $outletId): string
    {
        return generate_code('orders', $outletId);
    }
}

if (! function_exists('generate_code')) {
    /**
     * Generate kode human-readable dengan auto-increment per entity per outlet.
     * Contoh: PRD-0001, ORD-20260421-0001, SHF-0001
     */
    function generate_code(string $entity, string $outletId): string
    {
        $db      = \Config\Database::connect();
        $counter = $db->table('code_counters')
                      ->where('outlet_id', $outletId)
                      ->where('entity', $entity)
                      ->get()
                      ->getRowArray();

        if (! $counter) {
            // Fallback prefix kalau belum ada counter
            $prefix = strtoupper(substr($entity, 0, 3));
            return $prefix . '-' . str_pad('1', 4, '0', STR_PAD_LEFT);
        }

        $next   = (int)$counter['last_number'] + 1;
        $prefix = $counter['prefix'] ?? strtoupper(substr($entity, 0, 3));

        // Format khusus untuk orders: prefix-YYYYMMDD-NNNN
        if ($entity === 'orders') {
            $code = $prefix . '-' . date('Ymd') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        } else {
            $code = $prefix . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        }

        // Update counter
        $db->table('code_counters')
           ->where('outlet_id', $outletId)
           ->where('entity', $entity)
           ->update(['last_number' => $next]);

        return $code;
    }
}

if (! function_exists('peek_next_code')) {
    /**
     * Lihat kode berikutnya tanpa increment counter (untuk preview).
     */
    function peek_next_code(string $entity, string $outletId): string
    {
        $db      = \Config\Database::connect();
        $counter = $db->table('code_counters')
                      ->where('outlet_id', $outletId)
                      ->where('entity', $entity)
                      ->get()
                      ->getRowArray();

        if (! $counter) return strtoupper(substr($entity, 0, 3)) . '-0001';

        $next   = (int)$counter['last_number'] + 1;
        $prefix = $counter['prefix'] ?? strtoupper(substr($entity, 0, 3));

        if ($entity === 'orders') {
            return $prefix . '-' . date('Ymd') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        }
        return $prefix . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('init_counters')) {
    /**
     * Inisialisasi semua counter untuk outlet baru.
     */
    function init_counters(string $outletId): void
    {
        $db       = \Config\Database::connect();
        $entities = [
            ['entity' => 'orders',          'prefix' => 'ORD'],
            ['entity' => 'products',         'prefix' => 'PRD'],
            ['entity' => 'categories',       'prefix' => 'CAT'],
            ['entity' => 'raw_materials',    'prefix' => 'MAT'],
            ['entity' => 'suppliers',        'prefix' => 'SUP'],
            ['entity' => 'purchase_orders',  'prefix' => 'PO'],
            ['entity' => 'discounts',        'prefix' => 'DSC'],
            ['entity' => 'customers',        'prefix' => 'CST'],
            ['entity' => 'users',            'prefix' => 'USR'],
            ['entity' => 'shifts',           'prefix' => 'SHF'],
            ['entity' => 'bundles',          'prefix' => 'BDL'],
            ['entity' => 'journal_entries',  'prefix' => 'JRN'],
        ];

        foreach ($entities as $e) {
            $exists = $db->table('code_counters')
                         ->where('outlet_id', $outletId)
                         ->where('entity', $e['entity'])
                         ->countAllResults();
            if (! $exists) {
                $db->table('code_counters')->insert([
                    'outlet_id'   => $outletId,
                    'entity'      => $e['entity'],
                    'prefix'      => $e['prefix'],
                    'last_number' => 0,
                ]);
            }
        }
    }
}

<?php

if (! function_exists('generate_uuid')) {
    /**
     * Generate UUID v4
     * Dipakai di semua model sebelum insert karena MariaDB 10.4
     * tidak support DEFAULT (UUID()) di kolom CHAR(36)
     */
    function generate_uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (! function_exists('is_valid_uuid')) {
    /**
     * Validasi format UUID v4
     */
    function is_valid_uuid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}

if (! function_exists('generate_order_number')) {
    /**
     * Generate nomor order: ORD-20240315-0001
     * Counter di-handle dari DB (ambil count order hari ini + 1)
     */
    function generate_order_number(int $counter): string
    {
        return 'ORD-' . date('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('generate_journal_number')) {
    /**
     * Generate nomor jurnal: JRN-20240315-0001
     */
    function generate_journal_number(int $counter): string
    {
        return 'JRN-' . date('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('generate_po_number')) {
    /**
     * Generate nomor PO: PO-20240315-0001
     */
    function generate_po_number(int $counter): string
    {
        return 'PO-' . date('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }
}

<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ============================================================
// Handle preflight OPTIONS request untuk CORS
// ============================================================
$routes->options('(:any)', static function () {
    return response()->setStatusCode(204);
});

// ============================================================
// API v1 — semua prefix /api/v1
// ============================================================
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], static function ($routes) {

    // ----------------------------------------------------------
    // PUBLIC — tidak butuh auth
    // ----------------------------------------------------------
    $routes->post('auth/login',   'AuthController::login');
    $routes->post('auth/refresh', 'AuthController::refresh');

    // ----------------------------------------------------------
    // PROTECTED — butuh JWT token
    // ----------------------------------------------------------
    $routes->group('', ['filter' => 'auth'], static function ($routes) {

        // Auth
        $routes->post('auth/logout', 'AuthController::logout');
        $routes->get('auth/me',      'AuthController::me');

        // ----------------------------------------------------------
        // Master Data
        // ----------------------------------------------------------

        // Kategori
        $routes->get('categories',            'CategoryController::index');
        $routes->post('categories',           'CategoryController::create');
        $routes->get('categories/(:segment)', 'CategoryController::show/$1');
        $routes->put('categories/(:segment)', 'CategoryController::update/$1');
        $routes->delete('categories/(:segment)', 'CategoryController::delete/$1');

        // Produk
        $routes->get('products',                  'ProductController::index');
        $routes->post('products',                 'ProductController::create');
        $routes->get('products/(:segment)',        'ProductController::show/$1');
        $routes->put('products/(:segment)',        'ProductController::update/$1');
        $routes->delete('products/(:segment)',     'ProductController::delete/$1');
        $routes->get('products/(:segment)/stock', 'ProductController::stockInfo/$1');

        // Modifier produk
        $routes->get('products/(:segment)/modifiers',    'ModifierController::index/$1');
        $routes->post('products/(:segment)/modifiers',   'ModifierController::create/$1');
        $routes->put('modifiers/(:segment)',             'ModifierController::update/$1');
        $routes->delete('modifiers/(:segment)',          'ModifierController::delete/$1');

        // ----------------------------------------------------------
        // Bundling
        // ----------------------------------------------------------
        $routes->get('bundles',            'BundleController::index');
        $routes->post('bundles',           'BundleController::create');
        $routes->get('bundles/(:segment)', 'BundleController::show/$1');
        $routes->put('bundles/(:segment)', 'BundleController::update/$1');
        $routes->delete('bundles/(:segment)', 'BundleController::delete/$1');

        // ----------------------------------------------------------
        // Diskon
        // ----------------------------------------------------------
        $routes->get('discounts',                      'DiscountController::index');
        $routes->post('discounts',                     'DiscountController::create');
        $routes->get('discounts/(:segment)',            'DiscountController::show/$1');
        $routes->put('discounts/(:segment)',            'DiscountController::update/$1');
        $routes->delete('discounts/(:segment)',         'DiscountController::delete/$1');
        $routes->post('discounts/validate',             'DiscountController::validate'); // cek voucher
        $routes->post('discounts/calculate',            'DiscountController::calculate'); // hitung diskon untuk order

        // ----------------------------------------------------------
        // Inventory & Bahan Baku
        // ----------------------------------------------------------
        $routes->get('materials',                   'MaterialController::index');
        $routes->post('materials',                  'MaterialController::create');
        $routes->get('materials/(:segment)',         'MaterialController::show/$1');
        $routes->put('materials/(:segment)',         'MaterialController::update/$1');
        $routes->delete('materials/(:segment)',      'MaterialController::delete/$1');
        $routes->get('materials/low-stock',          'MaterialController::lowStock');

        // BOM / Resep
        $routes->get('products/(:segment)/recipe',   'RecipeController::show/$1');
        $routes->post('products/(:segment)/recipe',  'RecipeController::save/$1');

        // Stock opname & adjustment
        $routes->post('materials/(:segment)/adjust', 'MaterialController::adjust/$1');
        $routes->get('stock-movements',              'StockMovementController::index');

        // ----------------------------------------------------------
        // Supplier & Purchase Order
        // ----------------------------------------------------------
        $routes->get('suppliers',              'SupplierController::index');
        $routes->post('suppliers',             'SupplierController::create');
        $routes->get('suppliers/(:segment)',   'SupplierController::show/$1');
        $routes->put('suppliers/(:segment)',   'SupplierController::update/$1');

        $routes->get('purchase-orders',                     'PurchaseOrderController::index');
        $routes->post('purchase-orders',                    'PurchaseOrderController::create');
        $routes->get('purchase-orders/(:segment)',           'PurchaseOrderController::show/$1');
        $routes->put('purchase-orders/(:segment)',           'PurchaseOrderController::update/$1');
        $routes->post('purchase-orders/(:segment)/receive', 'PurchaseOrderController::receive/$1');

        // ----------------------------------------------------------
        // Pelanggan & Member
        // ----------------------------------------------------------
        $routes->get('customers',              'CustomerController::index');
        $routes->post('customers',             'CustomerController::create');
        $routes->get('customers/(:segment)',   'CustomerController::show/$1');
        $routes->put('customers/(:segment)',   'CustomerController::update/$1');
        $routes->get('member-tiers',           'CustomerController::tiers');

        // ----------------------------------------------------------
        // ORDER & TRANSAKSI (inti POS)
        // ----------------------------------------------------------
        $routes->get('orders',                        'OrderController::index');
        $routes->post('orders',                       'OrderController::create');      // buat order baru
        $routes->get('orders/(:segment)',              'OrderController::show/$1');
        $routes->put('orders/(:segment)/status',      'OrderController::updateStatus/$1');
        $routes->post('orders/(:segment)/cancel',     'OrderController::cancel/$1');
        $routes->post('orders/(:segment)/payment',    'OrderController::pay/$1');      // proses bayar
        $routes->get('orders/(:segment)/receipt',     'OrderController::receipt/$1');  // data struk

        // ----------------------------------------------------------
        // AKUNTANSI
        // ----------------------------------------------------------
        $routes->get('accounts',               'AccountController::index');
        $routes->post('accounts',              'AccountController::create');
        $routes->get('journal-entries',        'JournalController::index');
        $routes->get('journal-entries/(:segment)', 'JournalController::show/$1');

        // Kas
        $routes->get('kas',                    'KasController::index');
        $routes->post('kas',                   'KasController::create');
        $routes->get('kas/summary',            'KasController::summary');

        // Hutang & Piutang
        $routes->get('payables',               'PayableController::index');
        $routes->post('payables/(:segment)/pay', 'PayableController::pay/$1');
        $routes->get('receivables',            'ReceivableController::index');

        // ----------------------------------------------------------
        // LAPORAN
        // ----------------------------------------------------------
        $routes->get('reports/sales',          'ReportController::sales');          // omzet per periode
        $routes->get('reports/products',       'ReportController::products');       // produk terlaris
        $routes->get('reports/stock',          'ReportController::stock');          // laporan stok
        $routes->get('reports/hpp',            'ReportController::hpp');            // HPP vs revenue
        $routes->get('reports/cashflow',       'ReportController::cashflow');       // cash flow
        $routes->get('reports/profit-loss',    'ReportController::profitLoss');     // laba rugi
        $routes->get('reports/balance-sheet',  'ReportController::balanceSheet');   // neraca
        $routes->get('reports/discounts',      'ReportController::discounts');      // efektivitas promo

        // ----------------------------------------------------------
        // USER MANAGEMENT (owner & manager only)
        // ----------------------------------------------------------
        $routes->get('users',              'UserController::index');
        $routes->post('users',             'UserController::create');
        $routes->get('users/(:segment)',   'UserController::show/$1');
        $routes->put('users/(:segment)',   'UserController::update/$1');
        $routes->patch('users/(:segment)/toggle', 'UserController::toggle/$1');
    });
});

// ============================================================
// Fallback — route tidak ditemukan
// ============================================================
$routes->set404Override(static function () {
    return response()
        ->setStatusCode(404)
        ->setJSON([
            'status'  => false,
            'message' => 'Endpoint tidak ditemukan',
            'data'    => null,
        ]);
});

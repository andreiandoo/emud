<?php

use App\Livewire\Admin\Catalog\AttributesIndex;
use App\Livewire\Admin\Catalog\BrandsIndex;
use App\Livewire\Admin\Catalog\CategoriesIndex;
use App\Livewire\Admin\Catalog\ProductEditor;
use App\Livewire\Admin\Catalog\ProductsIndex;
use App\Livewire\Admin\CatalogPlatform\ApiConsumersIndex as CatalogApiConsumersIndex;
use App\Livewire\Admin\CatalogPlatform\ConflictsIndex as CatalogConflictsIndex;
use App\Livewire\Admin\CatalogPlatform\Explorer as CatalogExplorer;
use App\Livewire\Admin\CatalogPlatform\ImportRunsIndex as CatalogImportRunsIndex;
use App\Livewire\Admin\CatalogPlatform\PartDetail as CatalogPartDetail;
use App\Livewire\Admin\CatalogPlatform\QualityDashboard as CatalogQualityDashboard;
use App\Livewire\Admin\CatalogPlatform\SchemaExplorer as CatalogSchemaExplorer;
use App\Livewire\Admin\CatalogPlatform\SourceEditor as CatalogSourceEditor;
use App\Livewire\Admin\CatalogPlatform\SourceRecordDetail as CatalogSourceRecordDetail;
use App\Livewire\Admin\CatalogPlatform\SourceRecordsIndex as CatalogSourceRecordsIndex;
use App\Livewire\Admin\CatalogPlatform\SourcesIndex as CatalogSourcesIndex;
use App\Livewire\Admin\CatalogPlatform\SupplierMatchingIndex as CatalogSupplierMatchingIndex;
use App\Livewire\Admin\CatalogPlatform\UnresolvedRelationsIndex as CatalogUnresolvedRelationsIndex;
use App\Livewire\Admin\CatalogPlatform\VehicleDetail as CatalogVehicleDetail;
use App\Livewire\Admin\CommerceSettings;
use App\Livewire\Admin\Content\ArticleEditor;
use App\Livewire\Admin\Content\ArticlesIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\OrderEditor;
use App\Livewire\Admin\OrdersIndex;
use App\Livewire\Admin\Suppliers\SupplierEditor;
use App\Livewire\Admin\Suppliers\SuppliersIndex;
use App\Livewire\Admin\Suppliers\SyncRunsIndex;
use App\Livewire\Admin\VehiclesIndex;
use App\Livewire\Customer\Dashboard as CustomerDashboard;
use App\Livewire\Customer\ForgotPassword as CustomerForgotPassword;
use App\Livewire\Customer\Garage as CustomerGarage;
use App\Livewire\Customer\Login as CustomerLogin;
use App\Livewire\Customer\Orders as CustomerOrders;
use App\Livewire\Customer\Profile as CustomerProfile;
use App\Livewire\Customer\Register as CustomerRegister;
use App\Livewire\Customer\ResetPassword as CustomerResetPassword;
use App\Livewire\Storefront\CartPage as StorefrontCart;
use App\Livewire\Storefront\CategoryPage as StorefrontCategory;
use App\Livewire\Storefront\CheckoutPage as StorefrontCheckout;
use App\Livewire\Storefront\Home as StorefrontHome;
use App\Livewire\Storefront\OrderConfirmation as StorefrontOrder;
use App\Livewire\Storefront\ProductPage as StorefrontProduct;
use App\Livewire\Storefront\SearchResults as StorefrontSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontHome::class)->name('storefront.home');
Route::get('/cauta', StorefrontSearch::class)->name('storefront.search');
Route::get('/categorie/{category}', StorefrontCategory::class)->where('category', '.+')->name('storefront.category');
Route::get('/produs/{product:slug}', StorefrontProduct::class)->name('storefront.product');
Route::get('/cos', StorefrontCart::class)->name('storefront.cart');
Route::get('/finalizare', StorefrontCheckout::class)->name('storefront.checkout');
Route::get('/comanda/{order}', StorefrontOrder::class)->name('storefront.order');
Route::middleware('guest')->group(function (): void {
    Route::view('/admin/login', 'auth.admin-login')->name('admin.login');
    Route::post('/admin/login', function (Request $request) {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Datele de autentificare nu sunt corecte.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    })->middleware('throttle:6,1')->name('admin.login.store');
});
Route::post('/admin/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('admin.login');
})->middleware('auth')->name('admin.logout');

// Outside the customer.* name group on purpose: Laravel's own reset notification links to the
// route named exactly password.reset, and a prefixed name would leave the email pointing nowhere.
Route::prefix('cont')->middleware('guest')->group(function (): void {
    Route::get('/parola-uitata', CustomerForgotPassword::class)->name('password.request');
    Route::get('/reseteaza-parola/{token}', CustomerResetPassword::class)->name('password.reset');
});

Route::prefix('cont')->name('customer.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/autentificare', CustomerLogin::class)->name('login');
        Route::get('/inregistrare', CustomerRegister::class)->name('register');
    });

    Route::middleware('auth')->group(function (): void {
        Route::get('/', CustomerDashboard::class)->name('dashboard');
        Route::get('/garaj', CustomerGarage::class)->name('garage');
        Route::get('/comenzi', CustomerOrders::class)->name('orders');
        Route::get('/date', CustomerProfile::class)->name('profile');
        Route::post('/iesire', function (Request $request) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('storefront.home');
        })->name('logout');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/catalog-explorer', CatalogExplorer::class)->name('catalog-platform.explorer');
    Route::get('/catalog-schema', CatalogSchemaExplorer::class)->name('catalog-platform.schema');
    Route::get('/catalog-quality', CatalogQualityDashboard::class)->name('catalog-platform.quality');
    Route::get('/catalog-sources', CatalogSourcesIndex::class)->name('catalog-platform.sources');
    Route::get('/catalog-sources/create', CatalogSourceEditor::class)->name('catalog-platform.sources.create');
    Route::get('/catalog-sources/{source}/edit', CatalogSourceEditor::class)->name('catalog-platform.sources.edit');
    Route::get('/catalog-sources/{source}/records', CatalogSourceRecordsIndex::class)->name('catalog-platform.source-records');
    Route::get('/catalog-source-records/{record}', CatalogSourceRecordDetail::class)->name('catalog-platform.source-records.show');
    Route::get('/catalog-imports', CatalogImportRunsIndex::class)->name('catalog-platform.imports');
    Route::get('/catalog-conflicts', CatalogConflictsIndex::class)->name('catalog-platform.conflicts');
    Route::get('/catalog-unresolved-relations', CatalogUnresolvedRelationsIndex::class)->name('catalog-platform.unresolved-relations');
    Route::get('/catalog-api', CatalogApiConsumersIndex::class)->name('catalog-platform.api');
    Route::get('/catalog-supplier-matching', CatalogSupplierMatchingIndex::class)->name('catalog-platform.supplier-matching');
    Route::get('/catalog-parts/{part}', CatalogPartDetail::class)->name('catalog-platform.parts.show');
    Route::get('/catalog-vehicles/{vehicle}', CatalogVehicleDetail::class)->name('catalog-platform.vehicles.show');
    Route::get('/products', ProductsIndex::class)->name('products.index');
    Route::get('/products/create', ProductEditor::class)->name('products.create');
    Route::get('/products/{product}/edit', ProductEditor::class)->name('products.edit');
    Route::get('/categories', CategoriesIndex::class)->name('categories.index');
    Route::get('/attributes', AttributesIndex::class)->name('attributes.index');
    Route::get('/brands', BrandsIndex::class)->name('brands.index');
    Route::get('/media', MediaLibrary::class)->name('media.index');
    Route::get('/articles', ArticlesIndex::class)->name('articles.index');
    Route::get('/articles/create', ArticleEditor::class)->name('articles.create');
    Route::get('/articles/{article}/edit', ArticleEditor::class)->name('articles.edit');
    Route::get('/vehicles', VehiclesIndex::class)->name('vehicles.index');
    Route::get('/suppliers', SuppliersIndex::class)->name('suppliers.index');
    Route::get('/suppliers/create', SupplierEditor::class)->name('suppliers.create');
    Route::get('/suppliers/{supplier}/edit', SupplierEditor::class)->name('suppliers.edit');
    Route::get('/supplier-syncs', SyncRunsIndex::class)->name('suppliers.sync-runs');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/orders/{order}', OrderEditor::class)->name('orders.edit');
    Route::get('/commerce-settings', CommerceSettings::class)->name('commerce.settings');
});

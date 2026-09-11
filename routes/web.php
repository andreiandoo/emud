<?php

use App\Http\Controllers\Admin\WorkshopApiController;
use App\Http\Controllers\ServiceShopLinkController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Admin\Catalog\AttributesIndex;
use App\Livewire\Admin\Catalog\BrandsIndex;
use App\Livewire\Admin\Catalog\CategoriesIndex;
use App\Livewire\Admin\Catalog\CollectionEditor;
use App\Livewire\Admin\Catalog\CollectionsIndex;
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
use App\Livewire\Admin\Content\ArticleBlockEditor;
use App\Livewire\Admin\Content\ArticleEditor;
use App\Livewire\Admin\Content\ArticlesIndex;
use App\Livewire\Admin\Content\PagesIndex;
use App\Livewire\Admin\Content\ReviewEditor;
use App\Livewire\Admin\Content\ReviewsIndex;
use App\Livewire\Admin\CustomersIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\OrderEditor;
use App\Livewire\Admin\OrdersIndex;
use App\Livewire\Admin\Pricing\PriceChangesIndex;
use App\Livewire\Admin\Pricing\PricingRulesIndex;
use App\Livewire\Admin\ReturnsIndex;
use App\Livewire\Admin\ServiceAppointmentsIndex;
use App\Livewire\Admin\ServiceCatalogIndex;
use App\Livewire\Admin\ServiceShopEditor;
use App\Livewire\Admin\ServiceShopsIndex;
use App\Livewire\Admin\Settings\SettingsPage;
use App\Livewire\Admin\Suppliers\OffersIndex as SupplierOffersIndex;
use App\Livewire\Admin\Suppliers\SupplierEditor;
use App\Livewire\Admin\Suppliers\SuppliersIndex;
use App\Livewire\Admin\Suppliers\SyncRunsIndex;
use App\Livewire\Admin\VehiclesIndex;
use App\Livewire\Admin\Workshops\WorkshopDetail;
use App\Livewire\Admin\Workshops\WorkshopReviewQueue;
use App\Livewire\Admin\Workshops\WorkshopsIndex;
use App\Livewire\Admin\Workshops\WorkshopSourceRecordDetail;
use App\Livewire\Admin\Workshops\WorkshopSourcesIndex;
use App\Livewire\Customer\Appointments as CustomerAppointments;
use App\Livewire\Customer\Dashboard as CustomerDashboard;
use App\Livewire\Customer\Favourites as CustomerFavourites;
use App\Livewire\Customer\ForgotPassword as CustomerForgotPassword;
use App\Livewire\Customer\Garage as CustomerGarage;
use App\Livewire\Customer\Login as CustomerLogin;
use App\Livewire\Customer\Orders as CustomerOrders;
use App\Livewire\Customer\Profile as CustomerProfile;
use App\Livewire\Customer\Register as CustomerRegister;
use App\Livewire\Customer\ResetPassword as CustomerResetPassword;
use App\Livewire\Customer\VehicleDetail as CustomerVehicleDetail;
use App\Livewire\Storefront\AppointmentConfirmation as StorefrontAppointment;
use App\Livewire\Storefront\CartPage as StorefrontCart;
use App\Livewire\Storefront\CategoryPage as StorefrontCategory;
use App\Livewire\Storefront\CheckoutPage as StorefrontCheckout;
use App\Livewire\Storefront\CollectionPage as StorefrontCollection;
use App\Livewire\Storefront\CollectionsIndex as StorefrontCollections;
use App\Livewire\Storefront\Contact as StorefrontContact;
use App\Livewire\Storefront\GuidePage as StorefrontGuide;
use App\Livewire\Storefront\Guides as StorefrontGuides;
use App\Livewire\Storefront\Home as StorefrontHome;
use App\Livewire\Storefront\OrderConfirmation as StorefrontOrder;
use App\Livewire\Storefront\ProductPage as StorefrontProduct;
use App\Livewire\Storefront\SearchResults as StorefrontSearch;
use App\Livewire\Storefront\ServiceCityPage as StorefrontServiceCity;
use App\Livewire\Storefront\ServiceDirectory as StorefrontServices;
use App\Livewire\Storefront\ServiceShopPage as StorefrontService;
use App\Livewire\Storefront\ServiceTypePage as StorefrontServiceType;
use App\Livewire\Storefront\ServiceTypes as StorefrontServiceTypes;
use App\Livewire\Storefront\StaticPage as StorefrontPage;
use App\Models\CustomerVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontHome::class)->name('storefront.home');
Route::get('/cauta', StorefrontSearch::class)->name('storefront.search');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('storefront.sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('storefront.robots');
Route::get('/categorie/{category}', StorefrontCategory::class)->where('category', '.+')->name('storefront.category');
Route::get('/produs/{product:slug}', StorefrontProduct::class)->name('storefront.product');
Route::get('/colectii', StorefrontCollections::class)->name('storefront.collections');
Route::get('/colectii/{slug}', StorefrontCollection::class)->where('slug', '[a-z0-9-]+')->name('storefront.collection');
Route::get('/ghiduri', StorefrontGuides::class)->name('storefront.guides');
Route::get('/ghiduri/{slug}', StorefrontGuide::class)->where('slug', '[a-z0-9-]+')->name('storefront.guide');
Route::get('/service-auto', StorefrontServices::class)->name('storefront.services');
Route::get('/servicii-auto', StorefrontServiceTypes::class)->name('storefront.service-types');
Route::get('/servicii-auto/{slug}', StorefrontServiceType::class)->where('slug', '[a-z0-9-]+')->name('storefront.service-type');
Route::get('/programare/{token}', StorefrontAppointment::class)->whereUuid('token')->name('storefront.appointment');
// One segment is a city; two are a city and a workshop. A workshop slug still arriving on the
// one-segment form is a link made before the city was in the URL, and the city page redirects
// it rather than letting an old bookmark 404.
Route::get('/service-auto/{city}', StorefrontServiceCity::class)->where('city', '[a-z0-9-]+')->name('storefront.services.city');
Route::get('/service-auto/{city}/{slug}', StorefrontService::class)->where(['city' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'])->name('storefront.service');
Route::get('/service-auto/{city}/{slug}/catre/{type}', ServiceShopLinkController::class)
    ->where(['city' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+', 'type' => 'website|directions|waze'])
    ->name('storefront.service.link');
Route::get('/contact', StorefrontContact::class)->name('storefront.contact');
Route::get('/cos', StorefrontCart::class)->name('storefront.cart');
Route::get('/finalizare', StorefrontCheckout::class)->name('storefront.checkout');
// Constrained to a UUID so a malformed link never reaches the query: checkout_token is a uuid
// column, and PostgreSQL raises on a non-UUID comparison rather than simply matching nothing.
Route::get('/comanda/{token}', StorefrontOrder::class)->whereUuid('token')->name('storefront.order');
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
        // Garage pages were first addressed by number. Old links and bookmarks still reach the
        // car, scoped to the signed-in customer exactly like the page itself. Registered first:
        // the readable address below would otherwise take the digits as a name.
        Route::get('/garaj/{vehicleId}', function (int $vehicleId) {
            $vehicle = CustomerVehicle::query()->where('user_id', auth()->id())->findOrFail($vehicleId);

            return redirect()->route('customer.garage.vehicle', $vehicle->routeSlug(), 301);
        })->whereNumber('vehicleId');
        Route::get('/garaj/{slug}', CustomerVehicleDetail::class)->where('slug', '[a-z0-9-]+')->name('garage.vehicle');
        Route::get('/favorite', CustomerFavourites::class)->name('favourites');
        Route::get('/comenzi', CustomerOrders::class)->name('orders');
        Route::get('/programari', CustomerAppointments::class)->name('appointments');
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
    Route::get('/collections', CollectionsIndex::class)->name('collections.index');
    Route::get('/collections/create', CollectionEditor::class)->name('collections.create');
    Route::get('/collections/{collection}/edit', CollectionEditor::class)->name('collections.edit');
    Route::get('/reviews', ReviewsIndex::class)->name('reviews.index');
    Route::get('/reviews/create', ReviewEditor::class)->name('reviews.create');
    Route::get('/reviews/{review}/edit', ReviewEditor::class)->name('reviews.edit');
    Route::get('/attributes', AttributesIndex::class)->name('attributes.index');
    Route::get('/brands', BrandsIndex::class)->name('brands.index');
    Route::get('/media', MediaLibrary::class)->name('media.index');
    Route::get('/pages', PagesIndex::class)->name('pages.index');
    Route::get('/service-shops', ServiceShopsIndex::class)->name('service-shops.index');
    Route::get('/service-shops/create', ServiceShopEditor::class)->name('service-shops.create');
    Route::get('/service-shops/{shop}/edit', ServiceShopEditor::class)->name('service-shops.edit');
    Route::get('/service-catalog', ServiceCatalogIndex::class)->name('service-catalog');
    Route::get('/service-appointments', ServiceAppointmentsIndex::class)->name('service-appointments');
    Route::get('/workshops', WorkshopsIndex::class)->name('workshops.index');
    Route::get('/workshops/review', WorkshopReviewQueue::class)->name('workshops.review');
    Route::get('/workshops/sources', WorkshopSourcesIndex::class)->name('workshops.sources');
    Route::get('/workshops/{workshop}', WorkshopDetail::class)->whereNumber('workshop')->name('workshops.show');
    Route::get('/workshop-records/{record}', WorkshopSourceRecordDetail::class)->whereNumber('record')->name('workshops.records.show');
    // Internal JSON for back-office tools, behind the same admin login. Raw source content
    // (HTML, full RAR payloads) is never part of it.
    Route::get('/api/workshops', [WorkshopApiController::class, 'index'])->name('api.workshops.index');
    Route::get('/api/workshops/{workshop}', [WorkshopApiController::class, 'show'])->whereNumber('workshop')->name('api.workshops.show');
    Route::get('/api/workshop-services', [WorkshopApiController::class, 'services'])->name('api.workshop-services');
    Route::get('/articles', ArticlesIndex::class)->name('articles.index');
    Route::get('/articles/create', ArticleEditor::class)->name('articles.create');
    Route::get('/articles/{article}/edit', ArticleEditor::class)->name('articles.edit');
    Route::get('/articles/{article}/blocks', ArticleBlockEditor::class)->name('articles.blocks');
    Route::get('/vehicles', VehiclesIndex::class)->name('vehicles.index');
    Route::get('/suppliers', SuppliersIndex::class)->name('suppliers.index');
    Route::get('/suppliers/create', SupplierEditor::class)->name('suppliers.create');
    Route::get('/suppliers/{supplier}/edit', SupplierEditor::class)->name('suppliers.edit');
    Route::get('/supplier-syncs', SyncRunsIndex::class)->name('suppliers.sync-runs');
    Route::get('/supplier-offers', SupplierOffersIndex::class)->name('suppliers.offers');
    Route::get('/pricing-rules', PricingRulesIndex::class)->name('pricing.rules');
    Route::get('/price-changes', PriceChangesIndex::class)->name('pricing.changes');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/returns', ReturnsIndex::class)->name('returns.index');
    Route::get('/customers', CustomersIndex::class)->name('customers.index');
    Route::get('/orders/{order}', OrderEditor::class)->name('orders.edit');
    Route::get('/settings', SettingsPage::class)->name('settings');
    // Kept so bookmarks and older links still land somewhere sensible.
    Route::redirect('/commerce-settings', '/admin/settings?tab=commerce')->name('commerce.settings');
});

// Registered last on purpose: legal and informational pages get clean root URLs, and every
// specific route above is matched before this one is reached.
Route::get('/{slug}', StorefrontPage::class)->where('slug', '[a-z0-9-]+')->name('storefront.page');

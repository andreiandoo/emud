<?php

use App\Livewire\Admin\Catalog\AttributesIndex;
use App\Livewire\Admin\Catalog\CategoriesIndex;
use App\Livewire\Admin\Catalog\ProductsIndex;
use App\Livewire\Admin\Catalog\ProductEditor;
use App\Livewire\Admin\Catalog\BrandsIndex;
use App\Livewire\Admin\CommerceSettings;
use App\Livewire\Admin\Content\ArticleEditor;
use App\Livewire\Admin\Content\ArticlesIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MediaLibrary;
use App\Livewire\Admin\OrderEditor;
use App\Livewire\Admin\OrdersIndex;
use App\Livewire\Admin\Suppliers\SuppliersIndex;
use App\Livewire\Admin\Suppliers\SyncRunsIndex;
use App\Livewire\Admin\VehiclesIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::view('/admin/login', 'auth.admin-login')->name('login');
    Route::post('/admin/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Datele de autentificare nu sunt corecte.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    })->name('admin.login.store');
});

Route::post('/admin/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('admin.logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
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
    Route::get('/supplier-syncs', SyncRunsIndex::class)->name('suppliers.sync-runs');
    Route::get('/orders', OrdersIndex::class)->name('orders.index');
    Route::get('/orders/{order}', OrderEditor::class)->name('orders.edit');
    Route::get('/commerce-settings', CommerceSettings::class)->name('commerce.settings');
});

<?php

namespace App\Observers;

use App\Models\Category;
use App\Storefront\CategoryMenu;

/**
 * The menu renders on every page, so a stale copy is wrong everywhere at once. Clearing on any
 * category write is cheap next to serving a menu that no longer matches the catalogue.
 */
class CategoryMenuObserver
{
    public function saved(Category $category): void
    {
        CategoryMenu::forget();
    }

    public function deleted(Category $category): void
    {
        CategoryMenu::forget();
    }
}

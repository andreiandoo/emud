<?php

namespace App\Listeners;

use App\Models\User;
use App\Storefront\CartManager;
use Illuminate\Auth\Events\Login;

/**
 * A customer who fills a basket and only then signs in must not find it emptied. Merging on the
 * Login event covers every way in — the sign-in form, registration, a remembered session — so
 * no future entry point can forget to do it.
 */
class MergeGuestCartOnLogin
{
    public function __construct(private CartManager $carts) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->carts->mergeInto($event->user);
        }
    }
}

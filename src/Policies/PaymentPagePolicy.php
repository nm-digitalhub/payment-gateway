<?php

namespace NMDigitalHub\PaymentGateway\Policies;

use App\Models\User;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any payment pages.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'editor']);
    }

    /**
     * Determine whether the user can view the payment page.
     */
    public function view(User $user, PaymentPage $paymentPage): bool
    {
        // Admin and managers can view all pages
        if ($user->hasRole(['admin', 'manager'])) {
            return true;
        }

        // Editors can view pages they created or published pages
        if ($user->hasRole('editor')) {
            return $paymentPage->created_by === $user->id || 
                   $paymentPage->is_active;
        }

        return false;
    }

    /**
     * Determine whether the user can create payment pages.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'editor']);
    }

    /**
     * Determine whether the user can update the payment page.
     */
    public function update(User $user, PaymentPage $paymentPage): bool
    {
        // Admins can update any page
        if ($user->hasRole('admin')) {
            return true;
        }

        // Managers can update pages in their domain
        if ($user->hasRole('manager')) {
            return $this->isInUserDomain($user, $paymentPage);
        }

        // Editors can update their own pages if not locked
        if ($user->hasRole('editor')) {
            return $paymentPage->created_by === $user->id && 
                   !$paymentPage->is_locked;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the payment page.
     */
    public function delete(User $user, PaymentPage $paymentPage): bool
    {
        // Only admins can delete pages
        if ($user->hasRole('admin')) {
            return true;
        }

        // Managers can delete inactive pages in their domain
        if ($user->hasRole('manager')) {
            return !$paymentPage->is_active && 
                   $this->isInUserDomain($user, $paymentPage);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the payment page.
     */
    public function restore(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole(['admin', 'manager']);
    }

    /**
     * Determine whether the user can permanently delete the payment page.
     */
    public function forceDelete(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can publish the payment page.
     */
    public function publish(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole(['admin', 'manager']);
    }

    /**
     * Determine whether the user can unpublish the payment page.
     */
    public function unpublish(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole(['admin', 'manager']);
    }

    /**
     * Determine whether the user can duplicate the payment page.
     */
    public function duplicate(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole(['admin', 'manager', 'editor']) && 
               $this->create($user);
    }

    /**
     * Determine whether the user can view analytics for the payment page.
     */
    public function viewAnalytics(User $user, PaymentPage $paymentPage): bool
    {
        return $user->hasRole(['admin', 'manager']) || 
               ($user->hasRole('editor') && $paymentPage->created_by === $user->id);
    }

    /**
     * Check if payment page is in user's domain
     */
    private function isInUserDomain(User $user, PaymentPage $paymentPage): bool
    {
        // If user has department/domain restrictions
        if ($user->department) {
            return $paymentPage->department === $user->department;
        }

        // If user has service type restrictions
        if ($user->allowed_service_types) {
            return in_array($paymentPage->service_type, $user->allowed_service_types);
        }

        return true; // No restrictions
    }
}
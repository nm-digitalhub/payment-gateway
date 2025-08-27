<?php

namespace NMDigitalHub\PaymentGateway\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentTransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any payment transactions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'support']);
    }

    /**
     * Determine whether the user can view the payment transaction.
     */
    public function view(User $user, $transaction = null): bool
    {
        // Admin and managers can view all transactions
        if ($user->hasRole(['admin', 'manager'])) {
            return true;
        }

        // Support can view transactions for troubleshooting
        if ($user->hasRole('support')) {
            return true;
        }

        // Clients can only view their own transactions
        if ($transaction && $user->hasRole('client')) {
            return $transaction->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create payment transactions.
     */
    public function create(User $user): bool
    {
        // Only admins and managers can manually create transactions
        return $user->hasRole(['admin', 'manager']);
    }

    /**
     * Determine whether the user can update the payment transaction.
     */
    public function update(User $user, $transaction = null): bool
    {
        // Only admins can update transaction details
        if ($user->hasRole('admin')) {
            return true;
        }

        // Managers can update non-completed transactions
        if ($user->hasRole('manager') && $transaction) {
            return !in_array($transaction->status, ['completed', 'refunded']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the payment transaction.
     */
    public function delete(User $user, $transaction = null): bool
    {
        // Only super admin can delete transactions (for compliance)
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can restore the payment transaction.
     */
    public function restore(User $user, $transaction = null): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can permanently delete the payment transaction.
     */
    public function forceDelete(User $user, $transaction = null): bool
    {
        return false; // Never allow permanent deletion for compliance
    }

    /**
     * Determine whether the user can refund the payment transaction.
     */
    public function refund(User $user, $transaction = null): bool
    {
        if (!$user->hasRole(['admin', 'manager'])) {
            return false;
        }

        // Can only refund completed transactions
        if ($transaction) {
            return $transaction->status === 'completed' && 
                   !$transaction->refunded_at;
        }

        return true;
    }

    /**
     * Determine whether the user can export payment transactions.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'accountant']);
    }

    /**
     * Determine whether the user can view sensitive payment data.
     */
    public function viewSensitiveData(User $user): bool
    {
        return $user->hasRole(['admin']) && $user->has_2fa_enabled;
    }
}
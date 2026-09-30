<?php

namespace App\Actions\Customers;

use App\Enums\AccountStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Activate / suspend a CUSTOMER account (never an admin — this action is
 * only reachable from the customers pages). Suspending keeps the customer
 * and their orders, blocks login (AttemptLogin), and ends active sessions
 * and "remember me" cookies; EnsureAccountIsActive also logs them out on
 * their next request.
 */
class SetCustomerStatus
{
    public function handle(User $customer, AccountStatus $status): void
    {
        if (! $customer->isCustomer()) {
            throw new InvalidArgumentException('يمكن تغيير حالة حسابات العملاء فقط من هذه الصفحة.');
        }

        if ($customer->status === $status) {
            return;
        }

        $old = $customer->status;

        DB::transaction(function () use ($customer, $status, $old) {
            $customer->forceFill(['status' => $status])->save();

            if ($status === AccountStatus::Suspended) {
                $customer->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $customer->id)->delete();
                }
            }

            AuditLog::record($customer, 'status_changed', ['status' => $old->value], ['status' => $status->value]);
        });
    }
}

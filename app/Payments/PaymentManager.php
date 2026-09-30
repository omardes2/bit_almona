<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Payments\Contracts\PaymentProvider;

class PaymentManager
{
    /**
     * Methods a customer may choose now: enabled in config/payments.php, a
     * known PaymentMethod case, and backed by a real provider class.
     *
     * @return list<PaymentMethod>
     */
    public function enabledMethods(): array
    {
        $methods = [];

        foreach ((array) config('payments.methods', []) as $key => $settings) {
            $method = PaymentMethod::tryFrom((string) $key);

            if ($method && ($settings['enabled'] ?? false) && $this->providerClass($method)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    public function isEnabled(PaymentMethod $method): bool
    {
        return in_array($method, $this->enabledMethods(), true);
    }

    /**
     * @return list<PaymentOption>
     */
    public function checkoutOptions(): array
    {
        return array_map(fn (PaymentMethod $method) => new PaymentOption(
            method: $method,
            label: $method->label(),
            description: $method->description(),
            icon: config("payments.methods.{$method->value}.icon"),
            online: $this->provider($method)->isOnline(),
        ), $this->enabledMethods());
    }

    /**
     * Provider for a NEW payment: the method must be enabled right now.
     */
    public function provider(PaymentMethod $method): PaymentProvider
    {
        if (! $this->isEnabled($method)) {
            throw new PaymentException('طريقة الدفع غير متاحة.');
        }

        return $this->resolve($method);
    }

    /**
     * Provider for an EXISTING payment (cancel/refund/verify). Works even if
     * the method was disabled after the order was placed.
     */
    public function providerForExisting(PaymentMethod $method): PaymentProvider
    {
        return $this->resolve($method);
    }

    /**
     * Where to send the customer after the order is committed: the gateway's
     * hosted page for online methods, or null (go to the confirmation page).
     * Only absolute https URLs are accepted, so a misbehaving provider cannot
     * redirect to an arbitrary scheme.
     */
    public function redirectUrlFor(Order $order): ?string
    {
        $payment = $order->payments()->latest('id')->first();

        if (! $payment) {
            return null;
        }

        $provider = $this->providerForExisting($payment->method);
        $url = $provider->isOnline() ? $provider->redirectUrl($payment) : null;

        return $url && str_starts_with($url, 'https://') ? $url : null;
    }

    private function resolve(PaymentMethod $method): PaymentProvider
    {
        $class = $this->providerClass($method);

        if (! $class) {
            throw new PaymentException('طريقة الدفع غير متاحة.');
        }

        return app($class);
    }

    /** @return class-string<PaymentProvider>|null */
    private function providerClass(PaymentMethod $method): ?string
    {
        $class = config("payments.methods.{$method->value}.provider");

        return is_string($class) && class_exists($class) && is_subclass_of($class, PaymentProvider::class) ? $class : null;
    }
}

<?php

namespace App\Services\System;

use App\Messaging\MessagingManager;
use App\Otp\OtpService;
use App\Payments\PaymentManager;

/**
 * Read-only status of external integrations for /admin/settings/integrations.
 * Only driver names and on/off states are exposed — credentials stay in .env
 * and are never read here.
 */
class IntegrationStatus
{
    public const CONFIGURED = 'configured';

    public const DEVELOPMENT = 'development';

    public const NOT_CONFIGURED = 'not_configured';

    public function __construct(
        private readonly MessagingManager $messaging,
        private readonly OtpService $otp,
        private readonly PaymentManager $payments,
    ) {}

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function all(): array
    {
        return [
            $this->channel('whatsapp', 'واتساب (رسائل الزبائن)'),
            $this->channel('sms', 'رسائل SMS'),
            $this->otp(),
            $this->onlinePayments(),
            $this->queue(),
        ];
    }

    /** @return array{key: string, label: string, status: string, detail: string} */
    private function channel(string $channel, string $label): array
    {
        $driver = config("messaging.channels.{$channel}.driver");

        return [
            'key' => $channel,
            'label' => $label,
            'status' => $this->driverStatus($driver),
            'detail' => match (true) {
                blank($driver) => 'غير مربوط بمزود. لا تُرسل أي رسائل.',
                $driver === 'log' => 'وضع التطوير: الرسائل تُكتب في السجل فقط.',
                default => "المزود: {$driver}",
            },
        ];
    }

    /** @return array{key: string, label: string, status: string, detail: string} */
    private function otp(): array
    {
        $driver = config('otp.driver');

        return [
            'key' => 'otp',
            'label' => 'رموز التحقق OTP (استعادة كلمة المرور وتغيير الجوال)',
            'status' => $this->otp->isAvailable() ? $this->driverStatus($driver) : self::NOT_CONFIGURED,
            'detail' => match (true) {
                ! $this->otp->isAvailable() => 'غير مفعّلة: صفحات استعادة كلمة المرور وتغيير الجوال تعرض رسالة «غير مفعلة حاليًا».',
                $driver === 'log' => 'وضع التطوير: الرمز يُكتب في السجل فقط.',
                default => "المزود: {$driver}",
            },
        ];
    }

    /** @return array{key: string, label: string, status: string, detail: string} */
    private function onlinePayments(): array
    {
        $online = array_filter($this->payments->enabledMethods(), fn ($method) => $this->payments->provider($method)->isOnline());

        return [
            'key' => 'payments',
            'label' => 'الدفع الإلكتروني',
            'status' => $online === [] ? self::NOT_CONFIGURED : self::CONFIGURED,
            'detail' => $online === []
                ? 'غير مربوط. المتاح حاليًا: '.implode('، ', array_map(fn ($m) => $m->label(), $this->payments->enabledMethods())).'.'
                : 'مفعّل: '.implode('، ', array_map(fn ($m) => $m->label(), $online)),
        ];
    }

    /** @return array{key: string, label: string, status: string, detail: string} */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        return [
            'key' => 'queue',
            'label' => 'طابور المهام (Queue)',
            'status' => $driver === 'sync' ? self::DEVELOPMENT : self::CONFIGURED,
            'detail' => $driver === 'sync'
                ? 'sync: المهام تُنفّذ فورًا داخل الطلب (بدون عامل).'
                : "الاتصال: {$connection} — يحتاج عامل queue:work يعمل دائمًا.",
        ];
    }

    private function driverStatus(?string $driver): string
    {
        return match (true) {
            blank($driver) => self::NOT_CONFIGURED,
            $driver === 'log' => self::DEVELOPMENT,
            default => self::CONFIGURED,
        };
    }
}

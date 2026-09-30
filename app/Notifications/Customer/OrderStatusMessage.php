<?php

namespace App\Notifications\Customer;

use App\Enums\OrderStatus;
use App\Messaging\Channels\SmsChannel;
use App\Messaging\Channels\WhatsAppChannel;
use App\Messaging\OutgoingMessage;
use App\Models\Order;
use App\Support\Money;
use App\Support\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Order update for the customer on external channels. Always queued (and
 * only after commit) so a slow provider never delays checkout or admin work.
 */
class OrderStatusMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $channels  keys from MessagingManager::channelsFor()
     */
    public function __construct(public Order $order, public OrderStatus $status, public array $channels)
    {
        $this->afterCommit();
        $this->onQueue(config('messaging.queue'));
    }

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        return array_values(array_filter(array_map(fn (string $channel) => match ($channel) {
            'whatsapp' => WhatsAppChannel::class,
            'sms' => SmsChannel::class,
            'email' => 'mail',
            default => null,
        }, $this->channels)));
    }

    public function text(): string
    {
        $number = $this->order->order_number;

        return match ($this->status) {
            OrderStatus::New => 'شكرًا لطلبك من '.Store::name()."! رقم طلبك {$number} بقيمة ".Money::format($this->order->total).'.',
            OrderStatus::Confirmed => "تم تأكيد طلبك {$number}.",
            OrderStatus::Preparing => "طلبك {$number} قيد التجهيز الآن.",
            OrderStatus::OutForDelivery => "طلبك {$number} خرج للتوصيل، الرجاء تجهيز مبلغ ".Money::format($this->order->total).'.',
            OrderStatus::Delivered => "تم تسليم طلبك {$number}. شكرًا لتسوقك من ".Store::name().'.',
            OrderStatus::Cancelled => "تم إلغاء طلبك {$number}.",
        };
    }

    public function toWhatsApp(object $notifiable): OutgoingMessage
    {
        return new OutgoingMessage($this->text(), 'order_'.$this->status->value, ['order_number' => $this->order->order_number]);
    }

    public function toSms(object $notifiable): OutgoingMessage
    {
        return new OutgoingMessage($this->text());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('طلبك '.$this->order->order_number)->line($this->text());
    }
}

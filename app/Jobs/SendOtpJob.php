<?php

namespace App\Jobs;

use App\Contracts\SmsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * OTP SMS yuborish. Payload shifrlangan (ShouldBeEncrypted) — kod queue'da ochiq saqlanmaydi.
 */
class SendOtpJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 20];

    public function __construct(public string $phone, public string $code)
    {
        $this->onQueue('high');
    }

    public function handle(SmsProvider $sms): void
    {
        $sms->send($this->phone, str_replace(':code', $this->code, config('sms.otp_template')));
    }
}

<?php

namespace App\Services\Social;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Admin va tizim harakatlari jurnali (security va debugging uchun). */
class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token', 'code_hash', 'token'];

    public function log(string $action, ?Model $target = null, array $old = [], array $new = [], ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::query()->create([
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }

    private function clean(array $values): array
    {
        $values = array_diff_key($values, array_flip(self::HIDDEN));

        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $values);
    }
}

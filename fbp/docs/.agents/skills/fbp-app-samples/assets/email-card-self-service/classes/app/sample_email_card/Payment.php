<?php
/** Host billing integration. No payment runs unless explicitly enabled and connected. */
class SampleEmailCardPayment {
    function initialEnabled(): bool {return false;}
    function ready(): bool {return false;}
    /**
     * Override together with initialEnabled()/ready() when full initial payment is requested.
     * Reserve durably before external calls; reuse provider idempotency; return
     * ['state'=>'success'|'failed'|'unknown'|'processing'] from a stored attempt.
     * Changes to an existing card never call this method. Do not implement a blind retry.
     */
    function chargeInitial(Controller $ctl,array $member,string $month,string $operation): array {
        throw new DomainException('初回決済の連携設定を管理者に確認してください。');
    }
}

<?php
require_once __DIR__.'/../../lib/ManagedLogin.php';
final class FrameworkManagedLoginPolicy implements DspInterface {
    function __construct(private Controller $ctl,private string $channel){}
    private function check(): void {if(!ManagedLoginScope::active($this->ctl))throw new DspException('managed_login','credential_storage_denied');}
    function authorizeInsert(array $newRow): void {$this->check();}
    function authorizeRead(array $request): void {$this->check();}
    function inspectRead(array $row): DspReadDecision {$this->check();return new DspReadDecision(true,null);}
    function authorizeUpdate(array $before,array $after,array $submittedFields): void {$this->check();}
    function authorizeDelete(array $before): void {$this->check();}
}

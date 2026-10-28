<?php
namespace App\Services\Mao;
/** No-op in application use; a local test process may bind an IPC barrier observer. */
class WorkflowLockObserver {
    public function planned(array $plan): void {}
    public function acquired(string $table,array $ids): void {}
}

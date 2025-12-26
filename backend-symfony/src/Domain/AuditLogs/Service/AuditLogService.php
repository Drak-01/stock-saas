<?php

namespace App\Domain\AuditLogs\Service;

use App\Domain\AuditLogs\AuditLogs;
use App\Domain\AuditLogs\AuditLogsRepository;
use App\Domain\TenantUsers\TenantUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogService
{
    public function __construct(
        private AuditLogsRepository $auditLogsRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    public function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?TenantUsers $user = null
    ): AuditLogs {
        $log = AuditLogs::create(
            $action,
            $entityType,
            $entityId,
            $user ?? $this->getCurrentUser(),
            $oldValues,
            $newValues,
            $this->getClientIp(),
            $this->getUserAgent()
        );

        $this->auditLogsRepository->save($log, true);

        return $log;
    }

    public function logCreate(string $entityType, string $entityId, array $newValues, ?TenantUsers $user = null): void
    {
        $this->log('create', $entityType, $entityId, null, $newValues, $user);
    }

    public function logUpdate(string $entityType, string $entityId, array $oldValues, array $newValues, ?TenantUsers $user = null): void
    {
        $this->log('update', $entityType, $entityId, $oldValues, $newValues, $user);
    }

    public function logDelete(string $entityType, string $entityId, array $oldValues, ?TenantUsers $user = null): void
    {
        $this->log('delete', $entityType, $entityId, $oldValues, null, $user);
    }

    public function logLogin(?TenantUsers $user = null): void
    {
        $this->log('login', 'user', $user?->getUuid(), null, null, $user);
    }

    public function logLogout(?TenantUsers $user = null): void
    {
        $this->log('logout', 'user', $user?->getUuid(), null, null, $user);
    }

    // Méthodes spécifiques pour les entités

    public function logProductionOrderCreate($productionOrder): void
    {
        $this->logCreate(
            'production_order',
            $productionOrder->getUuid(),
            $productionOrder->toArray()
        );
    }

    public function logProductionOrderUpdate($productionOrder, array $oldValues, array $newValues): void
    {
        $this->logUpdate(
            'production_order',
            $productionOrder->getUuid(),
            $oldValues,
            $newValues
        );
    }

    public function logProductionOrderReserve($productionOrder): void
    {
        $this->log(
            'reserve',
            'production_order',
            $productionOrder->getUuid(),
            null,
            ['status' => 'reserved']
        );
    }

    public function logProductionOrderStart($productionOrder): void
    {
        $this->log(
            'start',
            'production_order',
            $productionOrder->getUuid(),
            null,
            ['status' => 'in_progress', 'startDate' => date('Y-m-d H:i:s')]
        );
    }

    public function logProductionRecord($productionOrder, string $quantity): void
    {
        $this->log(
            'record_production',
            'production_order',
            $productionOrder->getUuid(),
            ['quantityProduced' => $productionOrder->getQuantityProduced()],
            ['quantityProduced' => bcadd($productionOrder->getQuantityProduced(), $quantity, 6)]
        );
    }

    public function logProductionOrderComplete($productionOrder): void
    {
        $this->log(
            'complete',
            'production_order',
            $productionOrder->getUuid(),
            null,
            ['status' => 'completed', 'completionDate' => date('Y-m-d H:i:s')]
        );
    }

    public function logProductionOrderCancel($productionOrder, ?string $reason = null): void
    {
        $data = ['status' => 'cancelled'];
        if ($reason) {
            $data['cancelReason'] = $reason;
        }

        $this->log(
            'cancel',
            'production_order',
            $productionOrder->getUuid(),
            null,
            $data
        );
    }

    public function logProductionOrderClose($productionOrder): void
    {
        $this->log(
            'close',
            'production_order',
            $productionOrder->getUuid(),
            null,
            ['status' => 'closed']
        );
    }

    public function getAuditLogsByEntity(string $entityType, string $entityId): array
    {
        return $this->auditLogsRepository->findByEntity($entityType, $entityId);
    }

    public function getUserActivity(TenantUsers $user, int $days = 30): array
    {
        return $this->auditLogsRepository->findUserActivity($user, $days);
    }

    public function getRecentActivity(int $limit = 100): array
    {
        return $this->auditLogsRepository->findRecent($limit);
    }

    public function getStatistics(\DateTimeInterface $startDate = null, \DateTimeInterface $endDate = null): array
    {
        return $this->auditLogsRepository->getStatistics($startDate, $endDate);
    }

    public function getActivityByHour(\DateTimeInterface $date): array
    {
        return $this->auditLogsRepository->getActivityByHour($date);
    }

    public function searchAuditLogs(string $searchTerm, int $limit = 50): array
    {
        return $this->auditLogsRepository->search($searchTerm, $limit);
    }

    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        return $this->auditLogsRepository->cleanupOldLogs($daysToKeep);
    }

    private function getCurrentUser(): ?TenantUsers
    {
        $user = $this->security->getUser();
        return $user instanceof TenantUsers ? $user : null;
    }

    private function getClientIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request ? $request->getClientIp() : null;
    }

    private function getUserAgent(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        return $request ? $request->headers->get('User-Agent') : null;
    }
}

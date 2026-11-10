<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findUserOrders(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')
            ->addSelect('i')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllWithItems(): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')
            ->addSelect('i')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDashboardStats(): array
    {
        // 1. Total revenue from non-cancelled orders
        $revenue = (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->andWhere('o.status != :cancelled')
            ->setParameter('cancelled', 'Cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        // 2. Total orders count
        $totalOrders = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 3. Pending orders count
        $pendingOrders = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->setParameter('status', 'Pending')
            ->getQuery()
            ->getSingleScalarResult();

        // 4. Completed orders count
        $completedOrders = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->setParameter('status', 'Completed')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'revenue' => $revenue,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'completedOrders' => $completedOrders,
        ];
    }

    /**
     * Search orders by customer name/email and optionally filter by status.
     */
    public function searchOrders(string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')
            ->addSelect('i')
            ->orderBy('o.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('o.customerName LIKE :search OR o.customerEmail LIKE :search OR o.orderNumber LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== '') {
            $qb->andWhere('o.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}

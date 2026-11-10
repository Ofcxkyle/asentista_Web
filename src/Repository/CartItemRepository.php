<?php

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * Eagerly joins product to eliminate N+1 queries
     */
    public function findCartItems(?User $user, ?string $sessionId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.product', 'p')
            ->addSelect('p');

        if ($user !== null) {
            $qb->andWhere('c.user = :user')
               ->setParameter('user', $user);
        } elseif ($sessionId !== null) {
            $qb->andWhere('c.sessionId = :sessionId')
               ->setParameter('sessionId', $sessionId);
        } else {
            return [];
        }

        return $qb->orderBy('c.id', 'ASC')->getQuery()->getResult();
    }

    public function findExistingItem(?User $user, ?string $sessionId, Product $product): ?CartItem
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.product = :product')
            ->setParameter('product', $product);

        if ($user !== null) {
            $qb->andWhere('c.user = :user')
               ->setParameter('user', $user);
        } else {
            $qb->andWhere('c.sessionId = :sessionId')
               ->setParameter('sessionId', $sessionId);
        }

        return $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function countCartItems(?User $user, ?string $sessionId): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.quantity), 0)');

        if ($user !== null) {
            $qb->andWhere('c.user = :user')
               ->setParameter('user', $user);
        } elseif ($sessionId !== null) {
            $qb->andWhere('c.sessionId = :sessionId')
               ->setParameter('sessionId', $sessionId);
        } else {
            return 0;
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function clearCart(?User $user, ?string $sessionId): void
    {
        $qb = $this->createQueryBuilder('c')->delete();

        if ($user !== null) {
            $qb->andWhere('c.user = :user')->setParameter('user', $user);
        } elseif ($sessionId !== null) {
            $qb->andWhere('c.sessionId = :sessionId')->setParameter('sessionId', $sessionId);
        } else {
            return;
        }

        $qb->getQuery()->execute();
    }
}

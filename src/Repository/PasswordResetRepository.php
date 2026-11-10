<?php

namespace App\Repository;

use App\Entity\PasswordReset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordReset>
 */
class PasswordResetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordReset::class);
    }

    public function findValidToken(string $tokenHash): ?PasswordReset
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->andWhere('p.tokenHash = :tokenHash')
            ->andWhere('p.status = :status')
            ->andWhere('p.expiresAt > :currentTime')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('status', 'pending')
            ->setParameter('currentTime', time())
            ->getQuery()
            ->getOneOrNullResult();
    }
}

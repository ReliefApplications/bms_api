<?php

namespace VoucherBundle\Repository;

/**
 * BookletRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BookletRepository extends \Doctrine\ORM\EntityRepository
{
    public function getActiveBooklets($countryISO3)
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->where('b.status != :status')
                ->andWhere('b.countryISO3 = :country')
                ->setParameter('country', $countryISO3)
                ->setParameter('status', 3);

        return $q->getQuery()->getResult();
    }

    // We dont care about this function and probably we should remove it from controller, test, service and repo (it has nothing related in the front)
    public function getProtectedBooklets()
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->where('b.password IS NOT NULL');
        
        
        return $q->getQuery()->getResult();
    }

        public function getActiveBookletsByDistributionBeneficiary(int $distributionBeneficiaryId) {
        $qb = $this->createQueryBuilder('b');
        
        $qb->andWhere('db.id = :id')
                ->setParameter('id', $distributionBeneficiaryId)
                ->leftJoin('b.distribution_beneficiary', 'db')
                ->andWhere('b.status != :status')
                    ->setParameter('status', 3);
        
        return $qb->getQuery()->getResult();
    }
}

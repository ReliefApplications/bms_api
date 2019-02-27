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
    public function getActiveBooklets() 
    {
        $qb = $this->createQueryBuilder('b');
        $q = $qb->where('b.status != :status')
            ->setParameter('status', 3);
        
        return $q->getQuery()->getResult();
    }
}

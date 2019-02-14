<?php

namespace VoucherBundle\Repository;

use Doctrine\ORM\QueryBuilder;

/**
 * VendorRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class VendorRepository extends \Doctrine\ORM\EntityRepository
{
    public function getVendorByUser($user) {

        return $user ?
            $this->createQueryBuilder('vendor')
            ->where('vendor.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getResult() :
            null;
    }
}

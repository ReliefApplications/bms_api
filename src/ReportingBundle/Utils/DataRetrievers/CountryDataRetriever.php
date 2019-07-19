<?php

namespace ReportingBundle\Utils\DataRetrievers;

use Doctrine\ORM\EntityManager;

use Doctrine\ORM\QueryBuilder;
use ReportingBundle\Entity\ReportingCountry;

/**
 * Class CountryDataRetrievers
 * @package ReportingBundle\Utils\DataRetrievers
 */
class CountryDataRetriever extends AbstractDataRetriever
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * CountryDataRetrievers constructor.
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Use to make join and where in DQL
     * Use in all project data retrievers
     * @param string $code
     * @param array $filters
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getReportingValue(string $code, array $filters)
    {
        $qb = $this->em->createQueryBuilder()
                        ->from(ReportingCountry::class, 'rc')
                        ->leftjoin('rc.value', 'rv')
                        ->leftjoin('rc.indicator', 'ri')
                        ->where('ri.code = :code')
                        ->setParameter('code', $code)
                        ->andWhere('rc.country = :country')
                        ->setParameter('country', $filters['country']);

        return $qb;
    }

    /**
     * switch case to use the right select
     * each case is the name of the function to execute
     *
     * Indicators with the same 'select' statement are grouped in the same case
     * @param $qb
     * @return QueryBuilder
     */
    public function conditionSelect($qb)
    {
        $qb = $qb->select('rc.country AS name')
                 ->groupBy('name');

        return $qb;
    }

    /**
     * Get total of household by country
     * @param array $filters
     * @return mixed
     */
    public function BMSCountryTH(array $filters)
    {
        $qb = $this->getReportingValue('BMSCountryTH', $filters);
        $qb = $this->conditionSelect($qb);
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get total of active projects by country
     * @param array $filters
     * @return mixed
     */
    public function BMSCountryAP(array $filters)
    {
        $qb = $this->getReportingValue('BMSCountryAP', $filters);
        $qb = $this->conditionSelect($qb);
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get total of enrolled beneficiaries by country
     * @param array $filters
     * @return mixed
     */
    public function BMSCountryEB(array $filters)
    {
        $qb = $this->getReportingValue('BMSCountryEB', $filters);
        $qb = $this->conditionSelect($qb);
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get total number of distributions by country
     * @param array $filters
     * @return mixed
     */
    public function BMSCountryTND(array $filters)
    {
        $qb = $this->getReportingValue('BMSCountryTND', $filters);
        $qb = $this->conditionSelect($qb);
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get total transactions completed
     * @param array $filters
     * @return mixed
     */
    public function BMSCountryTTC(array $filters)
    {
        $qb = $this->getReportingValue('BMSCountryTTC', $filters);
        $qb = $this->conditionSelect($qb);
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }
}

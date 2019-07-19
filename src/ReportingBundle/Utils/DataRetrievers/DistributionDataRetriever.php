<?php

namespace ReportingBundle\Utils\DataRetrievers;

use Doctrine\ORM\EntityManager;

use function GuzzleHttp\Psr7\str;
use ReportingBundle\Entity\ReportingDistribution;
use \ProjectBundle\Entity\Project;
use \DistributionBundle\Entity\DistributionData;

/**
 * Class DistributionDataRetrievers
 * @package ReportingBundle\Utils\DataRetrievers
 */
class DistributionDataRetriever extends AbstractDataRetriever
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var ProjectDataRetriever
     */
    private $project;

    /**
     * DistributionDataRetrievers constructor.
     * @param EntityManager $em
     * @param ProjectDataRetriever $project
     */
    public function __construct(EntityManager $em, ProjectDataRetriever $project)
    {
        $this->em = $em;
        $this->project = $project;
    }

    /**
     * Use to make join and where in DQL
     * Use in all distribution data retrievers
     * @param string $code
     * @param array $filters
     * @return \Doctrine\ORM\QueryBuilder|mixed
     */
    public function getReportingValue(string $code, array $filters)
    {
        $qb = $this->em->createQueryBuilder()
                        ->from(ReportingDistribution::class, 'rd')
                        ->leftjoin('rd.value', 'rv')
                        ->leftjoin('rd.indicator', 'ri')
                        ->leftjoin('rd.distribution', 'd')
                        ->leftjoin('d.project', 'p')
                        ->where('ri.code = :code')
                        ->setParameter('code', $code)
                        ->andWhere('p.iso3 = :country')
                        ->setParameter('country', $filters['country']);


        $qb = $this->filterByProjects($qb, $filters['projects']);
        $qb = $this->filterByDistributions($qb, $filters['distributions']);

        return $qb;
    }

    /**
     * switch case to use the right select
     * each case is the name of the function to execute
     *
     * Indicator with the same 'select' statement is grouped in the same case
     * @param $qb
     * @param $nameFunction
     * @return mixed
     */
    public function conditionSelect($qb, $nameFunction)
    {
        switch ($nameFunction) {
            case 'BMSDistributionNEB':
                $qb ->select('d.name AS name')
                    ->groupBy('name');
                break;
            case 'BMSDistributionTDV':
                $qb ->select('DISTINCT(d.name) AS name', 'd.id AS id')
                    ->groupBy('name', 'id');
                break;
            case 'BMSUDistributionNM':
            case 'BMSUDistributionNW':
                $qb ->select("CONCAT(rv.unity, '/', d.name) AS name")
                    ->groupBy('name');
                break;
            case 'BMSDistributionM':
                $qb ->select('DISTINCT(d.name) AS name')
                    ->groupBy('name');
                break;
        }

        return $qb;
    }

    /**
     * Get the number of enrolled beneficiaries in a distribution
     * @param array $filters
     * @return array
     */
    public function BMSDistributionNEB(array $filters)
    {
        $qb = $this->getReportingValue('BMSDistributionNEB', $filters);
        $qb = $this->conditionSelect($qb, 'BMSDistributionNEB');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the total distribution value in a distribution
     * @param array $filters
     * @return array
     */
    public function BMSDistributionTDV(array $filters)
    {
        $qb = $this->getReportingValue('BMSDistributionTDV', $filters);
        $qb = $this->conditionSelect($qb, 'BMSDistributionTDV');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;

    }

    /**
     * Get the modality(and it type) for a distribution
     * @param array $filters
     * @return array
     */
    public function BMSDistributionM(array $filters)
    {
        $qb = $this->getReportingValue('BMSDistributionM', $filters);
        $qb = $this->conditionSelect($qb, 'BMSDistributionM');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the age breakdown in a distribution
     * @param array $filters
     * @return array
     */
    public function BMSDistributionAB(array $filters)
    {
        $qb = $this->getReportingValue('BMSDistributionAB', $filters);
        $qb = $this->conditionSelect($qb, 'BMSDistributionAB');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the number of men and women in a project
     * @param array $filters
     * @return array
     */
    public function BMSDistributionNMW(array $filters)
    {
        $men = $this->BMSUDistributionNM($filters);
        $women = $this->BMSUDistributionNW($filters);
        $menAndWomen = [];

        foreach(array_unique(array_merge(array_keys($men), array_keys($women))) as $period) {
            if(array_key_exists($period, $men)) {
                $menAndWomen[$period][] = $men[$period][0];
            }
            if(array_key_exists($period, $women)) {
                $menAndWomen[$period][] = $women[$period][0];
            }
        }
        return $menAndWomen;
    }

    /**
     * Get the percentage of vulnerabilities served
     * @param array $filters
     * @return array
     */
    public function BMSDistributionPVS(array $filters)
    {
        return $this->pieValuesToPieValuePercentage($this->BMSUDistributionTVS($filters));
    }

    /**
     * Get the percentage of value used in the project by the distribution
     * @param array $filters
     * @return array
     */
    public function BMSDistributionBR(array $filters)
    {
        $beneficiariesEnrolled = $this->BMSDistributionNEB($filters);

        $projectTarget = $this->em->createQueryBuilder()
            ->from(Project::class, 'p')
            ->where('p.id = :id')
            ->setParameter('id', $filters['projects'][0])
            ->select('p.target')
            ->getQuery()->getSingleScalarResult();

        foreach ($beneficiariesEnrolled as $period => $periodValues) {
            $totalReached = 0;
            foreach ($periodValues as $index => $value) {
                $percentage = $value['value'] / $projectTarget * 100;

                $beneficiariesEnrolled[$period][$index]['unity'] = $value['name'];
                $beneficiariesEnrolled[$period][$index]['value'] = $percentage;

                $totalReached += $percentage;
                unset($beneficiariesEnrolled[$period][$index]['name']);
            }
            $beneficiariesEnrolled[$period][] = [
                'date' => $period,
                'unity' => "Not reached",
                'value' => max(100 - $totalReached, 0)
            ];
        }

        return $beneficiariesEnrolled;
    }


    /**
     * Utils indicators
     */


    /**
     * Get the number of men in a distribution
     * @param array $filters
     * @return mixed
     */
    public function BMSUDistributionNM(array $filters)
    {
        $qb = $this->getReportingValue('BMSUDistributionNM', $filters);
        $qb = $this->conditionSelect($qb, 'BMSUDistributionNM');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the number of women in a distribution
     * @param array $filters
     * @return mixed
     */
    public function BMSUDistributionNW(array $filters)
    {
        $qb = $this->getReportingValue('BMSUDistributionNW', $filters);
        $qb = $this->conditionSelect($qb, 'BMSUDistributionNW');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the total of vulnerabilities served
     * @param array $filters
     * @return mixed
     */
    public function BMSUDistributionTVS(array $filters)
    {
        $qb = $this->getReportingValue('BMSUDistributionTVS', $filters);
        $qb = $this->conditionSelect($qb, 'BMSUDistributionTVS');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }

    /**
     * Get the total of vulnerabilities served by vulnerabilities
     * @param array $filters
     * @return mixed
     */
    public function BMSUDistributionTVSV(array $filters)
    {
        $qb = $this->getReportingValue('BMSUDistributionTVSV', $filters);
        $qb = $this->conditionSelect($qb, 'BMSUDistributionTVSV');
        $result = $this->formatByFrequency($qb, $filters['frequency'], $filters['period']);
        return $result;
    }
}

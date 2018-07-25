<?php

namespace ReportingBundle\Utils\DataRetrievers;

use Doctrine\ORM\EntityManager;

use ReportingBundle\Entity\ReportingProject;

class ProjectDataRetrievers 
{
    private $em;
    private $reportingCountry;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;   
        $this->reportingCountry = $em->getRepository(ReportingProject::class);
    }

    /**
     * Use to verify if a key project exist in filter
     * If this key exists, it means a project was selected in selector
     */
    public function ifInProject($qb, $filters) {
        if(array_key_exists('project', $filters)) {
            $qb->andWhere('p.id IN (:projects)')
                    ->setParameter('projects', $filters['project']);
        }
        return $qb;
    }

    /**
     * Use to make join and where in DQL
     * Use in all project data retrievers
     */
    public function getReportingValue(string $code, array $filters) {
        $qb = $this->reportingCountry->createQueryBuilder('rp')
                                    ->leftjoin('rp.value', 'rv')
                                    ->leftjoin('rp.indicator', 'ri')
                                    ->leftjoin('rp.project', 'p')
                                    ->where('ri.code = :code')
                                    ->setParameter('code', $code)
                                    ->andWhere('p.iso3 = :country')
                                    ->setParameter('country', $filters['country']);
        $qb = $this->ifInProject($qb, $filters);

        return $qb;
    }

    /**
     * Get the name of all donors
     */
    public function BMS_Project_D(array $filters) {
        $qb = $this->getReportingValue('BMS_Project_D', $filters);
        $qb->select('p.name AS name','rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of household served
     */
    public function BMS_Project_HS(array $filters) {
        $qb = $this->getReportingValue('BMS_Project_HS', $filters);
        $qb->select('p.name AS name','rv.value AS value', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the beneficiaries age
     */
    public function BMS_Project_AB(array $filters) {
        $qb = $this->getReportingValue('BMS_Project_AB', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS name', "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('name', 'date');
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of men and women in a project
     */
    public function BMS_Project_NMW(array $filters) {

        $menAndWomen = [];
        $mens = $this->BMSU_Project_NM($filters);
        $womens = $this->BMSU_Project_NW($filters);
        $lastDate = $mens[0]['date'];
        foreach($mens as $men) {
            if ($men['date'] > $lastDate) {
                $lastDate = $men['date'];
            }
        }
        foreach ($mens as $men) { 
            if ($men["date"] == $lastDate) {
                $result = [
                    'name' => $men["name"],
                    'project' => substr($men["name"],2),
                    'value' => $men["value"],
                    'date' => $men['date']
                ]; 
                array_push($menAndWomen, $result);
                foreach ($womens as $women) {
                    if (substr($women["name"],2) == substr($men["name"],2)) {
                        if ($women["date"] == $lastDate) {
                            $result = [
                                'name' => $women["name"],
                                'project' => substr($women["name"],2),
                                'value' => $women['value'],
                                'date' => $women['date']
                            ]; 
                            array_push($menAndWomen, $result);
                            break 1;
                        }
                    }  
                }                
            }   
        }
        return $menAndWomen; 
    }

    /**
     * Get the number of men
     */
    public function BMSU_Project_NM(array $filters) {
        $qb = $this->getReportingValue('BMSU_Project_NM', $filters);
        $qb->select('rv.value AS value', "CONCAT(UPPER(SUBSTRING(rv.unity, 1, 1)), '-', p.name) AS name", "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the number of women
     */
    public function BMSU_Project_NW(array $filters) {
        $qb = $this->getReportingValue('BMSU_Project_NW', $filters);
        $qb->select('rv.value AS value', "CONCAT(UPPER(SUBSTRING(rv.unity, 1, 1)), '-', p.name) AS name", "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date");
        return $qb->getQuery()->getArrayResult();
    }  
    
    /**
     * Get the percentage of vulnerabilities served
     */
    public function BMS_Project_PVS(array $filters) {
        $vulnerabilitiesPercentage = [];
        $totalVulnerabilities = $this->BMSU_Project_TVS($filters);
        $totalVulnerabilitiesByVulnerabilities = $this->BMSU_Project_TVSV($filters);
        $lastDate = $totalVulnerabilities[0]['date'];
        foreach($totalVulnerabilities as $totalVulnerability) {
            if ($totalVulnerability['date'] > $lastDate) {
                $lastDate = $totalVulnerability['date'];
            }
        }
        foreach ($totalVulnerabilities as $totalVulnerability) { 
            if ($totalVulnerability["date"] == $lastDate) {
                foreach ($totalVulnerabilitiesByVulnerabilities as $vulnerability) {
                    if ($vulnerability["date"] == $lastDate) {
                        $percent = ($vulnerability["value"]/$totalVulnerability["value"])*100;
                        $result = [
                            'name' => $vulnerability["unity"],
                            'value' => $percent,
                            'date' => $vulnerability['date']
                        ]; 
                        array_push($vulnerabilitiesPercentage, $result);
                    }   
                }                
            }   
        }
        return $vulnerabilitiesPercentage; 
    }

    /**
     * Get the total of vulnerabilities served by vulnerabilities
     */
    public function BMSU_Project_TVSV(array $filters) {
        $qb = $this->getReportingValue('BMSU_Project_TVSV', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS unity',  "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('unity', 'date');
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Get the total of vulnerabilities served
     */
    public function BMSU_Project_TVS(array $filters) {
        $qb = $this->getReportingValue('BMSU_Project_TVS', $filters);
        $qb->select('SUM(rv.value) AS value', 'rv.unity AS unity',  "DATE_FORMAT(rv.creationDate, '%Y-%m-%d') AS date")
           ->groupBy('unity', 'date');        
        return $qb->getQuery()->getArrayResult();
    }



}
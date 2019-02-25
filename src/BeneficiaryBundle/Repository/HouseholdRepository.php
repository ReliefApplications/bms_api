<?php

namespace BeneficiaryBundle\Repository;

use DistributionBundle\Repository\AbstractCriteriaRepository;
use Doctrine\ORM\QueryBuilder;
use ProjectBundle\Entity\Project;

/**
 * HouseholdRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class HouseholdRepository extends AbstractCriteriaRepository
{
    /**
     * Find all households in country
     * @param  string $iso3
     * @return QueryBuilder      
     */
    public function findAllByCountry(string $iso3)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->leftJoin("l.adm1", "adm1")
            ->leftJoin("l.adm2", "adm2")
            ->leftJoin("l.adm3", "adm3")
            ->leftJoin("l.adm4", "adm4")
            ->where("adm1.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm4.adm3", "adm3b")
            ->leftJoin("adm3b.adm2", "adm2b")
            ->leftJoin("adm2b.adm1", "adm1b")
            ->orWhere("adm1b.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm3.adm2", "adm2c")
            ->leftJoin("adm2c.adm1", "adm1c")
            ->orWhere("adm1c.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm2.adm1", "adm1d")
            ->orWhere("adm1d.countryISO3 = :iso3 AND hh.archived = 0")
            ->setParameter("iso3", $iso3);
        
        return $q;
    }

    /**
     * Return households which a Levenshtein distance with the stringToSearch under minimumTolerance
     * TODO : FOUND SOLUTION TO RETURN ONLY THE SIMILAR IF DISTANCE = 0 OR THE LIST OF HOUSEHOLDS WITH A DISTANCE
     * TODO : UNDER MINIMUMTOLERANCE, IF NO ONE HAS A DISTANCE = 0
     * @param string $stringToSearch
     * @param int $minimumTolerance
     * @return mixed
     */
    public function foundSimilarLevenshtein(string $iso3, string $stringToSearch, int $minimumTolerance)
    {
        $qb = $this->findAllByCountry($iso3);
        $q = $qb->leftJoin("hh.beneficiaries", "b")
            ->select("hh as household")
            ->addSelect("LEVENSHTEIN(
                    CONCAT(COALESCE(hh.addressStreet, ''), COALESCE(hh.addressNumber, ''), COALESCE(hh.addressPostcode, ''), COALESCE(b.givenName, ''), COALESCE(b.familyName, '')),
                    :stringToSearch
                ) as levenshtein")
            ->andWhere("b.status = 1")
            ->andWhere("
                LEVENSHTEIN(
                    CONCAT(COALESCE(hh.addressStreet, ''), COALESCE(hh.addressNumber, ''), COALESCE(hh.addressPostcode, ''), COALESCE(b.givenName, ''), COALESCE(b.familyName, '')),
                    :stringToSearch
                ) < 
                CASE 
                    WHEN (LEVENSHTEIN(
                        CONCAT(COALESCE(hh.addressStreet, ''), COALESCE(hh.addressNumber, ''), COALESCE(hh.addressPostcode, ''), COALESCE(b.givenName, ''), COALESCE(b.familyName, '')),
                        :stringToSearch) = 0) 
                        THEN 1
                    ELSE
                        :minimumTolerance
                    END
            ")
            ->setParameter("stringToSearch", $stringToSearch)
            ->setParameter("minimumTolerance", $minimumTolerance)
            ->orderBy("levenshtein", "ASC");

        return $q->getQuery()->getResult();
    }

    /**
     * Get all Household by country
     * @param $iso3
     * @param $begin
     * @param $pageSize
     * @param $sort
     * @param array $filters
     * @return mixed
     */
    public function getAllBy($iso3, $begin, $pageSize, $sort, $filters = [])
    {
        //Recover global information for the page
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->leftJoin("l.adm1", "adm1")
            ->leftJoin("l.adm2", "adm2")
            ->leftJoin("l.adm3", "adm3")
            ->leftJoin("l.adm4", "adm4")
            ->where("adm1.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm4.adm3", "adm3b")
            ->leftJoin("adm3b.adm2", "adm2b")
            ->leftJoin("adm2b.adm1", "adm1b")
            ->orWhere("adm1b.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm3.adm2", "adm2c")
            ->leftJoin("adm2c.adm1", "adm1c")
            ->orWhere("adm1c.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm2.adm1", "adm1d")
            ->orWhere("adm1d.countryISO3 = :iso3 AND hh.archived = 0")
            ->setParameter("iso3", $iso3);

        //If there is a sort, we recover the direction of the sort and the field that we want to sort
        if (array_key_exists('sort', $sort) && array_key_exists('direction', $sort)) {
            $value = $sort['sort'];
            $direction = $sort['direction'];

            //If the field is the location, we sort it by the direction sent
            if ($value == 'location') {
                $q->addSelect("(COALESCE(adm4.name, adm3.name, adm2.name, adm1.name)) AS HIDDEN order_adm");
                $q->addOrderBy("order_adm", $direction)
                    ->addGroupBy("order_adm")
                    ->addGroupBy('hh.id');
            }
            //If the field is the first name, we sort it by the direction sent
            else if ($value == 'firstName') {
                $q->leftJoin('hh.beneficiaries', 'b')
                    ->andWhere('hh.id = b.household')
                    ->addOrderBy('b.givenName', $direction)
                    ->addGroupBy("b.givenName")
                    ->addGroupBy('hh.id');
            }
            //If the field is the family name, we sort it by the direction sent
            else if ($value == 'familyName') {
                $q->leftJoin('hh.beneficiaries', 'b')
                    ->andWhere('hh.id = b.household')
                    ->addOrderBy('b.familyName', $direction)
                    ->addGroupBy("b.familyName")
                    ->addGroupBy('hh.id');
            }
            //If the field is the number of dependents, we sort it by the direction sent
            else if ($value == 'dependents') {
                $q->leftJoin("hh.beneficiaries", 'b')
                    ->andWhere('hh.id = b.household')
                    ->addSelect('COUNT(b.household) AS HIDDEN countBenef')
                    ->addGroupBy('b.household')
                    ->addOrderBy('countBenef', $direction)
                    ->addGroupBy('hh.id');
            }
            //If the field is the projects, we sort it by the direction sent
            else if ($value == 'projects') {
                $q->leftJoin('hh.projects', 'p')
                    ->addOrderBy('p.name', $direction)
                    ->addGroupBy("p.name")
                    ->addGroupBy('hh.id');
            }
            //If the field is the vulnerabilities, we sort it by the direction sent
            else if ($value == 'vulnerabilities') {
                $q->leftJoin('hh.beneficiaries', 'b')
                    ->andWhere('hh.id = b.household')
                    ->leftJoin('b.vulnerabilityCriteria', 'vb')
                    ->addOrderBy('vb.fieldString', $direction)
                    ->addGroupBy("vb.fieldString")
                    ->addGroupBy('hh.id');
            }
        }

        //If there is a filter array in the request
        if (count($filters) > 0) {

            //We join information that would be need for the filters
            $q->leftJoin('hh.beneficiaries', 'b2')
                ->leftJoin('hh.projects', 'p2');

            //Foreach filters in our array, we recover an index (to avoid parameters' repetitions in the WHERE clause) and the filters
            foreach ($filters as $indexFilter => $allFilter) {
                //We check if we have a location for the filter because we have to do a special treatment for this field
                if ($allFilter['category'] != 'locations') {
                    //If there is at least one filter index in the array
                    if (count($allFilter['filter']) > 0) {
                        //We recover the category of the filter chosen and the value of the filter
                        $category = $allFilter['category'];
                        $filters = $allFilter['filter'];

                        //We initialize counts to be able to do a AND WHERE or a OR WHERE when there is more filter in a category
                        $countFamilyName = 0;
                        $countDependents = 0;
                        $countProjects = 0;
                        $countVulnerabilities = 0;

                        //Foreach filters in the array we get an index (to avoid duplicate parameters) and the filter chosen (because the user can filter more than one value)
                        foreach ($filters as $index => $filter) {
                            //We check if the category is the familyName
                            if ($category == 'familyName') {
                                //If this is the first time we get there
                                if ($countFamilyName == 0) {
                                    //We do a AND WHERE clause to add the filter in our initial request
                                    $q->andWhere('hh.id = b2.household')
                                        ->andWhere('b2.familyName LIKE :filter' . $indexFilter . $index . ' OR b2.givenName LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                    //And we increment the count to don't come back in this condition if there is iteration
                                    $countFamilyName++;
                                //If this isn't the first time we get there
                                } else {
                                    //We do a OR WHERE clause to add the Xth filter in our initial request and don't erase the AND WHERE when count's value is 0
                                    $q->orWhere('b2.familyName LIKE :filter' . $indexFilter . $index)
                                        ->orWhere('b2.givenName LIKE :filter' . $indexFilter . $index . ' OR b2.givenName LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                }
                            //We check if the category is the number of dependents
                            } else if ($category == 'dependents') {
                                //If this is the first time we get there
                                if ($countDependents == 0) {
                                    //We do a AND WHERE clause to add the filter in our initial request
                                    $q->andWhere('hh.id = b2.household')
                                        ->andHaving('COUNT(b2.household) = :filter' . $indexFilter . $index)
                                        ->addGroupBy('b2.household')
                                        ->setParameter('filter' . $indexFilter . $index, $filter + 1);
                                    //And we increment the count to don't come back in this condition if there is iteration
                                    $countDependents++;
                                }
                                //If this isn't the first time we get there
                                else {
                                    //We do a OR WHERE clause to add the Xth filter in our initial request and don't erase the AND WHERE when count's value is 0
                                    $q->andWhere('hh.id = b2.household')
                                        ->orHaving('COUNT(b2.household) = :filter' . $indexFilter . $index)
                                        ->addGroupBy('b2.household')
                                        ->setParameter('filter' . $indexFilter . $index, $filter + 1);
                                }
                            //We check if the category is projects
                            } else if ($category == 'projects') {
                                //If this is the first time we get there
                                if ($countProjects == 0) {
                                    //We do a AND WHERE clause to add the filter in our initial request
                                    $q->andWhere('p2.name LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                    //And we increment the count to don't come back in this condition if there is iteration
                                    $countProjects++;
                                }
                                //If this isn't the first time we get there
                                else {
                                    //We do a OR WHERE clause to add the Xth filter in our initial request and don't erase the AND WHERE when count's value is 0
                                    $q->orWhere('p2.name LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                }
                            //We check if the category is vulnerabilities
                            } else if ($category == 'vulnerabilities') {
                                //If this is the first time we get there
                                if ($countVulnerabilities == 0) {
                                    //We do a AND WHERE clause to add the filter in our initial request
                                    $q->andWhere('hh.id = b2.household')
                                        ->leftJoin('b2.vulnerabilityCriteria', 'vb2')
                                        ->andWhere('vb2.fieldString LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                    //And we increment the count to don't come back in this condition if there is iteration
                                    $countVulnerabilities++;
                                }
                                //If this isn't the first time we get there
                                else {
                                    //We do a OR WHERE clause to add the Xth filter in our initial request and don't erase the AND WHERE when count's value is 0
                                    $q->andWhere('hh.id = b2.household')
                                        ->orWhere('vb2.fieldString LIKE :filter' . $indexFilter . $index)
                                        ->addGroupBy('hh')
                                        ->setParameter('filter' . $indexFilter . $index, '%' . $filter . '%');
                                }
                            }
                        }
                    }
                }
                //If the category is the location, there is no filter array inside the parameter index, so there is a special treatment
                elseif ($allFilter['category'] == 'locations') {
                    //We get the location selected and check if this is in the ADM4 / ADM3 / ADM2 / ADM1 to add it in the initial request
                    $q->andWhere("adm4.name LIKE :filter" . $indexFilter)
                        ->orWhere("adm3.name LIKE :filter" . $indexFilter)
                        ->orWhere("adm2.name LIKE :filter" . $indexFilter)
                        ->orWhere("adm1.name LIKE :filter" . $indexFilter)
                        ->addGroupBy('hh')
                        ->setParameter('filter' . $indexFilter, '%' . $allFilter['filter'] . '%');

                }
            }
        }
        $allData = $q->getQuery()->getResult();

        if (is_null($begin) && is_null($pageSize)) {
            return $allData;
        } else {
            $q->setFirstResult($begin)
            ->setMaxResults($pageSize);
            return [count($allData), $q->getQuery()->getResult()];
        }

    }

    /**
     * Get all Household by country and id
     * @param string $iso3
     * @param array  $ids
     * @return mixed
     */
    public function getAllByIds(string $iso3, array $ids)
    {
        $qb = $this->createQueryBuilder("hh");
        $q = $qb->leftJoin("hh.location", "l")
            ->leftJoin("l.adm1", "adm1")
            ->leftJoin("l.adm2", "adm2")
            ->leftJoin("l.adm3", "adm3")
            ->leftJoin("l.adm4", "adm4")
            ->where("adm1.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm4.adm3", "adm3b")
            ->leftJoin("adm3b.adm2", "adm2b")
            ->leftJoin("adm2b.adm1", "adm1b")
            ->orWhere("adm1b.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm3.adm2", "adm2c")
            ->leftJoin("adm2c.adm1", "adm1c")
            ->orWhere("adm1c.countryISO3 = :iso3 AND hh.archived = 0")
            ->leftJoin("adm2.adm1", "adm1d")
            ->orWhere("adm1d.countryISO3 = :iso3 AND hh.archived = 0")
            ->setParameter("iso3", $iso3);
        
        $q = $q->andWhere("hh.id IN (:ids)")
                ->setParameter("ids", $ids);

        return $q->getQuery()->getResult();
    }

    /**
     * @param $onlyCount
     * @param $countryISO3
     * @param Project $project
     * @return QueryBuilder|void
     */
    public function configurationQueryBuilder($onlyCount, $countryISO3, Project $project = null)
    {
        $qb = $this->createQueryBuilder("hh");
        if ($onlyCount)
            $qb->select("count(hh)");

        if (null !== $project)
        {
            $qb->where(":idProject MEMBER OF hh.projects")
                ->setParameter("idProject", $project->getId());
        }
        $qb->leftJoin("hh.beneficiaries", "b");
        $this->setCountry($qb, $countryISO3);

        return $qb;
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must have at least one beneficiary with the condition respected ($field $operator $value / Example: gender = 0)
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    public function whereDefault(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.beneficiaries", "b$i")
            ->andWhere("b$i.{$filters["field_string"]} {$filters["condition_string"]} :val$i")
            ->setParameter("val$i", $filters["value_string"]);
        if (null !== $filters["kind_beneficiary"])
            $qbSub->andWhere("b$i.status = :status$i")
                ->setParameter("status$i", $filters["kind_beneficiary"]);

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("val$i", $filters["value_string"]);
        if (null !== $filters["kind_beneficiary"])
            $qb->setParameter("status$i", $filters["kind_beneficiary"]);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereVulnerabilityCriterion(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.beneficiaries", "b$i");
        if (boolval($filters["condition_string"]))
        {
            $qbSub->leftJoin("b$i.vulnerabilityCriteria", "vc$i")
                ->andWhere("vc$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);
        }
        else
        {
            $qbSubNotIn = $this->createQueryBuilder("hhb$i");
            $this->setCountry($qbSubNotIn, $countryISO3, "b$i");
            $qbSubNotIn->leftJoin("hhb$i.beneficiaries", "bb$i")
                ->leftJoin("bb$i.vulnerabilityCriteria", "vcb$i")
                ->andWhere("vcb$i.id = :idvc$i")
                ->setParameter("idvc$i", $filters["id_field"]);

            $qbSub->andWhere($qbSub->expr()->notIn("hh$i", $qbSubNotIn->getDQL()));
        }

        if (null !== $filters["kind_beneficiary"])
        {
            $qbSub->andWhere("b$i.status = :status$i")
                ->setParameter("status$i", $filters["kind_beneficiary"]);
        }

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("idvc$i", $filters["id_field"])
            ->setParameter("status$i", $filters["kind_beneficiary"]);
    }

    /**
     * Create sub request. The main request while found household inside the subrequest (and others subrequest)
     * The household must respect the value of the country specific ($idCountrySpecific), depends on operator and value
     *
     * @param QueryBuilder $qb
     * @param $i
     * @param $countryISO3
     * @param array $filters
     */
    protected function whereCountrySpecific(QueryBuilder &$qb, $i, $countryISO3, array $filters)
    {
        $qbSub = $this->createQueryBuilder("hh$i");
        $this->setCountry($qbSub, $countryISO3, $i);
        $qbSub->leftJoin("hh$i.countrySpecificAnswers", "csa$i")
            ->andWhere("csa$i.countrySpecific = :countrySpecific$i")
            ->setParameter("countrySpecific$i", $filters["id_field"])
            ->andWhere("csa$i.answer {$filters["condition_string"]} :value$i")
            ->setParameter("value$i", $filters["value_string"]);

        $qb->andWhere($qb->expr()->in("hh", $qbSub->getDQL()))
            ->setParameter("value$i", $filters["value_string"])
            ->setParameter("countrySpecific$i", $filters["id_field"]);
    }

    /**
     * count the number of housholds linked to a project
     *
     * @param Project $project
     * @return
     */
    public function countByProject(Project $project)
    {
        $qb = $this->createQueryBuilder("hh");
        $qb->select("count(hh)")
            ->leftJoin("hh.projects", "p")
            ->andWhere("p = :project")
            ->setParameter("project", $project);

        return $qb->getQuery()->getResult()[0];
    }
}

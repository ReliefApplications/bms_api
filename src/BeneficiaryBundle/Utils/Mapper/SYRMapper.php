<?php


namespace BeneficiaryBundle\Utils\Mapper;


use BeneficiaryBundle\Entity\Beneficiary;
use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\CountrySpecificAnswer;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\NationalId;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use BeneficiaryBundle\Utils\ExportCSVService;
use CommonBundle\Utils\ExportService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Date;
use phpDocumentor\Reflection\FqsenResolver;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class SYRMapper extends AbstractMapper
{

    private $ISO3 = 'SYR';
    private $HEADER_ROW = 3;
    private $FIRST_ROW = 4;
    private $FIRST_COLUMN_AGE = 'F';
    private $LAST_COLUMN_AGE = 'S';

    private $headerAgeMapped = [];

    private $mapping = [
        "address_street" => "B",
        "age of all beneficiaries" => "F-S",
        "HEAD" => [
            "name" => "C",
            "gender" => "AB",
            "national_ids" => "E",
        ],
        "DEPENDENT" => [
        ]
    ];

    // 'custom' means that we have to read inside the cell to know the vulnerability name
    private $vulnerabilityCriteriaMap = [
        "HEAD" => [
            "custom" => "AD"
        ],
        "DEPENDENT" => [
            "disabled" => [
                "AF",
                "AI"
            ],
            "pregnant" => "AG",
        ]
    ];

    private $countrySpecifics = [
        "resident status" => "AK"
    ];

    private $ageConstraint = [
        "AF" => [
            "min" => 0,
            "max" => 17
        ],
        "AI" => [
            "min" => 18,
            "max" => 59
        ]
    ];

    /** @var ExportCSVService $exportCSVService */
    private $exportCSVService;

    /** @var array $sheetArray */
    private $sheetArray;


    public function __construct(EntityManagerInterface $entityManager, ExportCSVService $exportCSVService)
    {
        parent::__construct($entityManager);
        $this->exportCSVService = $exportCSVService;
    }

    /**
     * @param array $sheetArray
     * @return array
     * @throws \Exception
     */
    public function mapArray(array $sheetArray)
    {
        $this->sheetArray = $sheetArray;
        $listHouseholds = [];

        $this->mapHeader();
        // We parse the array => One line is one household (the line contains details of head of hh and of every beneficiaries)
        foreach ($sheetArray as $index => $row) {
            if ($index < $this->FIRST_ROW)
                continue;
            $orderedBirthDates = $this->getOrderedBirthDates($row);
            // HOUSEHOLD
            $household = new Household();
            $household->setAddressStreet($row[$this->mapping['address_street']]);
            foreach ($this->countrySpecifics as $countrySpecificName => $position) {
                $countrySpecific = new CountrySpecific($countrySpecificName, 'string', $this->ISO3);
                $countrySpecificAnswer = new CountrySpecificAnswer();
                $countrySpecificAnswer->setCountrySpecific($countrySpecific)
                    ->setAnswer($row[$position]);
                $household->addCountrySpecificAnswer($countrySpecificAnswer);
            }

            // HEAD OF HOUSEHOLD
            $head = new Beneficiary();
            $head->setStatus(1)
                ->setHousehold($household)
                ->setGender($row[$this->mapping['HEAD']['gender']])
                ->setDateOfBirth(null);
            $nationalIds = new NationalId();
            $nationalIds->setIdNumber($row[$this->mapping['HEAD']['national_ids']])
                ->setIdType('string');
            $head->addNationalId($nationalIds);
            foreach ($this->vulnerabilityCriteriaMap['HEAD'] as $vulnerabilityCriterionName => $position) {
                if ($vulnerabilityCriterionName === 'custom') {
                    $nameVulnerability = $row[$position];
                } else {
                    $nameVulnerability = $vulnerabilityCriterionName;
                }
                $vulnerabilityCriterion = $this->em->getRepository(VulnerabilityCriterion::class)
                    ->findOneByFieldString($nameVulnerability);

                if ($vulnerabilityCriterion instanceof VulnerabilityCriterion) {
                    $head->addVulnerabilityCriterion($vulnerabilityCriterion);
                }
            }
            $household->addBeneficiary($head);


            // DEPENDENTS
            $orderedVulnerabilitiesDependent = $this->getOrderedVulnerabilities($row);
            foreach ($orderedBirthDates as $birthDate) {
                $dependent = new Beneficiary();
                $dependent->setDateOfBirth($birthDate)
                    ->setStatus(0)
                    ->setHousehold($household);
                $currentVulnerabilityMap = $this->getNextVulnerability($orderedVulnerabilitiesDependent, $birthDate);
                if ($currentVulnerabilityMap !== null) {
                    $vulnerabilityCriterion = $this->em->getRepository(VulnerabilityCriterion::class)
                        ->findOneByFieldString($currentVulnerabilityMap["name"]);

                    if ($vulnerabilityCriterion instanceof VulnerabilityCriterion) {
                        $dependent->addVulnerabilityCriterion($vulnerabilityCriterion);
                    }
                }
                $household->addBeneficiary($dependent);
            }
            $listHouseholds[] = $household;
        }
        dump($listHouseholds);
        return $listHouseholds;
    }

    private function getNextVulnerability(array &$orderedVulnerabilitiesDependent, \DateTime $birthDate)
    {
        foreach ($orderedVulnerabilitiesDependent as $index => $currentVulnerabilityMap) {
            if (array_key_exists($currentVulnerabilityMap["position"], $this->ageConstraint)) {
                $age = $this->getAge($birthDate);
                if (array_key_exists('min', $this->ageConstraint[$currentVulnerabilityMap["position"]])
                    && $age < $this->ageConstraint[$currentVulnerabilityMap["position"]]['min']) {
                    next($orderedVulnerabilitiesDependent);
                    continue;
                }
                if (array_key_exists('max', $this->ageConstraint[$currentVulnerabilityMap["position"]])
                    && $age > $this->ageConstraint[$currentVulnerabilityMap["position"]]['max']) {
                    next($orderedVulnerabilitiesDependent);
                    continue;
                }
                unset($orderedVulnerabilitiesDependent[$index]);
                return $currentVulnerabilityMap;
            }
        }

        return null;
    }

    private function getAge(\DateTime $birthDate)
    {
        $birthDate = explode("/", $birthDate->format("m/d/Y"));
        return (date("md", date("U", mktime(0, 0, 0, $birthDate[0], $birthDate[1], $birthDate[2]))) > date("md")
            ? ((date("Y") - $birthDate[2]) - 1)
            : (date("Y") - $birthDate[2]));
    }

    public function getOrderedVulnerabilities($row)
    {
        $mappingVulnerabilities = [];
        foreach ($this->vulnerabilityCriteriaMap['DEPENDENT'] as $vulnerabilityName => $position) {
            if (is_array($position)) {
                foreach ($position as $subPosition) {
                    if (intval($row[$subPosition]) > 0) {
                        $mappingVulnerabilities[$subPosition] = [
                            "name" => $vulnerabilityName,
                            "position" => $subPosition,
                            "value" => intval($row[$subPosition])
                        ];
                    }
                }
            } else {
                if (intval($row[$position]) > 0) {
                    $mappingVulnerabilities[$position] = [
                        "name" => $vulnerabilityName,
                        "position" => $position,
                        "value" => intval($row[$position])
                    ];
                }
            }
        }

        return $mappingVulnerabilities;
    }

    public function mapBeneficiariesVulnerabilities($row)
    {
        $mappingVulnerabilities = [];
        foreach ($this->vulnerabilityCriteriaMap['DEPENDENT'] as $vulnerabilityName => $position) {
            if (is_array($position)) {
                foreach ($position as $subPosition) {
                    if (!array_key_exists($vulnerabilityName, $mappingVulnerabilities)) {
                        $mappingVulnerabilities[] = 0;
                    }
                    $mappingVulnerabilities[$vulnerabilityName]++;
                }
            } else {
                if (!array_key_exists($vulnerabilityName, $mappingVulnerabilities)) {
                    $mappingVulnerabilities[$vulnerabilityName] = 0;
                }
                $mappingVulnerabilities[$vulnerabilityName]++;
            }
        }
    }

    public function getOrderedBirthDates($row)
    {
        $orderedAges = [];
        $column = $this->FIRST_COLUMN_AGE;
        while ($column <= $this->LAST_COLUMN_AGE) {
            if (is_numeric($row[$column]) && $row[$column] > 0) {
                for ($i = 1; $i <= $row[$column]; $i++) {
                    $orderedAges[] = $this->headerAgeMapped[$column];
                }
            }
            $column = $this->SUMOfLetter($column, 1);
        }

        return $orderedAges;
    }

    public function mapHeader()
    {
        $column = $this->FIRST_COLUMN_AGE;
        while ($column <= $this->LAST_COLUMN_AGE) {
            if (strpos(strtolower($this->sheetArray[$this->HEADER_ROW][$column]), 'months') !== false) {
                $typeAge = 'months';
            } elseif (strpos(strtolower($this->sheetArray[$this->HEADER_ROW][$column]), 'yrs') !== false) {
                $typeAge = 'years';
            } else {
                continue;
            }
            $this->sheetArray[$this->HEADER_ROW][$column] = preg_replace('/[^0-9\-]/', '', $this->sheetArray[$this->HEADER_ROW][$column]);
            $limitAges = explode('-', $this->sheetArray[$this->HEADER_ROW][$column]);
            if (!is_array($limitAges)) {
                if (is_numeric($limitAges)) {
                    $averageAge = $limitAges;
                } else {
                    continue;
                }
            } else {
                if (sizeof($limitAges) > 1)
                    $averageAge = intval(($limitAges[0] + $limitAges[1]) / 2);
                elseif (sizeof($limitAges) === 1)
                    $averageAge = $limitAges[0];
                else
                    continue;
            }
            $birthDate = new \DateTime();
            $birthDate->modify("- $averageAge $typeAge");
            $this->headerAgeMapped[$column] = $birthDate;

            $column = $this->SUMOfLetter($column, 1);
        }
    }
}
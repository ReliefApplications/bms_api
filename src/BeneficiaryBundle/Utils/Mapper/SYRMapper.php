<?php


namespace BeneficiaryBundle\Utils\Mapper;


use BeneficiaryBundle\Entity\Beneficiary;
use BeneficiaryBundle\Entity\CountrySpecific;
use BeneficiaryBundle\Entity\CountrySpecificAnswer;
use BeneficiaryBundle\Entity\Household;
use BeneficiaryBundle\Entity\NationalId;
use BeneficiaryBundle\Entity\Phone;
use BeneficiaryBundle\Entity\VulnerabilityCriterion;
use BeneficiaryBundle\Utils\ExportCSVService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

    private $MAPPING_CSV_EXPORT = [
        // Household
        "A" => "Address street",
        "B" => "Address number",
        "C" => "Address postcode",
        "D" => "Livelihood",
        "E" => "Notes",
        "F" => "Latitude",
        "G" => "Longitude",
        // Location
        "H" => "Adm1",
        "I" => "Adm2",
        "J" => "Adm3",
        "K" => "Adm4",
        // Beneficiary
        "L" => "Given name",
        "M" => "Family name",
        "N" => "Gender",
        "O" => "Status",
        "P" => "Date of birth",
        "Q" => "Vulnerability criteria",
        "R" => "Phones",
        "S" => "National IDs"
    ];

    /** @var array $sheetArray */
    private $sheetArray;

    /** @var HouseholdToCSVMapper $householdToCSVMapper */
    private $householdToCSVMapper;

    /** @var ExportCSVService $exportCSVService */
    private $exportCSVService;


    public function __construct(EntityManagerInterface $entityManager,
                                HouseholdToCSVMapper $householdToCSVMapper,
                                ExportCSVService $exportCSVService)
    {
        parent::__construct($entityManager);
        $this->householdToCSVMapper = $householdToCSVMapper;
        $this->exportCSVService = $exportCSVService;
    }

    /**
     * @param array $listHouseholds
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function mapHouseholdToCsv($filename, array $listHouseholds)
    {
        $spreadsheet = $this->buildHeader($this->ISO3);
        $activeSheet = $spreadsheet->getActiveSheet();
        $this->buildContentFile($activeSheet, $listHouseholds);
        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setEnclosure('');
        $writer->setDelimiter(';');
        $writer->setUseBOM(true);
        $filename = 'mapped_' . $filename . '.csv';
        $writer->save($filename);

        return $filename;
    }

    /**
     * @param Worksheet $worksheet
     * @param array $listHousehold
     */
    public function buildContentFile(Worksheet &$worksheet, array $listHousehold)
    {
        $currentIndex = 3;
        /** @var Household $household */
        foreach ($listHousehold as $index => $household) {
            $currentIndex = $this->setHouseholdValue($worksheet, $household, $index + $currentIndex);
        }
    }

    private function setHouseholdValue(Worksheet &$worksheet, Household $household, int $index)
    {
        $worksheet->setCellValue("A$index", $household->getAddressStreet());
        $worksheet->setCellValue("B$index", $household->getAddressNumber());
        $worksheet->setCellValue("C$index", $household->getAddressPostcode());
        $worksheet->setCellValue("D$index", $household->getLivelihood());
        $worksheet->setCellValue("E$index", $household->getNotes());
        $worksheet->setCellValue("F$index", $household->getLatitude());
        $worksheet->setCellValue("G$index", $household->getLongitude());
        $nbCountrySpecific = sizeof($this->getCountrySpecifics($this->ISO3));
        $currentColumn = Household::firstColumnNonStatic;
        /** @var CountrySpecificAnswer $countrySpecificAnswer */
        foreach ($household->getCountrySpecificAnswers() as $countrySpecificAnswer) {
            $tmpCurrentColumn = $currentColumn;
            $value = $countrySpecificAnswer->getAnswer();
            $countrySpecificName = $countrySpecificAnswer->getCountrySpecific()->getFieldString();
            $nbTested = 0;
            while ($worksheet->getCell($tmpCurrentColumn . 2)->getValue() !== $countrySpecificName) {
                $nbTested++;
                $tmpCurrentColumn = $this->SUMOfLetter($tmpCurrentColumn, 1);
                if ($nbTested > $nbCountrySpecific) {
                    break;
                }
            }
            if ($worksheet->getCell($tmpCurrentColumn . 2)->getValue() === $countrySpecificName) {
                $worksheet->setCellValue($tmpCurrentColumn . $index, $value);
            }
        }
        $currentColumn = $this->SUMOfLetter($currentColumn, $nbCountrySpecific);
        /** @var Beneficiary $beneficiary */
        foreach ($household->getBeneficiaries() as $indexBenef => $beneficiary) {
            $this->setBeneficiaryValue($worksheet, $beneficiary, $currentColumn, $index + $indexBenef);
            $currentIndex = $index + $indexBenef;
        }
        return $currentIndex;
    }

    private function setBeneficiaryValue(Worksheet &$worksheet, Beneficiary $beneficiary, string $columnBase, int $index)
    {
        $worksheet->setCellValue($columnBase . $index, $beneficiary->getGivenName());
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 1) . $index, $beneficiary->getFamilyName());
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 2) . $index, $beneficiary->getGender());
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 3) . $index, $beneficiary->getStatus());
        if ($beneficiary->getDateOfBirth() !== null) {
            $worksheet->setCellValue($this->SUMOfLetter($columnBase, 4) . $index,
                $beneficiary->getDateOfBirth()->format('Y-m-d'));
        }
        $vulnerabilityFormatted = '';
        /** @var VulnerabilityCriterion $vulnerabilityCriterion */
        foreach ($beneficiary->getVulnerabilityCriteria() as $vulnerabilityCriterion) {
            if ($vulnerabilityFormatted !== '')
                $vulnerabilityFormatted .= ';';
            $vulnerabilityFormatted .= $vulnerabilityCriterion->getFieldString();
        }
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 5) . $index, $vulnerabilityFormatted);
        $phonesFormatted = '';
        /** @var Phone $phone */
        foreach ($beneficiary->getPhones() as $phone) {
            if ($phonesFormatted !== '')
                $phonesFormatted .= ';';
            $phonesFormatted .= $phone->getType() . ' - ' . $phone->getNumber();
        }
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 6) . $index, $phonesFormatted);
        $IdsFormatted = '';
        /** @var NationalId $nationalId */
        foreach ($beneficiary->getNationalIds() as $nationalId) {
            if ($IdsFormatted !== '')
                $IdsFormatted .= ';';
            $IdsFormatted .= $nationalId->getIdType() . ' - ' . $nationalId->getIdNumber();
        }
        $worksheet->setCellValue($this->SUMOfLetter($columnBase, 7) . $index, $IdsFormatted);
    }

    /**
     * @param $countryISO3
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function buildHeader($countryISO3)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->createSheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $countrySpecifics = $this->getCountrySpecifics($countryISO3);
        $columnsCountrySpecificsAdded = false;

        $i = 0;
        $worksheet->setCellValue('A' . 1, "Household");
        foreach ($this->MAPPING_CSV_EXPORT as $CSVIndex => $name) {
            if (!$columnsCountrySpecificsAdded && $CSVIndex >= Household::firstColumnNonStatic) {
                if (!empty($countrySpecifics)) {
                    $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 1, "Country Specifics");
                    /** @var CountrySpecific $countrySpecific */
                    foreach ($countrySpecifics as $countrySpecific) {
                        $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $countrySpecific->getFieldString());
                        $i++;
                    }
                }
                $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 1, "Beneficiary");
                $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $name);
                $columnsCountrySpecificsAdded = true;
            } else {
                if ($CSVIndex >= Household::firstColumnNonStatic) {
                    $worksheet->setCellValue(($this->SUMOfLetter($CSVIndex, $i)) . 2, $name);
                } else {
                    $worksheet->setCellValue($CSVIndex . 2, $name);
                }
            }
        }

        return $spreadsheet;
    }

    /**
     * @param $countryISO3
     * @return mixed
     */
    private function getCountrySpecifics($countryISO3)
    {
        $countrySpecifics = $this->em->getRepository(CountrySpecific::class)->findByCountryIso3($countryISO3);
        return $countrySpecifics;
    }

    /**
     * @param $filename
     * @param array $sheetArray
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function mapArray($filename, array $sheetArray)
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
                ->setGivenName($row[$this->mapping['HEAD']['name']])
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

        return $this->mapHouseholdToCsv($filename, $listHouseholds);
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
<?php


namespace BeneficiaryBundle\Utils\Mapper;


use BeneficiaryBundle\Utils\ExportCSVService;
use CommonBundle\Utils\ExportService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class SYRMapper extends AbstractMapper
{

    private $FIRST_ROW = 4;
    private $FIRST_COLUMN_AGE = 'F';
    private $LAST_COLUMN_AGE = 'S';

    private $mapping = [
        "ONE LOCATION" => "B",
        "ID HEAD" => "E",
        "Name HEAD" => "C",
        "Gender HEAD" => "AB",
        "Vulnerability Criterion HEAD" => "AD",
        "age of all beneficiaries" => "F-S",
        "Number of disabled or chronically ill 0-17y DEP" => "AF",
        "Number of pregnant DEP" => "AG",
        "Number of children under 5 DEP" => "AH",
        "Number of disabled or chronically ill 18-59y DEP" => "AI"
    ];

    private $vulnerabilityCriteriaMap = [
        "HEAD" => "AD",
        "DEPENDENT" => [
            "AF" => "disabled",
            "AG" => "pregnant",
            "AI" => "disabled",
        ]
    ];

    private $countrySpecifics = [
        "AK" => "resident status"
    ];

    private $ageConstraint = [
        "AF" => 8,
        "AI" => 38
    ];

    /** @var ExportCSVService $exportCSVService */
    private $exportCSVService;

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
        try {
            $mappingCSV = $this->loadMappingCSVOfCountry('SYR');
        } catch (\Exception $exception) {
            dump($exception);
            throw new \Exception("Error during the loading of the mapping of country SYR");
        }
        $arrayFormatted = [];

        dump($mappingCSV);
        // We parse the array => One line is one household (the line contains details of head of hh and of every beneficiaries)
        foreach ($sheetArray as $index => $row) {
            if ($index < $this->FIRST_ROW)
                continue;
            $houesholdArray = [];
            dump($this->countingSizeHousehold($row));
        }
        return $arrayFormatted;
    }

    public function countingSizeHousehold($row)
    {
        $size = 0;
        $column = $this->FIRST_COLUMN_AGE;
        while ($column <= $this->LAST_COLUMN_AGE) {
            if (is_numeric($row[$column]))
                $size += $row[$column];
            $column = $this->SUMOfLetter($column, 1);
        }

        return $size;
    }
}
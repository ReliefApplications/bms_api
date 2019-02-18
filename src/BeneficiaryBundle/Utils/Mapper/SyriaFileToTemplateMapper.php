<?php

declare(strict_types=1);

namespace BeneficiaryBundle\Utils\Mapper;

use ArrayObject;
use BeneficiaryBundle\Exception\MapperException;
use CommonBundle\Utils\ExportService;
use DateInterval;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Exception as PhpOfficeException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as PhpOfficeReaderException;
use PhpOffice\PhpSpreadsheet\Writer\Exception as PhpOfficeWriterException;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;
use function explode;
use function implode;
use function in_array;
use function microtime;
use function sprintf;
use function set_time_limit;
use function strpos;
use function str_replace;
use function trim;
class SyriaFileToTemplateMapper
{
    /**
     * Date of the day when the script is executed.
     * Used to compute birthdays.
     *
     * @var DateTime $TODAY
     */
    private static $TODAY;

    /*
     * Handle sex constants
     */
    private const MALE   = 'Male';
    private const FEMALE = 'Female';

    private const INPUT_COLUMN_START = 'A';
    private const INPUT_COLUMN_END   = 'Y';

    private const SPACE_SEPARATOR   = ' ';

    /**
     * Used to avoid copy of this array during copy
     *
     * @var array $mapping
     */
    private $mapping = [];

    /**
     * Used to avoid copy of this array during copy
     *
     * @var array $birthdays
     */
    private $birthdays = [];

    /** @var ExportService $defaultExportService */
    private $defaultExportService;

    public function __construct(ExportService $defaultExportService)
    {
        $this->defaultExportService = $defaultExportService;
        self::$TODAY = new DateTime();
    }

    /**
     * Map a file within the Syria Model to the default template
     *
     * @param mixed[] $input An array with all required informations
     *                       {
     *                       file: File,
     *                       options?: jsonArray
     *                       }
     *
     * @return array
     * {
     *   outputFile: File,
     *   loadingTime: Numeric,
     *   executionTime: Numeric,
     *   writeTime: Numeric,
     * }
     * @throws MapperException
     */
    public function map(array $input) : array
    {
        try {
            /** @var File $file */
            $file     = $input['file'];
            $location = $input['location'];

            // Load input file
            $time        = microtime(true);
            $reader      = IOFactory::createReaderForFile($file->getRealPath());
            $worksheet   = $reader->load($file->getRealPath())->getActiveSheet();
            $loadingTime = microtime(true) - $time;

            // Map and generate output content
            // security to avoid infinite loop during test
            set_time_limit(60); // after 60 seconds it should crash to avoid server termination
            $time          = microtime(true);
            $highestRow    = $worksheet->getHighestRow();
            $sheetArray    = $worksheet->rangeToArray('A1:Z' . $highestRow, null, true, true, true);
            $output        = $this->doMap($sheetArray, [
                'location' => $location,
            ]);
            $executionTime = microtime(true) - $time;

            // create new speadsheet
            $time        = microtime(true);
            $spreadsheet = new Spreadsheet();
            $spreadsheet->createSheet();
            $worksheet = $spreadsheet->getActiveSheet();

            // Write header
            $currentIndex = 1;
            foreach ($this->prepareOutputHeaderRow() as $letter => $value) {
                $worksheet->setCellValue($letter . $currentIndex, $value);
            }

            // Write content
            $currentIndex = 5;
            foreach ($output as $row) {
                $currentIndex++;
                foreach ($row as $letter => $cell) {
                    $worksheet->setCellValue($letter . $currentIndex, $cell);
                }
            }

            $filename  = $this->defaultExportService->generateFile(
                $spreadsheet,
                'syriaToStandard' . (new DateTime())->getTimestamp(),
                ExportService::FORMAT_XLS
            );
            $writeTime = microtime(true) - $time;

            set_time_limit(0);

            return [
                'outputFile' => $filename,
                'loadingTime' => $loadingTime,
                'executionTime' => $executionTime,
                'writeTime' => $writeTime,
            ];
        } catch (PhpOfficeReaderException|PhpOfficeWriterException|PhpOfficeException $exception) {
            throw new MapperException(sprintf('[PhpOffice] %s', $exception->getMessage()));
        } catch (Throwable $exception) {
            throw new MapperException($exception->getMessage());
        }
    }

    /**
     * Map the given array to the standard template
     *
     * @param array $sheetArray The uploaded file converted to an array
     *
     * @param array $parameters An array with all required informations
     *                       {
     *                          location: {
     *                              admIndex: number,
     *                              name: string,
     *                          },
     *                       }
     * @return string[][]
     * @throws MapperException
     */
    private function doMap(array $sheetArray, $parameters = []) : array
    {
        // O. Validation step
        $admType  = '';
        $location = '';

        foreach ($parameters['location'] as $admIndex => $value) {
            if (! empty($value)) {
                $location = $value;
                $admType  = 'adm' . $admIndex;
                break;
            }
        }
        if (empty($location)) {
            throw new MapperException('A location is required with admX:value format');
        }
        if (empty($admType)) {
            throw new MapperException('Adm type was not recognized');
        }

        // End 0.

        $this->initializeBirthdays();
        $defaultMapping = $this->getMapping();
        $outputRows = [];

        $addressStreet = trim(str_replace('LOCATION:','', $sheetArray[2]['A']));;

        foreach ($sheetArray as $indexRow => $row) {
            if ($indexRow < 10) {
                // we start at row 10
                continue;
            }

            if (empty($row['A'])){
                // we break at the first empty row
                break;
            }

            // A. HANDLE SHARED COLUMNS
            /**
             * we use the same variable to store the columns shared between
             * members of a family
             * @var mixed[] $mutualOutputRow
             */
            $mutualOutputRow = ['AE' => $indexRow];
            $secondBeneficiaryValues = ['AE' => $indexRow];
            $secondBeneficiaryExists = false;

            /**
             * Names
             * M => Family name
             * L => Given name
             */
            $nameOfBeneficiary = $row['B'];
            // If there is a slash in the beneficiary name
            if (strpos($nameOfBeneficiary, DIRECTORY_SEPARATOR) !== false) {
                // A second beneficiary exists
                $secondBeneficiaryExists = true;
                // We store these names in an array
                $beneficiaryNames = explode(DIRECTORY_SEPARATOR, $nameOfBeneficiary);
                // Get the name of the family's head
                $headName = trim($beneficiaryNames[0]);
                // If the headName contains a space => first + surname
                if (strpos($headName, self::SPACE_SEPARATOR) !== false) {
                    // We store the first and surname into an array 
                    $headNames = explode(self::SPACE_SEPARATOR, $headName);
                    // Writes the family name in the column M
                    $mutualOutputRow['M'] = $headNames[0];
                    unset($headNames[0]);
                    // Writes the rest of the name in the column L
                    $mutualOutputRow['L'] = implode(' ', $headNames);
                } else {
                    // Else writes everything in column M
                    $mutualOutputRow['M'] = $headName;
                }

                // Get the name of the second beneficiary
                $otherBeneficiary = trim($beneficiaryNames[1]);
                // If this name contains a space => first + surname
                if (strpos($otherBeneficiary, self::SPACE_SEPARATOR) !== false) {
                    // We store the first and surname into an array
                    $names = explode(self::SPACE_SEPARATOR, $otherBeneficiary);
                    // Writes the family name in the column M
                    $secondBeneficiaryValues['M'] = $names[0];
                    unset($names[0]);
                    // Writes the rest of the name in the column L
                    $secondBeneficiaryValues['L'] = implode(' ', $names);
                } else {
                    // Else writes everything in column M
                    $secondBeneficiaryValues['M'] = $otherBeneficiary;
                }
            } else { // If only one name is found => second beneficiary doesn't exist
                // If there is a space in the beneficiary's name
                if (strpos($nameOfBeneficiary, self::SPACE_SEPARATOR) !== false) {
                    // Get the different parts of the name
                    $names = explode(self::SPACE_SEPARATOR, $nameOfBeneficiary);
                    // Writes the family name in the column M
                    $mutualOutputRow['M'] = $names[0];
                    unset($names[0]);
                    // Writes the first name in the column L
                    $mutualOutputRow['L'] = implode(' ', $names);
                }else {
                    // Else writes everything in column M
                    $mutualOutputRow['M'] = $nameOfBeneficiary;
                }
            }

            // Get beneficiary's id
            $beneficiaryId = strval($row['C']);
            if (!empty($beneficiaryId)) {
                // Writes 'ID Card' in the column Z
                $mutualOutputRow['Z'] = 'ID Card';
                // If there is a slash in the id => two ids
                if (strpos($beneficiaryId, DIRECTORY_SEPARATOR) !== false) {
                    $beneficiariesId = explode(DIRECTORY_SEPARATOR, $beneficiaryId);
                    $mutualOutputRow[$defaultMapping['C']] = trim($beneficiariesId[0]);

                    if ($secondBeneficiaryExists) {
                        $secondBeneficiaryValues[$defaultMapping['C']] = trim($beneficiariesId[1]);
                        $secondBeneficiaryValues['Z'] = 'ID Card';
                    } else {
                        // File badly filled in
//                    throw new Exception('Die' . $indexRow);
                    }
                } else { // only one Id found
                    $mutualOutputRow[$defaultMapping['C']] = $beneficiaryId;
                }
            }

            // Get the gender of the family's head
            $headGender = $row['T'];
            // Writes the gender in column N
            $mutualOutputRow['N'] = intval($headGender) === 1 ? self::FEMALE : self::MALE;

            // Set head of household status
            $mutualOutputRow['O'] = 1;
            if ($secondBeneficiaryExists) {
                $secondBeneficiaryValues['O'] = 0;
            }

            // B. LET ADD HEAD OF HOUSEHOLD and its second
            $headOfHouseholdRow = new ArrayObject($mutualOutputRow);
            // address
            $headOfHouseholdRow['A'] = $addressStreet;
            $headOfHouseholdRow['B'] = $row[$defaultMapping['B']] ? $row[$defaultMapping['B']] : 'Unknown';
            $headOfHouseholdRow['C'] = 'Unknown';
            $headOfHouseholdRow[$defaultMapping[$admType]] = $location;
            if (!empty($row['E'])) {
                // head phone number
                $headOfHouseholdRow['R'] = 'Mobile';
                $headOfHouseholdRow['S'] = '\'+963';
                $headOfHouseholdRow['T'] = '\'' . $row['E'];
                $headOfHouseholdRow['U'] = 'N';
            }

            /**
             * remove and potential second from list of beneficiaries by guessing their ages
             * Strategy:
             * Find the oldest person having the sex of the head
             * Find the second adult person
             */
            $mainHeadRemoved = false;
            $subHeadRemoved  = false;
            $letters = range('I', 'R');
            $genders = [self::MALE, self::FEMALE];

            for ($i = count($letters) - 1; $i>=0; $i--) {
                $letter = $letters[$i];
                $cellValue = intval($row[$letter]);
                if ($cellValue <= 0) {
                    // we ignore an empty column
                    continue;
                }
                // the 1st index (column V) is odd and matches a woman
                // if the current person has the same sex than the main and the main has not been removed yet
                if (!$mainHeadRemoved) {
                    // odd means woman, $headGender===1 also means woman
                    if (($i % 2 != 0 && intval($headGender) === 1) || ($i % 2 == 0 && intval($headGender) === 0)) {
                        //we potentially found the first older person having the head of household sex
                        //we remove him

                        $headOfHouseholdRow['P'] = $this->getBirthday($letter);

                        $row[$letter] = --$cellValue;
                        $mainHeadRemoved = true;
                        if (!$secondBeneficiaryExists) {
                            break;
                        }
                    }
                }

                // there's another person in the same sex-age group
                // and the sub has not been removed yet
                if ($secondBeneficiaryExists && $cellValue > 0 && ! $subHeadRemoved) {
                    $row[$letter] = intval($row[$letter]) - 1;
                    $subHeadRemoved = true;
                    // set second beneficiary sex and birthday: odd means woman
                    $secondBeneficiaryValues['N'] = $i % 2 != 0 ? self::FEMALE : self::MALE;
                    $secondBeneficiaryValues['P'] = $this->getBirthday($letter);

                    if ($mainHeadRemoved) {
                        break;
                    }
                }
            }
            unset($letters);

            $outputRows[] = $headOfHouseholdRow;
            if ($secondBeneficiaryExists && $subHeadRemoved) {
                $outputRows[] = new ArrayObject($secondBeneficiaryValues);
            }

            // C. HANDLE NON SHARED COLUMNS: Let add each beneficiary
            // knowing that mainhead and subhead have been removed

            // remove head status for beneficiaries
            $mutualOutputRow['O'] = 0;
            // starting from here, we create a row per value of column
            $mutualOutputRowToArrayObject = new ArrayObject($mutualOutputRow);
            $letters = range('I', 'R');
            for ($i = 0; $i < count($letters); $i++) {
                // $i is MALE
                $column = $letters[$i];
                // count members of family in a age class
                $ageGroupCount = intval($row[$column]);

                // ignore null or 0 values
                if ($ageGroupCount === 0) {
                    continue;
                }

                /**
                 * For each beneficiary of a household, fill these fields:
                 * - L: given name: concatenate
                 *      "head of family name (column C)" + ageGroup + countInAgeGroup
                 * - M: family name: keep "head of family name (column C)"
                 *    -- same than the head of family
                 *    -- this column is handled in the part I.
                 * - N: sex: Male or Female
                 * - P: date delay:
                 */
                for ($j=0; $j < $ageGroupCount; $j++) {
                    $outputRow = $mutualOutputRowToArrayObject->getArrayCopy();

                    // given name
                    $outputRow['L'] = sprintf("%s_%s_%s", $outputRow['M'], $column, $j);

                    // birthday
                    $outputRow['P'] = $this->getBirthday($column);

                    // sex
                    if (in_array($column, ['I', 'K', 'M', 'O', 'Q'])) {
                        $outputRow['N'] = self::MALE;
                    } else if (in_array($column, ['J', 'L', 'N', 'P', 'R'])) {
                        $outputRow['N'] = self::FEMALE;
                    } else {
                        $outputRow['N'] = $genders[array_rand($genders)];
                    }

                    $outputRows[] = $outputRow;
                }
            }

            unset($mutualOutputRowToArrayObject);
        }

        return $outputRows;
    }

    /**
     * Returns the 1st row of the output file
     *
     * @return array
     */
    private function prepareOutputHeaderRow() : array
    {
        return [
            'A' => 'Address street',
            'B' => 'Address number',
            'C' => 'Address postcode',
            'D' => 'Livelihood',
            'E' => 'Notes',
            'F' => 'Latitude',
            'G' => 'Longitude',
            'H' => 'Adm1',
            'I' => 'Adm2',
            'J' => 'Adm3',
            'K' => 'Adm4',
            'L' => 'Given name',
            'M' => 'Family name',
            'N' => 'Gender',
            'O' => 'Status',
            'P' => 'Date of birth',
            'Q' => 'Vulnerability criteria',
            'R' => 'Type phone 1',
            'S' => 'Prefix phone 1',
            'T' => 'Number phone 1',
            'U' => 'Proxy phone 1',
            'V' => 'Type phone 2',
            'W' => 'Prefix phone 2',
            'X' => 'Number phone 2',
            'Y' => 'Proxy phone 2',
            'Z' => 'Type national ID',
            'AA' => 'Number national ID',
        ];
    }

    /**
     * Gives the mapping column by column to trancript a Syria file to the default
     * template
     *
     * @return array
     */
    private function &getMapping() : array
    {
        if (! empty($this->mapping)) {
            return $this->mapping;
        }

        $this->mapping = [
            self::INPUT_COLUMN_START => '',  // id
            'B' => 'B',  // tent number
            'C' => 'AA',  // id number ob beneficiary
            'adm1' => 'H',
            'adm2' => 'I',
            'adm3' => 'J',
            'adm4' => 'K',
            self::INPUT_COLUMN_END => '',  // Signature / Thumbprint of beneficiary
        ];

        return $this->mapping;
    }

    /**
     * Retrieve the birthday from the given column.
     *
     * @param string $column The column from the input file
     *
     * @return string The formated date
     */
    private function getBirthday(string $column) : string
    {
        return $this->birthdays[$column];
    }

    private function initializeBirthdays() : void
    {
        $this->birthdays = [
            'I' => (clone self::$TODAY)->sub(new DateInterval('P1Y'))->format('Y-m-d'),
            'J' => (clone self::$TODAY)->sub(new DateInterval('P1Y'))->format('Y-m-d'),
            'K' => (clone self::$TODAY)->sub(new DateInterval('P3Y'))->format('Y-m-d'),
            'L' => (clone self::$TODAY)->sub(new DateInterval('P3Y'))->format('Y-m-d'),
            'M' => (clone self::$TODAY)->sub(new DateInterval('P11Y'))->format('Y-m-d'),
            'N' => (clone self::$TODAY)->sub(new DateInterval('P11Y'))->format('Y-m-d'),
            'O' => (clone self::$TODAY)->sub(new DateInterval('P39Y'))->format('Y-m-d'),
            'P' => (clone self::$TODAY)->sub(new DateInterval('P39Y'))->format('Y-m-d'),
            'Q' => (clone self::$TODAY)->sub(new DateInterval('P61Y'))->format('Y-m-d'),
            'R' => (clone self::$TODAY)->sub(new DateInterval('P61Y'))->format('Y-m-d'),
        ];
    }
}

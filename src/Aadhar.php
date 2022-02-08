<?php

namespace Espl\Aadharscanner;

use thiagoalessio\TesseractOCR\TesseractOCR;

class Aadhar
{

    private $extraWords;
    private $splitter;
    private $name, $aadhar, $gender, $dob;
    public $surNames;
    public $indianName;
    public $suffix;


    public function __construct()
    {
        $this->extraWords  = 'Enrotiment|Enrolment|Address|Enrolment|Year|DOR|GOVERNMENT INDIA|GOVERNMENT|INDIA|No|enrollment|year of birth|Year of Birth|DOB|Male|Female|MALE|FEMALE|male|female|Femaie';
        $this->splitter = 'year of birth|Year of Birth|DOB|dob|Male|Female|MALE|FEMALE|male|female|Femate|femate';
        $this->readFile();
    }

    /**
     * readFile
     * 
     * - This will help to extract exact words
     */
    public function readFile()
    {
        $this->surNames = file_get_contents(__DIR__ . "/resource/surname.txt");
        $this->indianName = file_get_contents(__DIR__ . "/resource/name.txt");

        $_suffixMale = explode(',', file_get_contents(__DIR__ . "/resource/suffix-male.txt"));
        $_suffixFemale = explode(',', file_get_contents(__DIR__ . "/resource/suffix-female.txt"));
        $this->suffix = array_merge($_suffixFemale, $_suffixMale);
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setDOB($dob)
    {
        $this->dob = $dob;
    }

    public function getDOB()
    {
        return $this->dob;
    }

    public function setAadhar($aadhar)
    {
        $this->aadhar = $aadhar;
    }

    public function getAadhar()
    {
        return $this->aadhar;
    }

    public function setGender($gender)
    {
        $this->gender = $gender;
    }

    public function getGender()
    {
        return $this->gender;
    }

    public function setRawData($text)
    {
        $this->rawData = $text;
    }

    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * Start Processing
     */
    public function extractDetails($image)
    {
        $originalText = (new TesseractOCR($image))
            ->lang('eng', 'guj')
            ->run();

        $cleanUp = preg_replace('/[^a-z0-9_ ]/i', ' ', $originalText);
        $cleanUpText = preg_replace('/\s+/', ' ', $cleanUp);
        $this->setRawData($cleanUpText);
        $this->extractAadharDetails($cleanUpText);
        return [
            'name'  => $this->getName(),
            'dob'   => $this->getDOB(),
            'aadhar' => $this->getAadhar(),
            'gender' => $this->getGender(),
            'raw_data' => $this->getRawData()
        ];
    }



    /**
     * Extract Aadhar Details
     */
    public function extractAadharDetails($text)
    {
        $details = [];
        preg_match('/\b(' . $this->splitter . ')\b/', $text, $matches);
        if (count($matches) > 0) {
            $details = $this->dataFromArray($text);
        } else {
            $details = $this->dataFromText($text);
        }
        return $details;
    }

    /**
     * Fetch Details from Text
     */
    public function dataFromText($text)
    {
        $name = '';
        $dob = '';
        $aadhar = '';
        $gender = '';
        $dobPosition = 0;
        $aadharPosition = 0;

        // DOB
        $dob = $this->findDOB($text, $dobPosition);
        $dobPosition = stripos($text, $dob);

        /**
         * ========
         * Aadhar No
         * ========
         * 1. Normally, Addhar number is written after DOB. 
         * 2. Sometime DOB year is counted as first four digit of Aadhar.
         * 3. Probablility of getting correct Aadhar number is higher after DOB's position.
         * 
         */
        $noOfCharInDob = 10; // 25 11 1982
        $aadhar = $this->findAadhar($text, $dobPosition + $noOfCharInDob);
        $aadharPosition = stripos($text, $aadhar);

        /**
         * ========
         * Get Name
         * ========
         * Probablity of getting name is higher befor DOB position
         */

        $gender = $this->findGender($text);

        $name = substr($text, 0, ($dobPosition != 0) ? $dobPosition : $aadharPosition);
        if ($name == '')
            $name = $text;
        $name = $this->findName($name);

        $this->setName($name);
        $this->setDOB($dob);
        $this->setAadhar($aadhar);
        $this->setGender($gender);
    }

    /**
     * Fetch details from Array
     * This method will help to extract exact details if expected string found. e.g. DOB / Male / Female
     */
    public function dataFromArray($text)
    {
        $name = '';
        $dob = '';
        $aadhar = '';
        $gender = '';
        $splits = preg_split('/\b' . $this->splitter . '\b/', $text);

        if (count($splits) == 3) {
            $name = $this->findName($splits[0]);
            $dob = $this->findDOB($splits[1]);
            $aadhar = $this->findAadhar($splits[2]);
        } else {
            return $details = $this->dataFromText($text);
        }

        $gender = $this->findGender($text);

        $this->setName($name);
        $this->setDOB($dob);
        $this->setAadhar($aadhar);
        $this->setGender($gender);
    }

    public function findGender($text)
    {
        $gender = '';
        preg_match('/\b(Male|Female|MALE|FEMALE|male|female)\b/', $text, $matches);
        if (count($matches) > 0) {
            $gender = $matches[0];
        }
        return trim($gender);
    }

    public function findName($text)
    {
        $name = [];
        $processName = preg_replace('/\d/', '', $text); // Remove Digits
        $processName = preg_replace('/\b.{2}\b/', ' ', $processName); // Remove 2 and 1 letter words
        $processName = preg_replace('/\b.{1}\b/', ' ', $processName); // Remove 2 and 1 letter words
        $processName = $this->removeExtraWords($processName);
        $processName = preg_replace('/\s{1,}/', ' ', $processName); // Remove Duplicate space

        // Find 2 Words before and after surname
        $nameArray = explode(' ', $processName);
        foreach ($nameArray as $k => $v) {
            if ($v !== '') {
                if (preg_match('/\b' . $v . '\b/i', $this->surNames, $matches)) {
                    $s = (int)($k - 2);
                    $e = (int)($k + 3);
                    if ($s < 0)
                        $s = $k;
                    $name = array_slice($nameArray, $s, $e);
                    break;
                }
            }
        }

        $fullName = [];
        $validated = 1;

        foreach ($name as $kP => $w) {
            $original = $w;
            if ($w !== '') {
                foreach ($this->suffix as $suf) {
                    $count = 1 - strlen(trim($suf));
                    $wa = substr($w, 0, $count); // Word Amended - remove suffix e.g bhai, ben 
                }

                if ($wa!='' && preg_match('/\b' . $wa . '\b/i', $this->surNames)) {
                    $fullName[] = $original;
                } else if ($wa!='' && preg_match('/\b' . $wa . '\b/i', $this->indianName)) {
                    $fullName[] = preg_replace('/\b.{3}\b/', ' ', $original);
                } else {
                    $fullName[] = preg_replace('/\b.{3}\b/', ' ', $original);
                    $validated = 0;
                }
            }
        }
        $validated = (empty($fullName)) ? 0 : $validated;

        return trim(implode(' ', array_unique($fullName)));
        //return ['name' => trim(implode(' ',array_unique($fullName))),'validatedFromSource' => $validated];
    }

    public function findDOB($text, $position = 0)
    {
        $dob = '';
        // DOB
        preg_match('/[\s](0[1-9]|1[0-9]|2[0-9]|3(0|1))[\s-](0[1-9]|1[0-2])[\s-]\d{4}[\s]/', $text, $mD, false, $position);
        if (count($mD) > 0) {
            $dob = $mD[0];
        } else {
            // Collect Year, if found
            preg_match('/(193[4-9]|19[4-9]\d|200[0-3]|20[0-2]\d)/', $text, $mY, false, $position);
            $dob = (count($mY) > 0) ? $mY[0] : '';
        }

        return str_replace(' ','/',trim($dob));
    }

    public function findAadhar($text, $position = 0)
    {
        $aadhar = '';
        preg_match('/\d{4}[\s-]\d{4}[\s-]\d{4}/', $text, $mA, false, $position);
        if (count($mA) > 0)
            $aadhar = $mA[0];

        // Another Attempt to find aadhar from whole string, if aadhar not found on specific position
        if ($position > 0 && $aadhar == '') {
            $aadhar = $this->findAadhar($text, 0);
        }
        return trim($aadhar);
    }

    public function removeExtraWords($text)
    {
        $str = preg_replace('/\b' . $this->extraWords . '\b/i', '', $text);
        return $str;
    }

    public function printLine()
    {
        echo "\n ------------------------------- \n\n\n";
    }

    public function indianNamePrefixSuffix()
    {
        return '';
    }
}

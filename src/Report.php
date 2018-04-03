<?php
/**
 * TeamCity report formatter for PHP Code Sniffer
 *
 * PHP version 5.
 *
 * @category PHP
 * @package  PHPCS_TeamCity_Report
 * @author   Robert F.P. Ludwick <rfpludwick@gmail.com> <robertl@cdbaby.com>
 * @author   HostBaby <programmers@hostbaby.com>
 * @author   CD Baby, Inc.
 * @license  Apache 2.0
 */

// Namespacing
namespace RFPLudwick\PHPCS\Reports;

use \PHP_CodeSniffer\Reports\Report as BaseReport;
use \PHP_CodeSniffer\Files\File;

/**
 * TeamCity report formatter for PHP Code Sniffer
 *
 * PHP version 5.
 *
 * @category PHP
 * @package  PHPCS_TeamCity_Report
 * @author   Robert F.P. Ludwick <rfpludwick@gmail.com> <robertl@cdbaby.com>
 * @author   HostBaby <programmers@hostbaby.com>
 * @author   CD Baby, Inc.
 * @license  Apache 2.0
 */
class Report implements BaseReport
{
    /**
     * Temporary file which will house individual file warnings and errors
     *
     * @var resource
     */
    private $tmpfile;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Note that we are using a temp-file to track individual file results, as this will help keep PHP from hitting
        // its memory limit.
        $this->tmpfile = tmpfile();
    }

    // Destructor
    public function __destruct()
    {
        fclose($this->tmpfile);
    }

    /**
     * Generate a partial report for a single processed file.
     *
     * @inheritdoc
     */
    public function generateFileReport(
        $report,
        File $phpcsFile,
        $showSources = false,
        $width = 80
    ) {
        $filename     = $phpcsFile->getFilename();
        $warningCount = $phpcsFile->getWarningCount();
        $errorCount   = $phpcsFile->getErrorCount();

        fwrite($this->tmpfile, $this->createTeamCityLine('testSuiteStarted', [
            'name' => $filename
        ]));

        $shouldBeCounted = ($warningCount || $errorCount);

        if ($shouldBeCounted) {
            if ($warningCount) {
                $this->renderFailures($filename, $phpcsFile
                    ->getWarnings());
            }
            if ($errorCount) {
                $this->renderFailures($filename, $phpcsFile->getErrors());
            }
        } else {
            fwrite($this->tmpfile, $this->createTeamCityLine('testStarted', [
                'name' => $filename
            ]));
            fwrite($this->tmpfile, $this->createTeamCityLine('testFinished', [
                'name' => $filename
            ]));
        }

        fwrite($this->tmpfile, $this->createTeamCityLine('testSuiteFinished', [
            'name' => $filename
        ]));

        return $shouldBeCounted;
    }

    /**
     * Generate the actual report.
     *
     * @inheritdoc
     */
    public function generate(
        $cachedData,
        $totalFiles,
        $totalErrors,
        $totalWarnings,
        $totalFixable,
        $showSources = false,
        $width = 80,
        $interactive = false,
        $toScreen = true
    ) {
        echo $this->createTeamCityLine('testSuiteStarted', [
            'name' => 'PHPCS'
        ]);

        rewind($this->tmpfile);
        fpassthru($this->tmpfile);

        echo $this->createTeamCityLine('testSuiteFinished', [
            'name' => 'PHPCS'
        ]);
        echo $this->createTeamCityLine('buildStatisticValue', [
            'key' => 'PHPCS Warning Count',
            'value' => $totalWarnings
        ]);
        echo $this->createTeamCityLine('buildStatisticValue', [
            'key' => 'PHPCS Error Count',
            'value' => $totalErrors
        ]);
        echo $this->createTeamCityLine('buildStatisticValue', [
            'key' => 'PHPCS Automatically Fixable Violations Count',
            'value' => $totalFixable
        ]);
        echo $this->createTeamCityLine('buildStatisticValue', [
            'key' => 'PHPCS Files With Violations Count',
            'value' => $totalFiles
        ]);
    }

    /**
     * Creates a TeamCity report line
     *
     * @param string  $messageName   The message name
     * @param mixed[] $keyValuePairs The key=>value pairs
     *
     * @return string The TeamCity report line
     */
    private function createTeamCityLine($messageName, array $keyValuePairs)
    {
        $string = '##teamcity[' . $messageName;

        foreach ($keyValuePairs as $key => $value) {
            $string .= ' ' . $key . '=\'' . $this->escapeForTeamCity($value) . '\'';
        }

        return $string . ']' . PHP_EOL;
    }

    /**
     * Renders the failures
     *
     * @param string $filename The filename
     * @param array  $failures The failures
     *
     * @return $this
     */
    private function renderFailures($filename, array $failures)
    {
        foreach ($failures as $line => $lineFailures) {
            foreach ($lineFailures as $column => $columnFailures) {
                $testName = $filename . ':' . $line . ':' . $column;

                fwrite($this->tmpfile, $this->createTeamCityLine('testStarted', [
                    'name' => $testName
                ]));

                foreach ($columnFailures as $result) {
                    fwrite($this->tmpfile, $this->createTeamCityLine('testFailed', [
                        'name' => $testName,
                        'message' => $result['source'],
                        'details' => $result['message']
                    ]));
                }

                fwrite($this->tmpfile, $this->createTeamCityLine('testFinished', [
                    'name' => $testName
                ]));
            }
        }

        return $this;
    }

    /**
     * Escapes the given string for TeamCity output
     *
     * @param $string string The string to escape
     *
     * @return string The escaped string
     */
    private function escapeForTeamCity($string)
    {
        $replacements = [
            '#\n#' => '|n',
            '#\r#' => '|r',
            '#([\'\|\[\]])#' => '|$1'
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $string);
    }
}

<?php

if(!class_exists('SapphireTestReporter')) {
	return;
}

/**
 * Test reporter optimised for CLI (ie, plain-text) output
 *
 * @package framework
 * @subpackage testing
 */
class CliTestReporter extends SapphireTestReporter {

	/**
	 * Display error bar if it exists
	 */
	public function writeResults() {
		$passCount = 0;
		$failCount = $this->currentSession['failures'];
		$testCount = 0;
		$incompleteCount = $this->currentSession['incomplete'];
		$errorCount = $this->currentSession['errors'];

		foreach($this->suiteResults['suites'] as $suite) {
			foreach($suite['tests'] as $test) {
				$testCount++;
				switch($test['status']) {
					case TEST_INCOMPLETE: {
						$incompleteCount++;
						break;
					}
					case TEST_SUCCESS: {
						$passCount++;
						break;
					}
					case TEST_ERROR: {
						$errorCount++;
						break;
					}
					default: {
						$failCount++;
						break;
					}
				}
			}
		}

		echo "\n\n";
		$breakages = $errorCount + $failCount;
		if ($breakages == 0 && $incompleteCount > 0) {
			echo SS_Cli::text(" OK, BUT INCOMPLETE TESTS! ", "black", "yellow");
		} elseif ($breakages == 0) {
			echo SS_Cli::text(" ALL TESTS PASS ", "black", "green");
		}  else {
			echo SS_Cli::text(" AT LEAST ONE FAILURE ", "black", "red");
		}

		echo sprintf("\n\n%d tests run: %s, %s, and %s\n", $testCount, SS_Cli::text("$passCount passes"),
			SS_Cli::text("$breakages failures"), SS_Cli::text("$incompleteCount incomplete"));

		echo "Maximum memory usage: " . number_format(memory_get_peak_usage()/(1024*1024), 1) . "M\n\n";

		// Use sake dev/tests/all --showslow to show slow tests
		if((isset($_GET['args']) && is_array($_GET['args']) && in_array('--showslow', $_GET['args']))
				|| isset($_GET['showslow'])) {

			$avgSpeed = round(array_sum($this->testSpeeds) / count($this->testSpeeds), 3);
			echo "Slow tests (more than the average $avgSpeed seconds):\n";

			arsort($this->testSpeeds);
			foreach($this->testSpeeds as $k => $v) {
				// Ignore below-average speeds
				if($v < $avgSpeed) break;

				echo " - $k: " . round($v,3) . "\n";
			}
		}
		echo "\n";
	}

	public function endTest( PHPUnit_Framework_Test $test, $time) {
		// Status indicator, a la PHPUnit
		switch($this->currentTest['status']) {
			case TEST_FAILURE: echo SS_Cli::text("F","red", null, true); break;
			case TEST_ERROR: echo SS_Cli::text("E","red", null, true); break;
			case TEST_INCOMPLETE: echo SS_Cli::text("I","yellow"); break;
			case TEST_SUCCESS: echo SS_Cli::text(".","green"); break;
			default: echo SS_Cli::text("?", "yellow"); break;
		}

		static $colCount = 0;
		$colCount++;
		if($colCount % 80 == 0) echo " - $colCount\n";

		$this->writeTest($this->currentTest);
		parent::endTest($test, $time);
	}

	protected function addStatus($status, $message, $exception, $trace) {
		if(!$this->currentTest && !$this->currentSuite) {
			// Log non-test errors immediately
			$statusResult = array(
				'status' => $status,
				'message' => $message,
				'exception' => $exception,
				'trace' => $trace
			);
			$this->writeTest($statusResult);
		}
		parent::addStatus($status, $message, $exception, $trace);
	}

	protected function writeTest($test) {
		if ($test['status'] != TEST_SUCCESS) {
			$filteredTrace = array();
			foreach($test['trace'] as $item) {
				if(isset($item['file'])
						&& strpos($item['file'], 'PHPUnit/Framework') === false
						&& !isset($item['class'])) {
					$filteredTrace[] = $item;
				}

				if(isset($item['class']) && isset($item['function']) && $item['class'] == 'PHPUnit_Framework_TestSuite'
						&& $item['function'] == 'run') {
					break;
				}

			}

			$color = ($test['status'] == 2) ? 'yellow' : 'red';
			echo "\n" . SS_Cli::text($test['name'] . "\n". $test['message'] . "\n", $color, null);
			echo SS_Backtrace::get_rendered_backtrace($filteredTrace, true);
			echo "--------------------\n";
		}
	}

}

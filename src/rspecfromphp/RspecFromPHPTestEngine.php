<?php

/**
 * PHPUnit wrapper
 *
 * To use, set unit.engine in .arcconfig, or use --engine flag
 * with arc unit. Currently supports only class & test files
 * (no directory support).
 * To use custom phpunit configuration, set phpunit_config in
 * .arcconfig (e.g. app/phpunit.xml.dist).
 *
 * @group unitrun
 */
final class RspecFromPHPTestEngine extends ArcanistBaseUnitTestEngine {

  private $configFile;
  private $phpunitBinary = 'phpunit';
  private $affectedTests;
  private $projectRoot;

  public function run() {

    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
    $rspec_command = sprintf(
      "cd %s; bundle exec rspec spec --format json -o result.json",
      $this->projectRoot
    );
    $cucumber_command = sprintf(
      "cd %s; bundle exec cucumber --format json -o cucumber.json",
      $this->projectRoot
    );
    echo "Running rspec...\n";
    exec($rspec_command);
    echo "Running cucumber... this might take a while...";
    exec($cucumber_command);

    $rspec_results = $this->parseRspecTestResults();
    $cucumber_results = $this->parseCucumberTestResults();

    exec("rm result.json");
    exec("rm cucumber.json");
    return array_merge($rspec_results, $cucumber_results);
  }

  /**
   * Parse test results from json report
   */
  private function parseCucumberTestResults() {
    $test_results = file_get_contents("cucumber.json");
    $features = json_decode($test_results);
    $results = array();
    foreach ($features as $feature) {
      foreach ($feature->elements as $element) {
        $result = new ArcanistUnitTestResult();
        $result->setName($name);
        $user_data = sprintf("\n%s:%s", $feature->uri, $element->line);
        $result->setUserData($user_data);
        $result_and_duration = $this->checkAllSteps($element->steps);
        $result->setResult($result_and_duration[0]);
        $result->setDuration($result_and_duration[1]);
        $results[] = $result;
      }

    }

    return $results;
  }

  private function checkAllSteps($steps) {
    $status = ArcanistUnitTestResult::RESULT_PASS;
    $duration = 0;
    foreach ($steps as $step) {
      if ($step->result->status != 'passed') {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
      }
      $duration += $step->result->duration;
    }
    $ret = array($status, $duration);
    return $ret;
  }

  /**
   * Parse test results from json report
   */
  private function parseRspecTestResults() {
    $test_results = file_get_contents("result.json");
    $test_results = json_decode($test_results);
    $examples = $test_results->examples;
    $results = array();
    foreach ($examples as $example) {
      $name = explode(' ',trim($example->full_description));
      $name = $name[0];
      $user_data = sprintf("\n%s:%s", $example->file_path, $example->line_number);
      $result = new ArcanistUnitTestResult();
      $result->setName($name);
      $result->setResult($this->getStatus($example->status));
      $result->setUserData($user_data);

      $results[] = $result;
    }

    return $results;
  }

  private function getStatus($status) {
    if($status == 'passed') {
      return ArcanistUnitTestResult::RESULT_PASS;
    }
    return ArcanistUnitTestResult::RESULT_FAIL;
  }
}

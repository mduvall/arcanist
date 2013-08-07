<?php

/**
 * Uses "ESLint" to detect errors and potential problems in JavaScript code.
 * To use this linter, you must install eslint through NPM (Node Package
 * Manager). You can configure different ESLint options on a per-file basis.
 *
 * If you have NodeJS installed you should be able to install eslint with
 * ##npm install eslint -g## (don't forget the -g flag or NPM will install
 * the package locally). If your system is unusual, you can manually specify
 * the location of eslint and its dependencies by configuring these keys in
 * your .arcconfig:
 *
 *   lint.eslint.prefix
 *   lint.eslint.bin
 *
 * If you want to configure custom options for your project, create a JSON
 * file with these options and add the path to the file to your .arcconfig
 * by configuring this key:
 *
 *   lint.eslint.config
 *
 * For more options see https://github.com/nzakas/eslint.
 *
 * @group linter
 */
final class ArcanistESLintLinter extends ArcanistLinter {

  const ESLINT_ERROR = 1;

  public function getLinterName() {
    return 'ESLint';
  }

  public function getLintSeverityMap() {
    return array(
      self::ESLINT_ERROR => ArcanistLintSeverity::SEVERITY_ERROR
    );
  }

  public function getLintNameMap() {
    return array(
      self::ESLINT_ERROR => "ESLint Error"
    );
  }

  public function getESLintOptions() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $options = '--format jslint-xml';
    $config = $working_copy->getConfig('lint.eslint.config');

    if ($config !== null) {
      $config = Filesystem::resolvePath(
        $config,
        $working_copy->getProjectRoot());

      if (!Filesystem::pathExists($config)) {
        throw new ArcanistUsageException(
          "Unable to find custom options file defined by ".
          "'lint.jshint.config'. Make sure that the path is correct.");
      }

      $options .= ' --config '.$config;
    }

    return $options;
  }

  private function getESLintPath() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.eslint.prefix');
    $bin = $working_copy->getConfig('lint.eslint.bin');

    if ($bin === null) {
      $bin = "eslint";
    }

    if ($prefix !== null) {
      $bin = $prefix."/".$bin;

      if (!Filesystem::pathExists($bin)) {
        throw new ArcanistUsageException(
          "Unable to find ESLint binary in a specified directory. Make sure ".
          "that 'lint.eslint.prefix' and 'lint.eslint.bin' keys are set ".
          "correctly. If you'd rather use a copy of ESLint installed ".
          "globally, you can just remove these keys from your .arcconfig");
      }

      return $bin;
    }

    if (!Filesystem::binaryExists($bin)) {
      throw new ArcanistUsageException(
        "ESlint does not appear to be installed on this system. Install it ".
        "(e.g., with 'npm install eslint -g') or configure ".
        "'lint.eslint.prefix' in your .arcconfig to point to the directory ".
        "where it resides.");
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    if (!$this->isCodeEnabled(self::ESLINT_ERROR)) {
      return;
    }

    $eslint_bin = $this->getESLintPath();
    $eslint_options = $this->getESLintOptions();
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $futures[$path] = new ExecFuture(
        "%s %C %s",
        $eslint_bin,
        $eslint_options,
        $filepath
      );
    }

    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->results[$path] = $future->resolve();
    }
  }

  public function lintPath($path) {
    list($rc, $stdout, $stderr) = $this->results[$path];

    $xml_result = new SimpleXMLElement($stdout);
    $file_name = (string) $xml_result->file['name'];

    $errors = array();
    foreach ($xml_result->file->issue as $issue) {
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setName($issue['reason']);
      $message->setDescription("Evidence: " . $issue['evidence']);
      $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
      $message->setCode(ArcanistLintSeverity::SEVERITY_ERROR);

      $this->addLintMessage($message);
    }

    foreach ($errors as $err) {
      $this->raiseLintAtLine(
        $err['line'],
        $err['col'],
        self::ESLINT_ERROR,
        $err['reason']);
    }
  }
}

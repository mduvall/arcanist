<?php

final class ESLintEngine extends ArcanistLintEngine {

  public function buildLinters() {

    $paths = $this->getPaths();
    $eslinter = new ArcanistESLintLinter();

    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path)) {
        unset($paths[$key]);
      }
    }

    foreach ($paths as $path) {
      if (!preg_match('/\.js$/', $path)) {
        continue;
      }

      $eslinter->addPath($path);
    }

    return array(
      $eslinter,
    );
  }

  public function run() {
    $linters = $this->buildLinters();

    // Lint all the code first...
    foreach ($linters as $linter_name => $linter) {
      $linter->setEngine($this);
      if (!is_string($linter_name)) {
        $linter_name = get_class($linter);
      }
      $paths = $linter->getPaths();

      $linter->willLintPaths($paths);
      // Go through each path and collect the results...
      foreach ($paths as $path) {
        $linter->willLintPath($path);
        $linter->lintPath($path);
        if ($linter->didStopAllLinters()) {
          $this->stopped[$path] = $linter_name;
        }
      }
    }

    // Go through all the messages and add the message to the result set
    foreach ($linters as $linter) {
      foreach ($linter->getLintMessages() as $message) {
        $result = $this->getResultForPath($message->getPath());
        $result->addMessage($message);
      }
    }
  }
}

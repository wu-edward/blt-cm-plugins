<?php

namespace WuEdward\BltCm\Blt\Plugin\Commands;

use Acquia\Blt\Robo\BltTasks;
use Acquia\Blt\Robo\Exceptions\BltException;
use Robo\Contract\VerbosityThresholdInterface;

/**
 * Provides Acquia BLT commands to help with config management.
 */
class BltCmCommands extends BltTasks {

  /**
   * Adds service to sync Drupal config entity UUIDs from sync storage.
   *
   * @command cm:uuid:sync:init
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   *   On failure.
   */
  public function uuidSyncInit() {
    $service_definition_path = $this->getServiceDefinitionPath('installation_services.yml');
    $service_definition_snippet = <<<EOT

// Add service definitions to be used during Drupal installation.
if (\Drupal\Core\Installer\InstallerKernel::installationAttempted()) {
  \$settings['container_yamls'][] = DRUPAL_ROOT . '/{$service_definition_path}';
}

EOT;

    $result = $this->addServiceDefinitionToSettingsFile($service_definition_snippet);
    if (!$result->wasSuccessful()) {
      throw new BltException('Unable to add service definition(s) to sync config entity UUIDs during site installation.');
    }

    $this->say('<info>Successfully added service definition(s) to sync config entity UUIDs during site installation.</info>');
  }

  /**
   * Adds service to allow for profiles splits during site installation.
   *
   * @command cm:profile:split:init
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   *   On failure.
   */
  public function profileSplitInit() {
    $installation_service_definition_path = $this->getServiceDefinitionPath('profile_split_installation_services.yml');
    $service_definition_path = $this->getServiceDefinitionPath('profile_split_services.yml');
    $service_definition_snippet = <<<EOT

// Add services to be able to use and enable config splits based on separate
// profiles when installing Drupal from existing configuration.
if (\Drupal\Core\Installer\InstallerKernel::installationAttempted()) {
  \$settings['container_yamls'][] = DRUPAL_ROOT . '/{$installation_service_definition_path}';
}
\$settings['container_yamls'][] = DRUPAL_ROOT . '/{$service_definition_path}';

EOT;

    $result = $this->addServiceDefinitionToSettingsFile($service_definition_snippet);
    if (!$result->wasSuccessful()) {
      throw new BltException('Unable to add service definition(s) to allow profile splits during site installation.');
    }

    $this->say('<info>Successfully added service definition(s) to allow profile splits during site installation.</info>');
  }

  /**
   * Get the relative path from the Drupal docroot to this package root.
   *
   * @param string $yaml_file_name
   *   The YAML service definition file name.
   *
   * @return string
   *   The relative from the Drupal docroot to this package root.
   */
  protected function getServiceDefinitionPath($yaml_file_name) {
    // Note that package root is four directories up from current.
    $service_definition_path = $this->getInspector()
      ->getFs()
      ->makePathRelative(__DIR__ . '/../../../../' . $yaml_file_name, $this->getConfigValue('docroot'));
    return trim($service_definition_path, '/');
  }

  /**
   * Appends snippet that adds service YAML definitions to settings.
   *
   * @param string $service_definition_snippet
   *   PHP snippet text that will be appended to global.settings.php file.
   *
   * @return \Robo\Result
   *   The task run result.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   *   On error writing to file.
   */
  protected function addServiceDefinitionToSettingsFile($service_definition_snippet) {
    $global_settings_path = $this->ensureGlobalSettingsFile();

    // Append code to add the service definition to the bottom of the global
    // settings file.
    $result = $this->taskWriteToFile($global_settings_path)
      ->text($service_definition_snippet)
      ->append(TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    $this->beautifyGlobalSettingsFile();
    return $result;
  }

  /**
   * Checks for presence of global.settings.php file in docroot.
   *
   * If global.settings.php does not exist, then an attempt to copy from the
   * default.global.settings.php file in the same directory will be made. If
   * the default.global.settings.php file does not exist in the docroot, then
   * user will be prompted whether build the settings files.
   *
   * @return string
   *   The path to the global.settings.php file on success.
   *
   * @throws \Acquia\Blt\Robo\Exceptions\BltException
   *   On any failures.
   */
  protected function ensureGlobalSettingsFile() {
    $global_settings_path = $this->getConfigValue('docroot') . '/sites/settings/global.settings.php';
    if (!file_exists($global_settings_path)) {
      // Check whether default.global.settings.php exists in the docroot.
      $default_global_settings_path = $this->getConfigValue('docroot') . '/sites/settings/default.global.settings.php';

      if (!file_exists($default_global_settings_path)) {
        // If default file not in docroot, provide prompt to user to build the
        // settings files.
        $filepath = $this->getInspector()
          ->getFs()
          ->makePathRelative($this->getConfigValue('docroot') . "/sites/settings", $this->getConfigValue('repo.root'));
        $this->say(sprintf('The default.global.settings.php file does not exist in %s. You may need to run blt source:build:settings.', $filepath));
        $confirm = $this->confirm('Should BLT attempt to run that now? This may overwrite files.');
        if ($confirm) {
          $this->invokeCommand('source:build:settings');
        }
        else {
          throw new BltException('Unable to create global.settings.php file.');
        }
      }

      // Copy the default global settings file to global.settings.php.
      $result = $this->taskFilesystemStack()
        ->copy($default_global_settings_path, $global_settings_path)
        ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
        ->run();

      if (!$result->wasSuccessful()) {
        throw new BltException('Unable to create global.settings.php file.');
      }
    }

    return $global_settings_path;
  }

  /**
   * Runs phpcbf to fix the global.settings.php based on user interaction.
   *
   * For convenience, the UUID sync snippet uses a fully namespaced class, so
   * running phpcbf can correctly add the use statement.
   */
  protected function beautifyGlobalSettingsFile() {
    $confirm = $this->confirm('Use phpcbf to fix and beautify the global.settings.php file per coding standards?');
    if ($confirm) {
      try {
        $global_settings_path = $this->ensureGlobalSettingsFile();
        $bin = $this->getConfigValue('composer.bin');
        $result = $this->taskExec("$bin/phpcbf $global_settings_path")
          ->run();
      }
      catch (BltException $e) {
        $this->say('<warning>PHPCBF failed.</warning>');
      }

      $exit_code = $result->getExitCode();
      // - 0 indicates that no fixable errors were found.
      // - 1 indicates that all fixable errors were fixed correctly.
      // - 2 indicates that PHPCBF failed to fix some of the fixable errors.
      // - 3 is used for general script execution errors.
      switch ($exit_code) {
        case 0:
          $this->say('<info>No fixable errors were found, and so nothing was fixed.</info>');
          break;

        case 1:
          $this->say('<comment>Please note that exit code 1 does not indicate an error for PHPCBF.</comment>');
          $this->say('<info>All fixable errors were fixed correctly. There may still be errors that could not be fixed automatically.</info>');
          break;

        case 2:
          $this->say('<warning>PHPCBF failed to fix some of the fixable errors it found.</warning>');
          break;

        default:
          $this->say('<warning>PHPCBF failed.</warning>');
          break;
      }
    }
  }

}

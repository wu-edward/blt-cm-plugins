<?php

namespace WuEdward\BltCm\Drupal\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Transform import config data for profile property in core.extension object.
 *
 * Effectively ignores the core.extension profile being imported.
 */
class ProfileSplitConfigImportTransformSubscriber implements EventSubscriberInterface {

  /**
   * The active config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * The profile extension list.
   *
   * @var \Drupal\Core\Extension\ProfileExtensionList
   */
  protected $profileExtensionList;

  /**
   * The machine name of installation profile used on the site.
   *
   * @var string
   */
  protected $installProfile;

  /**
   * Contructs ProfileSplitConfigImportTransformSubscriber object.
   *
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active config storage.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension list service.
   * @param string $install_profile
   *   The machine name of installation profile used on the site.
   */
  public function __construct(StorageInterface $active_storage, ProfileExtensionList $profile_extension_list, $install_profile) {
    $this->activeStorage = $active_storage;
    $this->profileExtensionList = $profile_extension_list;
    $this->installProfile = $install_profile;
  }

  /**
   * Handles the config import tranform event.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The event object.
   */
  public function onStorageTransformImport(StorageTransformEvent $event) {
    $storage = $event->getStorage();
    if (($extension_data = $storage->read('core.extension'))) {
      $extension_data = $this->syncActiveProfile($extension_data);
      $storage->write('core.extension', $extension_data);
    }
  }

  /**
   * Set the profile data read from import storage to use existing active data.
   *
   * @param array $data
   *   Config data read from import storage for core.extension config object.
   *
   * @return array
   *   Config data for core.extension config object with profile synced to
   *   active storage.
   */
  protected function syncActiveProfile(array $data) {
    $replaced_profile = $data['profile'] ?? NULL;
    if ($replaced_profile && $replaced_profile !== $this->installProfile) {
      $active_data = $this->activeStorage->read('core.extension');

      // Remove any data enabling profile and ancestors read from original sync
      // storage.
      $replaced_profile_names = array_keys($this->profileExtensionList->getAncestors($replaced_profile));
      foreach ($replaced_profile_names as $profile_name) {
        if (!isset($active_data['module'][$profile_name])) {
          unset($data['module'][$profile_name]);
        }
      }

      // Set 'profile' property to the installed profile.
      $data['profile'] = $this->installProfile;

      // Set the profile and its ancestors' status to match active config.
      $profiles = $this->profileExtensionList->getAncestors($this->installProfile);
      $installation_attempted = InstallerKernel::installationAttempted();
      foreach ($profiles as $profile_name => $profile) {
        if ($installation_attempted) {
          $data['module'][$profile_name] = $profile->weight ?? 0;
        }
        elseif (isset($active_data['module'][$profile_name])) {
          $data['module'][$profile_name] = $active_data['module'][$profile_name];
        }
      }

      // Sort modules by weight first and then name.
      $data['module'] = module_config_sort($data['module']);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Set a low priority so it runs last.
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => [['onStorageTransformImport', -1000]],
    ];
  }

}

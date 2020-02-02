<?php

namespace WuEdward\BltCm\Drupal\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Extension\ProfileExtensionList;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Transform export config data for profile property in core.extension object.
 *
 * Effectively ignores the core.extension profile being exported.
 */
class ProfileSplitConfigExportTransformSubscriber implements EventSubscriberInterface {

  /**
   * The sync config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $syncStorage;

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
   * Constructs ProfileSplitConfigExportTransformSubscriber object.
   *
   * @param \Drupal\Core\Config\StorageInterface $sync_storage
   *   The sync config storage.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension list service.
   * @param string $install_profile
   *   The machine name of installation profile used on the site.
   */
  public function __construct(StorageInterface $sync_storage, ProfileExtensionList $profile_extension_list, $install_profile) {
    $this->syncStorage = $sync_storage;
    $this->profileExtensionList = $profile_extension_list;
    $this->installProfile = $install_profile;
  }

  /**
   * Handles the config export tranform event.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The event object.
   */
  public function onStorageTransformExport(StorageTransformEvent $event) {
    $storage = $event->getStorage();
    if (($extension_data = $storage->read('core.extension'))) {
      $extension_data = $this->syncExportedProfile($extension_data);
      $storage->write('core.extension', $extension_data);
    }
  }

  /**
   * Set the profile data read from export storage to use existing sync data.
   *
   * @param array $data
   *   Config data read from export storage for core.extension config object.
   *
   * @return array
   *   Config data for core.extension config object with profile synced to
   *   sync storage.
   */
  protected function syncExportedProfile(array $data) {
    // Check what the sync storage config is for `core.extension:profile`.
    $current_sync_data = $this->syncStorage->exists('core.extension') ? $this->syncStorage->read('core.extension') : [];
    if (!isset($current_sync_data['profile']) ||
        ($current_sync_data['profile'] === $this->installProfile)) {

      // If profile does not exist in sync storage or is the same as data to be
      // written, leave $data as is.
      return $data;
    }

    // Get the ancestor profiles for the profile in `$data['profile']`, and make
    // sure to remove them from the enabled module extensions in
    // `$data['module']`.
    $active_profiles = $this->profileExtensionList->getAncestors($data['profile']);
    foreach ($active_profiles as $profile_name => $profile) {
      if (!isset($current_sync_data['module'][$profile_name])) {
        unset($data['module'][$profile_name]);
      }
    }

    // Add the current sync data profile and its ancestors to the enabled module
    // extensions.
    $sync_profiles = $this->profileExtensionList->getAncestors($current_sync_data['profile']);
    foreach ($sync_profiles as $profile_name => $profile) {
      if ($current_sync_data['module'][$profile_name]) {
        $data['module'][$profile_name] = $current_sync_data['module'][$profile_name];
      }
    }
    // Sort the enabled modules first by weight and then by name.
    $data['module'] = module_config_sort($data['module']);

    // Finally, set the profile to match the current sync data.
    $data['profile'] = $current_sync_data['profile'];

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Set a low priority so it runs last.
      ConfigEvents::STORAGE_TRANSFORM_EXPORT => [['onStorageTransformExport', -1000]],
    ];
  }

}

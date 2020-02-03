<?php

namespace BltCm\Drupal\Config;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ProfileExtensionList;

/**
 * Service that overrides the configuration sync storage.
 *
 * Effectively ignores the core.extension profile in sync storage.
 */
class ProfileSplitSyncStorage implements StorageInterface {

  /**
   * The config sync storage being decorated.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

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
   * ProfileSplitSyncStorage constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The config sync storage being decorated.
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active config storage.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension list service.
   * @param string $install_profile
   *   The machine name of installation profile used on the site.
   */
  public function __construct(StorageInterface $storage, StorageInterface $active_storage, ProfileExtensionList $profile_extension_list, $install_profile) {
    $this->storage = $storage;
    $this->activeStorage = $active_storage;
    $this->profileExtensionList = $profile_extension_list;
    $this->installProfile = $install_profile;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return $this->storage->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $data = $this->storage->read($name);
    if (!$data || ($name !== 'core.extension')) {
      return $data;
    }

    return $this->syncActiveProfileRead($data);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $list = $this->storage->readMultiple($names);
    if (empty($list['core.extension'])) {
      return $list;
    }

    $list['core.extension'] = $this->syncActiveProfileRead($list['core.extension']);

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    if (($name === 'core.extension') &&
        !empty($data['profile'])) {

      $data = $this->syncActiveProfileWrite($data);
    }

    return $this->storage->write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    return $this->storage->delete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    return $this->storage->rename($name, $new_name);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    return $this->storage->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    return $this->storage->deleteAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static($this->storage->createCollection($collection), $this->activeStorage, $this->profileExtensionList, $this->installProfile);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

  /**
   * Set the profile data read from sync storage to use existing active data.
   *
   * @param array $data
   *   Config data read from sync storage for core.extension config object.
   *
   * @return array
   *   Config data for core.extension config object with profile synced to
   *   active storage.
   */
  protected function syncActiveProfileRead(array $data) {
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
      $installation_attempted = drupal_installation_attempted();
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
   * Set the profile data to write to sync storage to use existing sync data.
   *
   * @param array $data
   *   Config data to be written to sync storage for core.extension config.
   *
   * @return mixed
   *   Config data for core.extension that matches the existing sync config
   *   for the installed profile and its ancestors.
   */
  protected function syncActiveProfileWrite(array $data) {
    // Check what the sync storage config is for `core.extension:profile`.
    $current_sync_data = $this->storage->exists('core.extension') &&
      $this->storage->read('core.extension');
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

}

<?php

namespace BltCm\Drupal\Config;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Extension\ProfileExtensionList;

/**
 * Service that overrides the configuration sync storage.
 *
 * This uses the installed profile's 'config/sync' directory as the directory
 * for the `config.storage.sync` service.
 */
class ProfileSyncStorage implements StorageInterface {

  /**
   * Config file storage based on the profile directory.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * ProfileSyncStorage constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The config sync storage being decorated.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension list service.
   * @param string $install_profile
   *   The machine name of installation profile used on the site.
   */
  public function __construct(StorageInterface $storage, ProfileExtensionList $profile_extension_list, $install_profile) {
    $extension = $profile_extension_list->get($install_profile);
    $directory = DRUPAL_ROOT . "/{$extension->getPath()}/config/sync";
    $this->storage = is_dir($directory) ? new FileStorage($directory) : $storage;
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
    return $this->storage->read($name);

  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    return $this->storage->readMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
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
    return $this->storage->createCollection($collection);
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

}

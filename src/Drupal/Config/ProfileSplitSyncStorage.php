<?php

namespace BltCm\Drupal\Config;

use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageInterface;

/**
 * Service that overrides the configuration sync storage.
 *
 * Should be used only during site installation. Transform the sync storage so
 * that the import transform event is dispatched and the import data can be
 * altered by subscribers.
 *
 * @see \BltCm\Drupal\EventSubscriber\ProfileSplitConfigImportTransformSubscriber
 */
class ProfileSplitSyncStorage implements StorageInterface {

  /**
   * The config sync storage being decorated.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * ProfileSplitSyncStorage constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The config sync storage being decorated.
   * @param \Drupal\Core\Config\ImportStorageTransformer $import_storage_transfomer
   *   The config import storage transformer.
   */
  public function __construct(StorageInterface $storage, ImportStorageTransformer $import_storage_transfomer) {
    $this->storage = $import_storage_transfomer->transform($storage);
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

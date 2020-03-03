<?php

namespace BltCm\Drupal\Config;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Site\Settings;

/**
 * Class that decorates module_handler service.
 *
 * If this service decorator is added to the container during Drupal install,
 * an event will be dispatched on when invoking the `create` hook for config
 * entities. Note that this will not cover any cases where entity create is
 * somehow performed without using ::invoke() or ::invokeAll().
 *
 * @see installation_services.yml
 * @see \Drupal\Core\Entity\EntityStorageBase::create()
 */
class SyncConfigEntityUuidModuleHandler extends ModuleHandler {

  /**
   * {@inheritdoc}
   */
  public function invoke($module, $hook, array $args = []) {
    $this->syncConfigEntityUuidOnCreate($hook, $args);
    return parent::invoke($module, $hook, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function invokeAll($hook, array $args = []) {
    $this->syncConfigEntityUuidOnCreate($hook, $args);
    return parent::invokeAll($hook, $args);
  }

  /**
   * Helper function that acts on entity create hook.
   *
   * If a config entity is created, and not in config sync, check the config
   * sync storage to see if the config entity data exists there with a UUID.
   * If it does, set the entity UUID to the sync data before it is saved.
   *
   * This will prevent config entities from being deleted and recreated when
   * running configuration sync after Drupal install because of mismatching
   * UUIDs. Only syncing UUIDs here to avoid having to deal with any altered
   * dependencies with data in config sync.
   *
   * @param string $hook
   *   The name of the hook to invoke.
   * @param array $args
   *   Arguments to pass to the hook.
   */
  protected function syncConfigEntityUuidOnCreate($hook, array $args) {
    if ($hook === 'entity_create' &&
        ($entity = reset($args)) &&
        ($entity instanceof ConfigEntityInterface) &&
        !$entity->isSyncing()) {

      /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
      $entity_type = $entity->getEntityType();
      $config_name = $entity_type->getConfigPrefix() . '.' . $entity->id();
      $uuid_key = $entity_type->getKey('uuid');
      // Getting sync storage directory this way instead injecting
      // `config.sync.storage` to reduce chance of possible circular service
      // dependencies.
      $sync_storage = new FileStorage(Settings::get('config_sync_directory'));
      if ($uuid_key &&
          $sync_storage->exists($config_name) &&
          ($data = $sync_storage->read($config_name)) &&
          isset($data[$uuid_key])) {

        $entity->set($uuid_key, $data[$uuid_key]);
      }
    }
  }

}

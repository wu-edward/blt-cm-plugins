<?php

namespace BltCm\Drupal\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Enables the config split with the active profile name.
 */
class ProfileSplitConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * The profile config split config name.
   *
   * @var string
   */
  protected $profileSplitConfigName;

  /**
   * ProfileSplitConfigOverrides constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param string $install_profile
   *   The active install profile.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $install_profile) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $config_split_entity_type */
    $config_split_entity_type = $entity_type_manager->getDefinition('config_split', FALSE);
    // Try getting the prefix from the config_split entity definition, otherwise
    // just use a default.
    $prefix = $config_split_entity_type ? $config_split_entity_type->getConfigPrefix() : 'config_split.config_split';
    $this->profileSplitConfigName = $prefix . '.' . $install_profile;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    // Enable the config split for the active profile if it exists.
    if (in_array($this->profileSplitConfigName, $names)) {
      $overrides[$this->profileSplitConfigName]['status'] = TRUE;
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'BltCmProfileSplitOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}

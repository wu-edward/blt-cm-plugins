Acquia BLT Configuration Management Plugins
====

This is an [Acquia BLT](https://github.com/acquia/blt) plugin that provides some additional configuration management commands. Generally, each commands injects services into Drupal by making sure there is an existing `docroot/sites/settings/global.settings.php` file and appending a line such as:
```php
$settings['container_yamls'][] = {PATH TO SERVICE DEFINITION .YML file}
```

The YAML files exist in this repo root and provide services definitions using classes declared in the `/src/Drupal` directory.

None of the commands check for the presence of any of the service definitions already in the `global.settings.php` file, so some care should be exercised to make sure sure that the service definitions are are appended twice. The service definitions are also not necessarily meant to work together.

The plugin also provides prompts for a user to add any of the services when running `blt source:build:settings`.

Drupal 8.8+ is supported on the `master` branch; Drupal 8.7 on the `d87` branch.

## Installation

User composer to install.

- Add the following to `repositories` in `composer.json`:
```json
"blt-cm-plugins": {
  "type": "vcs",
  "url": "git@github.com:wu-edward/blt-cm-plugins.git"
}
```

- Run `composer require wu-edward/blt-cm-plugins:dev-master`

## Commands

### `blt cm:uuid:sync:init`
The purpose of this command is to create config entities with UUIDs matching the UUIDs previously exported to sync storage.

This command adds code to `global.settings.php` that will add a service definition (in `installation_services.yml`) only during site install. The service definition is a service decorator for the `module_handler` service, and overrides it such that when the `entity_create` hooks are invoked, any configuration entities being created will have their UUIDs synced to the data in the `config/sync` file storage. Doing so during site installation, when not installing from existing config, greatly reduces the number of config entities that get created during site installation only to be deleted and recreated during configuration sync in the next step of the `blt setup` process.

### `blt cm:profile:split:init`
The purpose of this command is to allow for profile splits, even when a site is being installed from config.

#### D8.8+
This command adds code to `global.settings.php` that will add a service definition (in `profile_split_installation_services.yml`) only during site install, as well as service definitions (in `profile_split_services.yml`) that persist even after installation.

The service injected during site installation overrides the `config.storage.sync` service, so that config storage
transform events are dispatched when the site is being installed from configuration. Doing so will allow the config import transform event subscriber to be invoked during site install.

The other services defined are:
- **Subscriber for the config import transform event**: This event subscriber essentially changes the config data being imported, so that the profile definition in sync storage is ignored and does not override the profile saved in active config storage.

- **Subscriber for the config export transform event**: This event subscriber essentially changes hte config data being exported, so that the profile in active storage is ignored and not written out to sync storage.

- **Config override service that sets the status of the `config_split` entity**: This config override will set the status of the `config_split` entity with the same ID as the active profile to TRUE, thus enabling the split based on the profile.

#### D8.7
This command adds code to `global.settings.php` that will add service definitions (in `profile_split_services.yml`) that will be available during site installation and persist afterwards.

The services defined are:
- **Service decorator for `config.storage.sync` service**: Overrides the config sync storage service to effectively ignore the `profile` property in `core.extension.`yml during import and export. Because D8.7 does not have the confirm transformation event, all of the ignore logic is handled in the storage service.
- **Config override service that sets the status of the `config_split` entity**: This config override will set the status of the `config_split` entity with the same ID as the active profile to TRUE, thus enabling the split based on the profile.

### `blt cm:profile:sync:set`
The purpose of this command is to set the active profile's `config/sync` directory to be the base of configuration sync storage.

This command adds code to `global.settings.php` that will add a service definition (in `profile_config_sync_services.yml`). The service definition is a service decorator for the `config.stroage.sync` service that sets the directory to the `config/sync` directory of the active profile, if the directory exists. It also adds a blank `core.extension.yml` file to the repo root `config/sync` directory, so that BLT will still run configuration sync. Doing so will allow users to set up separate profiles in a codebase, each with its separate configuration that can be used during site installation and afterwards to sync configuration to and from.

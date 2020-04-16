<?php

namespace Drupal\oe_newsroom_connector\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\oe_newsroom_connector\UniverseManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base class for Newsroom processor plugins.
 */
abstract class NewsroomProcessorBase extends PluginBase implements NewsroomProcessorInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The universe manager.
   *
   * @var \Drupal\oe_newsroom_connector\UniverseManager
   */
  protected $universeManager;

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationPluginManager;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    UniverseManagerInterface $universeManager,
    MigrationPluginManager $migrationPluginManager,
    LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->universeManager = $universeManager;
    $this->migrationPluginManager = $migrationPluginManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('oe_newsroom_connector.universe_manager'),
      $container->get('plugin.manager.migration'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function redirect($newsroom_id) {
    $entity = $this->getEntityByNewsroomId($newsroom_id);
    if (!empty($entity)) {
      $response = new RedirectResponse($entity->toUrl()->toString());
      $response->send();
    }
    else {
      throw new NotFoundHttpException();
    }
  }


  private function getEntityByNewsroomId($newsroom_id) {
    $entity = NULL;
    $definition = $this->getPluginDefinition();
    $items = $this->entityTypeManager
      ->getStorage($definition['content_type'])
      ->loadByProperties([
        'field_newsroom_id' => $newsroom_id,
        $definition['bundle_field'] => $definition['bundle'],
      ]);

    if ($item = reset($items)) {
      $entity = $item;
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityUrl($newsroom_id = NULL) {
    $params = [];
    $definition = $this->getPluginDefinition();
    if (!empty($newsroom_id)) {
      $params[$definition['import_segment']] = $newsroom_id;
    }

    // @TODO move it to a separate class.
    if ($this->getPluginId() == 'newsroom_item') {
      $config = $this->universeManager->getConfig();
      if ($config->get('subsite')) {
        $params['subsite'] = $config->get('subsite');
      }
    }

    $params['t'] = time();

    return $this->universeManager->buildUrl($definition['import_script'], $params);
  }

  /**
   * {@inheritdoc}
   */
  public function import($newsroom_id = NULL) {
    $url = $this->getEntityUrl($newsroom_id);
    $this->runImport($url);
  }

  /**
   * {@inheritdoc}
   */
  public function runImport(Url $url) {

    $definition = $this->getPluginDefinition();
    if (empty($definition['migrations'])) {
      return;
    }

    foreach ($definition['migrations'] as $migration_id) {
      $this->runMigration($migration_id, $url);

      // Run translations migrations.
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $language) {
        $language_id = $language->getId();

        // We skip EN as that is the original language.
        if ($language_id === 'en') {
          continue;
        }

        $this->runMigration("{$this->pluginId}_translations:{$language_id}", $url);
      }
    }
  }

  /**
   * Execute migration for the given URL.
   *
   * @param string $migration_id
   *   Migration Id.
   * @param Drupal\Core\Url $url
   *   Url object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */
  protected function runMigration($migration_id, Url $url) {
    $migration = $this->migrationPluginManager->createInstance($migration_id);
    if (!empty($migration)) {
      $source = $migration->get('source');
      $source['urls'] = $url->toUriString();
      $migration->set('source', $source);
      $migration->getIdMap()->prepareUpdate();
      $executable = new MigrateExecutable($migration, new MigrateMessage());
      $executable->import();
    }
  }

}

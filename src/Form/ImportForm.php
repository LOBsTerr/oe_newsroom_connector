<?php

namespace Drupal\oe_newsroom_connector\Form;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_newsroom_connector\Helper\ImporterHelper;
use Drupal\oe_newsroom_connector\Helper\UniverseHelper;
use Drupal\oe_newsroom_connector\Importer\Configuration;
use Drupal\oe_newsroom_connector\Importer\Importer;
use Drupal\oe_newsroom_connector\Plugin\NewsroomProcessorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImportForm
 * @package Drupal\oe_newsroom_connector\Form
 */
class ImportForm extends FormBase {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\oe_newsroom_connector\Plugin\NewsroomProcessorManager
   */
  protected $newsroomProcessorPluginManager;


  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_newsroom_connector_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(NewsroomProcessorManager $newsroom_processor_plugin_manager) {
    $this->newsroomProcessorPluginManager = $newsroom_processor_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom_connector.plugin.manager.newsroom_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL) {
    $plugin = $this->newsroomProcessorPluginManager->createInstance($plugin_id);
    if (!$plugin) {
      throw new PluginNotFoundException($plugin_id, 'Unable to find the plugin');
    }
    else {
      $this->plugin = $plugin;
    }

    $form = [];
    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#default_value' => $plugin->getEntityUrl()->toUriString(),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = Url::fromUri($form_state->getValue('url'));
    $this->plugin->runImport($url);
  }

}

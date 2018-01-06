<?php

namespace Drupal\media\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines the "mediabutton" plugin.
 *
 * @CKEditorPlugin(
 *   id = "mediaembed",
 *   label = @Translation("Media Embed"),
 *   module = "media",
 * )
 */
class MediaEmbed extends CKEditorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The collected media buttons.
   *
   * @var array
   */
  protected $buttons;

  /**
   * MediaEmbed constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'MediaEmbed_dialogTitleAdd' => $this->t("Add Media"),
      'MediaEmbed_dialogTitleEdit' => $this->t("Edit Media"),
      'MediaEmbed_buttons' => $this->getButtons(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'media') . '/js/plugins/mediaembed/plugin.js';
  }

  /**
   * (@inheritdoc}
   */
  public function getButtons() {
    if ($this->buttons) {
      return $this->buttons;
    }

    $buttons = [];
    foreach ($this->entityTypeBundleInfo->getBundleInfo('media') as $machine_name => $media_type) {
      $buttons[$machine_name] = [
        'label' => $media_type['label'],
        'id' => $machine_name,
        'image' => base_path() . drupal_get_path('module', 'media') . '/js/plugins/mediaembed/icons/mediaembed.png',
      ];
    }
    $this->buttons = $buttons;
    return $buttons;
  }

}

<?php

namespace Drupal\Tests\media\Kernel;

use Drupal\filter\FilterPluginCollection;
use Drupal\media\Entity\Media;
use Drupal\Component\Utility\Html;

/**
 * Tests the media content input filter.
 *
 * @group media
 */
class MediaEmbedFilterTest extends MediaKernelTestBase {

  /**
   * @var \Drupal\filter\Plugin\FilterInterface[]
   */
  protected $filters;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $manager = $this->container->get('plugin.manager.filter');
    $bag = new FilterPluginCollection($manager, []);
    $this->filters = $bag->getAll();
  }

  /**
   * Tests the media embed filter.
   */
  public function testMediaEmbedFilter() {
    // Get media embed filter.
    $filter = $this->filters['media_embed'];

    // Create test function.
    $test = function ($input) use ($filter) {
      // Run $input through $filter.
      $filtered_media = $filter->process($input, 'und')->getProcessedText();
      // Extract <img /> tag.
      $dom = Html::load($filtered_media);
      $img = $dom->getElementsByTagName('img')[0];
      $filtered_media_img = $dom->saveHTML($img);
      return $filtered_media_img;
    };

    // Create media entity.
    $media = Media::create(['bundle' => $this->testMediaType->id()]);
    $media->save();
    $media_uuid = $media->uuid();

    // Render entity.
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $view_builder */
    $view_builder = \Drupal::entityTypeManager();
    $build = $view_builder->getViewBuilder($media->getEntityTypeId())->view($media);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $rendered_media = $renderer->renderPlain($build)->__toString();

    // Extract <img /> tag.
    $dom = Html::load($rendered_media);
    $img = $dom->getElementsByTagName('img')[0];
    $rendered_media_img = $dom->saveHTML($img);

    // Test filter using data-entity-embed-display attribute.
    $input = '<drupal-entity data-entity-type="media" data-entity-embed-display="view_mode:image.full" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media_img;
    $this->assertSame($expected, $test($input));

    // Test filter using data-view-mode attribute.
    $input = '<drupal-entity data-entity-type="media" data-view-mode="full" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media_img;
    $this->assertSame($expected, $test($input));
  }

}

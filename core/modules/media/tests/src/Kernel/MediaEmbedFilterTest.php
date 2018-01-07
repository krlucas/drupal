<?php

namespace Drupal\Tests\media\Kernel;

use Drupal\filter\FilterPluginCollection;
use Drupal\media\Entity\Media;

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
      return $filter->process($input, 'und');
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

    // Test filter using data-entity-embed-display attribute.
    $input = '<drupal-entity data-entity-type="image" data-entity-embed-display="view_mode:image.full" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());

    // Test filter using data-view-mode attribute.
    $input = '<drupal-entity data-entity-type="image" data-view-mode="full" data-entity-uuid="' . $media_uuid . '"></drupal-entity>';
    $expected = $rendered_media;
    $this->assertSame($expected, $test($input)->getProcessedText());
  }

}

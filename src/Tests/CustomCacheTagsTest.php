<?php
/**
 * @file
 * Contains \Drupal\views_custom_cache_tag\CustomCacheTagsTest.
 */

namespace Drupal\views_custom_cache_tag\Tests;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;
use Drupal\system\Tests\Cache\AssertPageCacheTagsTrait;

/**
 * Tests the custom cache tags in views.
 *
 * @group views_custom_cache_tag
 */
class CustomCacheTagsTest extends WebTestBase {

  use AssertPageCacheTagsTrait;

  /**
   * Disabled config schema checking temporarily until all errors are resolved.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'node',
    'views',
    'views_custom_cache_tag_demo'
  );

  /**
   * Tests the cache tag invalidation.
   */
  public function testCustomCacheTags() {

    $this->enablePageCaching();
    // Create a new node of type A
    $node_a = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_a',
      'created' => 1,
      'title' => $this->randomMachineName(8),
      'nid' => 2,
            ]);
    $node_a->enforceIsNew(TRUE);
    $node_a->save();

    // Create a new node of type B
    $node_b = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_b',
      'created' => 1,
      'title' => $this->randomMachineName(8),
      'nid' => 3,
    ]);
    $node_b->enforceIsNew(TRUE);
    $node_b->save();

    // Check the cache tags in the views.
    $this->assertPageCacheTags(Url::fromRoute('view.view_node_type_a.page_1'), array(
      'node:node_type_a',
      'rendered',
      'view:view_node_type_a'
    ));
    $this->assertPageCacheTags(Url::fromRoute('view.view_node_type_b.page_1'), array(
      'node:node_type_b',
      'rendered',
      'view:view_node_type_b'
    ));
    // Verify cache hits in both views,Cached in assertPageCacheTags().
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_a.page_1'), 'HIT');
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_b.page_1'), 'HIT');

    // Create a new node of type B ensure that the page
    // cache entry invalidates.
    $node_b = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'node_type_b',
      'created' => 1,
      'title' => $this->randomMachineName(8),
      'nid' => 4,
    ]);
    $node_b->enforceIsNew(TRUE);
    $node_b->save();
    // Ensure cache tags invalidation in node type B view.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_b.page_1'), 'MISS');
    // Make sure the node type A tags are not invalidated.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_a.page_1'), 'HIT');
    // Make sure type B view is cached again.
    $this->verifyPageCache(Url::fromRoute('view.view_node_type_b.page_1'), 'HIT');



  }

  /**
   * Verify that when loading a given page, it's a page cache hit or miss.
   *
   * @param \Drupal\Core\Url $url
   *   The page for this URL will be loaded.
   * @param string $hit_or_miss
   *   'HIT' if a page cache hit is expected, 'MISS' otherwise.
   *
   * @param array|FALSE $tags
   *   When expecting a page cache hit, you may optionally specify an array of
   *   expected cache tags. While FALSE, the cache tags will not be verified.
   */
  protected function verifyPageCache(Url $url, $hit_or_miss, $tags = FALSE) {
    $this->drupalGet($url);
    $message = String::format('Page cache @hit_or_miss for %path.', array('@hit_or_miss' => $hit_or_miss, '%path' => $url->toString()));
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), $hit_or_miss, $message);

    if ($hit_or_miss === 'HIT' && is_array($tags)) {
      $absolute_url = $url->setAbsolute()->toString();
      $cid_parts = array($absolute_url, 'html');
      $cid = implode(':', $cid_parts);
      $cache_entry = \Drupal::cache('render')->get($cid);
      sort($cache_entry->tags);
      $tags = array_unique($tags);
      sort($tags);
      $this->assertIdentical($cache_entry->tags, $tags);
    }
  }
}
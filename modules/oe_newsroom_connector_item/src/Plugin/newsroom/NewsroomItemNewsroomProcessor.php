<?php

namespace Drupal\oe_newsroom_connector_item\Plugin\newsroom;

use Drupal\oe_newsroom_connector\Plugin\NewsroomProcessorBase;

/**
 * Handles typical operations for newsroom item.
 *
 * @NewsroomProcessor (
 *   id = "oe_newsroom_item",
 *   migration_id = "newsroom_item",
 *   content_type = "node",
 *   bundle = "newsroom_item",
 *   import_script = "fullrss-multilingual.cfm",
 *   import_segment = "item_id",
 *   label = @Translation("Newsroom item"),
 *   migrations = {
 *     "newsroom_item_image",
 *     "newsroom_item_image_media",
 *     "newsroom_item",
 *   }
 * )
 */
class NewsroomItemNewsroomProcessor extends NewsroomProcessorBase {

}

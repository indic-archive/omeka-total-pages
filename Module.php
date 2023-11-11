<?php
namespace ItemSetTotalPages;

use Omeka\Module\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;

class Module extends AbstractModule
{
    private $isUpdating = false;
    private $itemSets = [];
    private $oldExtent = null;

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Attach to events that modify an item's extent, visibility, or its collection membership
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'updateTotalPages']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.delete.post',
            [$this, 'updateTotalPages']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'updateTotalPages']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemSetAdapter',
            'api.update.post',
            [$this, 'updateTotalPages']
        );
    }

    public function updateTotalPages(Event $event) {
        if($this->isUpdating) {
            return;
        }
        $this->isUpdating = true;


        $response = $event->getParam('response');
        $resource = $response->getContent();

        if ($resource instanceof Item) {
            $itemSets = $resource->getItemSets();
            foreach ($itemSets as $itemSet) {
                $this->calculateTotalPages($itemSet->getId());
            }
        } elseif ($resource instanceof ItemSet) {
            $this->calculateTotalPages($resource->getId());
        }
    }

    private function calculateTotalPages($itemSetId)
    {
        // Get total pages.
        $sql = "SELECT SUM(CAST(value.value AS UNSIGNED)) AS total_pages
                FROM value
                INNER JOIN property ON value.property_id = property.id
                INNER JOIN resource ON value.resource_id = resource.id
                INNER JOIN item_item_set ON item_item_set.item_id = resource.id
                WHERE property.local_name = 'extent' AND resource.is_public = true
                AND item_item_set.item_set_id = ".$itemSetId;

        $services = $this->getServiceLocator();
        $conn = $services->get('Omeka\Connection');

        $count = $conn->fetchOne($sql);
        if(!$count) {
            return;
        }

        // See if the property exists on the itemset already.
        $propID = $conn->fetchOne("SELECT id FROM `value` WHERE resource_id='".$itemSetId."' AND property_id=(SELECT id FROM property WHERE local_name = 'extent')");
        if($propID) {
            $sql = "UPDATE `value` SET `value` = '".$count."' WHERE id='".$propID."'";
        } else {
            $sql = "
                INSERT INTO `value` (`type`, `is_public`, `property_id`, `value`, `resource_id`)
                VALUES ('literal', true, (SELECT id FROM property WHERE local_name = 'extent'), '".$count."', '".$itemSetId."')
            ";
        }

        $conn->exec($sql);
    }
}

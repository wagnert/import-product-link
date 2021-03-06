<?php

/**
 * TechDivision\Import\Product\Link\Observers\LinkObserver
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product-link
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Product\Link\Observers;

use TechDivision\Import\Product\Link\Utils\ColumnKeys;
use TechDivision\Import\Product\Link\Utils\MemberNames;
use TechDivision\Import\Product\Observers\AbstractProductImportObserver;
use TechDivision\Import\Product\Link\Services\ProductLinkProcessorInterface;

/**
 * Oberserver that provides functionality for the product link replace operation.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product-link
 * @link      http://www.techdivision.com
 */
class LinkObserver extends AbstractProductImportObserver
{

    /**
     * The product link processor instance.
     *
     * @var \TechDivision\Import\Product\Link\Services\ProductLinkProcessorInterface
     */
    protected $productLinkProcessor;

    /**
     * Initialize the observer with the passed product link processor instance.
     *
     * @param \TechDivision\Import\Product\Link\Services\ProductLinkProcessorInterface $productLinkProcessor The product link processor instance
     */
    public function __construct(ProductLinkProcessorInterface $productLinkProcessor)
    {
        $this->productLinkProcessor= $productLinkProcessor;
    }

    /**
     * Return's the product link processor instance.
     *
     * @return \TechDivision\Import\Product\Link\Services\ProductLinkProcessorInterface The product link processor instance
     */
    protected function getProductLinkProcessor()
    {
        return $this->productLinkProcessor;
    }

    /**
     * Process the observer's business logic.
     *
     * @return array The processed row
     */
    protected function process()
    {

        // prepare the product link entity
        if ($attr = $this->prepareAttributes()) {
            // initialize the product link entity
            $productLink = $this->initializeProductLink($attr);

            // persist the product link entity and store the link ID
            $this->setLastLinkId($this->persistProductLink($productLink));
        }
    }

    /**
     * Prepare the attributes of the entity that has to be persisted.
     *
     * @return array The prepared attributes
     */
    protected function prepareAttributes()
    {

        try {
            // extract the parent ID from the row
            $parentId = $this->mapSku($this->getValue(ColumnKeys::LINK_PARENT_SKU));
        } catch (\Exception $e) {
            throw $this->wrapException(array(ColumnKeys::LINK_PARENT_SKU), $e);
        }

        try {
            // extract the child ID from the row
            $childId = $this->mapSkuToEntityId($this->getValue(ColumnKeys::LINK_CHILD_SKU));
        } catch (\Exception $e) {
            throw $this->wrapException(array(ColumnKeys::LINK_CHILD_SKU), $e);
        }

        try {
            // extract the link type code from the row
            $linkTypeId = $this->mapLinkTypeCodeToLinkTypeId($this->getValue(ColumnKeys::LINK_TYPE_CODE));
        } catch (\Exception $e) {
            // query whether or not, debug mode is enabled
            if ($this->isDebugMode()) {
                // log a warning and return immediately
                $this->getSystemLogger()->warning($e->getMessage());
                return;
            }

            // if we're NOT in debug mode, re-throw the exception
            throw $e;
        }

        // initialize and return the entity
        return $this->initializeEntity(
            array(
                MemberNames::PRODUCT_ID        => $parentId,
                MemberNames::LINKED_PRODUCT_ID => $childId,
                MemberNames::LINK_TYPE_ID      => $linkTypeId
            )
        );
    }

    /**
     * Initialize the product link with the passed attributes and returns an instance.
     *
     * @param array $attr The product link attributes
     *
     * @return array The initialized product link
     */
    protected function initializeProductLink(array $attr)
    {

        // load the product/linked product/link type ID
        $productId = $attr[MemberNames::PRODUCT_ID];
        $linkTypeId = $attr[MemberNames::LINK_TYPE_ID];
        $linkedProductId = $attr[MemberNames::LINKED_PRODUCT_ID];

        // try to load the link with the passed product/linked product/link type ID
        if ($entity = $this->loadProductLink($productId, $linkedProductId, $linkTypeId)) {
            return $this->mergeEntity($entity, $attr);
        }

        // simply return the attributes
        return $attr;
    }

    /**
     * Load's the link with the passed product/linked product/link type ID.
     *
     * @param integer $productId       The product ID of the link to load
     * @param integer $linkedProductId The linked product ID of the link to load
     * @param integer $linkTypeId      The link type ID of the product to load
     *
     * @return array The link
     */
    protected function loadProductLink($productId, $linkedProductId, $linkTypeId)
    {
        return $this->getProductLinkProcessor()->loadProductLink($productId, $linkedProductId, $linkTypeId);
    }

    /**
     * Temporary persist the last link ID.
     *
     * @param integer $lastLinkId The last link ID
     *
     * @return void
     */
    protected function setLastLinkId($lastLinkId)
    {
        $this->getSubject()->setLastLinkId($lastLinkId);
    }

    /**
     * Persist's the passed product link data and return's the ID.
     *
     * @param array $productLink The product link data to persist
     *
     * @return string The ID of the persisted entity
     */
    protected function persistProductLink($productLink)
    {
        return $this->getProductLinkProcessor()->persistProductLink($productLink);
    }

    /**
     * Return the entity ID for the passed SKU.
     *
     * @param string $sku The SKU to return the entity ID for
     *
     * @return integer The mapped entity ID
     * @throws \TechDivision\Import\Product\Link\Exceptions\MapSkuToEntityIdException Is thrown if the SKU is not mapped yet
     */
    protected function mapSkuToEntityId($sku)
    {
        return $this->getSubject()->mapSkuToEntityId($sku);
    }

    /**
     * Return the entity ID for the passed SKU.
     *
     * @param string $sku The SKU to return the entity ID for
     *
     * @return integer The mapped entity ID
     * @throws \TechDivision\Import\Product\Link\Exceptions\MapSkuToEntityIdException Is thrown if the SKU is not mapped yet
     */
    protected function mapSku($sku)
    {
        return $this->getSubject()->mapSkuToEntityId($sku);
    }

    /**
     * Return the link type ID for the passed link type code.
     *
     * @param string $linkTypeCode The link type code to return the link type ID for
     *
     * @return integer The mapped link type ID
     * @throws \TechDivision\Import\Product\Exceptions\MapLinkTypeCodeToIdException Is thrown if the link type code is not mapped yet
     */
    protected function mapLinkTypeCodeToLinkTypeId($linkTypeCode)
    {
        return $this->getSubject()->mapLinkTypeCodeToLinkTypeId($linkTypeCode);
    }

    /**
     * Queries whether or not debug mode is enabled or not, default is TRUE.
     *
     * @return boolean TRUE if debug mode is enabled, else FALSE
     */
    protected function isDebugMode()
    {
        return $this->getSubject()->isDebugMode();
    }
}

<?php
/*
 * Copyright (c) 2015, Dor Fire
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 
 *  1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 
 *  2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer
 *     in the documentation and/or other materials provided with the distribution.
 * 
 *  3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA,
 * OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * @author	Dor Fire <dorfire@gmail.com>
 */
class Fire_AutoRelate_Model_Observer
{
	const MAX_RELATED_PRODUCTS = 4;

	private function getCategoryRelationMap()
	{
		return array(
			16 => 17, # boys' shirts => pants
			17 => 16,
			18 => 16, # boys' sweaters => pants
			16 => 18,
			
			26 => 27, # girls' shirts => pants
			27 => 26,
			28 => 31, # girls' sweaters => skirts
			31 => 28,
		);
	}

	private static function setProductCollectionVisibilityFilter($col, $visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
	{
		$col->addAttributeToFilter('visibility' , $visibility);
	}

	private static function setProductCollectionCategoryFilter($col, $cId)
	{
		$col->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id = entity_id', null, 'left')
			->addAttributeToFilter('category_id', $cId);
	}

	protected static function getCollectionIds($col, $limit = null)
	{
		$idsSelect = $col->getSelect();
		$idsSelect->columns('e.' . $col->getEntity()->getIdFieldName());
		$idsSelect->limit($limit);
		$idsSelect->resetJoinLeft();
		return $col->getConnection()->fetchCol($idsSelect);
	}

	/**
	 * Links the first MAX_RELATED_PRODUCTS products from the given collection as related products for the given product.
	 * @param [type] $product           [description]
	 * @param Array $relatedCollection
	 * @return Array The IDs of the products that were linked to the given product.
	 */
	protected function setProductRelatedCollection($product, $relatedCollection)
	{
		$relatedIds = self::getCollectionIds($relatedCollection, self::MAX_RELATED_PRODUCTS);

		$data = array();
		foreach ($relatedIds as $id)
			$data[$id] = array('position' => 0);

		if (count($data) <= self::MAX_RELATED_PRODUCTS)
		{
			$product->setRelatedLinkData($data);
			//$product->save();
			return $relatedIds;
		}
		else return null;
	}

	/**
	 * Returns a random product collection from the specified category with the defined visibility status.
	 * @param  int $toId Category ID from which to select products
	 * @return Varien_Collection       [description]
	 */
	protected function getRelatedProductCollection($toId)
	{
		$resultCollection = Mage::getModel('catalog/product')->getCollection();
		$resultCollection->clear();
		$resultCollection->setPageSize(false);

		$resultCollection->getSelect()->order(new Zend_Db_Expr('RAND()'));
		self::setProductCollectionCategoryFilter($resultCollection, $toId);
		self::setProductCollectionVisibilityFilter($resultCollection);

		return $resultCollection;
	}

	/**
	 * Adds related products to the given product, using the relation map returned from getCategoryRelationMap.
	 * @param  $product Product for which to add related products.
	 * @return          Number of relations added (0 <= n <= MAX_RELATED_PRODUCTS) or null if no match was found in the category relation map.
	 */
	public function relateProduct($product)
	{
		$_categoryRelationMap = $this->getCategoryRelationMap();
		$productCategories = $product->getCategoryIds();
		foreach ($productCategories as $categoryId)
		{
			if (array_key_exists($categoryId, $_categoryRelationMap))
			{
				$targetCategoryId = $_categoryRelationMap[$categoryId];

				$relatedProductCollection = $this->getRelatedProductCollection($targetCategoryId);

				$relatedIds = $this->setProductRelatedCollection($product, $relatedProductCollection);
				if ($relatedIds !== null)
				{
					return count($relatedIds);
				}
				else return 0;
			}
		}

		return null;
	}

	public function handleSave($observer)
	{
		$product = $observer->getProduct();
		$productVisibility = $product->getVisibility();

		// Add relations only for products that are visible in the catalog
		if ($productVisibility == Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
		 || $productVisibility == Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
		{
			if (count($product->getRelatedProductIds()) == 0)
			{
				$relationsAdded = $this->relateProduct($product);

				if ($relationsAdded !== null)
				{
					Mage::log(__CLASS__ .': relateProduct('. $product->getId() .') added '. $relationsAdded .' links.');
				}
				else Mage::log(__CLASS__ .': relateProduct('. $product->getId() .') returned NULL. Product categories not mapped.');
			}
			else Mage::log(__CLASS__ .': relateProduct('. $product->getId() .') already has relations.');
		}
		else
		{
			Mage::log(__CLASS__ .': relateProduct('. $product->getId() .') called for an invisible product.');
		}

		return $this;
	}
}
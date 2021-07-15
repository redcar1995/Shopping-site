<?php

/**
Fields Summary:
- ruleId [numeric]
- localizedfields [localizedfields]
-- name [input]
*/

namespace Pimcore\Model\DataObject\Fieldcollection\Data;

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\PreGetValueHookInterface;

class PricingRule extends DataObject\Fieldcollection\Data\AbstractData
{
protected $type = "PricingRule";
protected $ruleId;
protected $localizedfields;


/**
* Get ruleId - Rule Id
* @return float|null
*/
public function getRuleId(): ?float
{
	$data = $this->ruleId;
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set ruleId - Rule Id
* @param float|null $ruleId
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\PricingRule
*/
public function setRuleId(?float $ruleId)
{
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\Numeric $fd */
	$fd = $this->getDefinition()->getFieldDefinition("ruleId");
	$this->ruleId = $fd->preSetData($this, $ruleId);

	return $this;
}

/**
* Get localizedfields - 
* @return \Pimcore\Model\DataObject\Localizedfield|null
*/
public function getLocalizedfields(): ?\Pimcore\Model\DataObject\Localizedfield
{
	$container = $this;
	/** @var \Pimcore\Model\DataObject\ClassDefinition\Data\Localizedfields $fd */
	$fd = $this->getDefinition()->getFieldDefinition("localizedfields");
	$data = $fd->preGetData($container);
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Get name - Name
* @return string|null
*/
public function getName($language = null): ?string
{
	$data = $this->getLocalizedfields()->getLocalizedValue("name", $language);
	if ($data instanceof \Pimcore\Model\DataObject\Data\EncryptedField) {
		return $data->getPlain();
	}

	return $data;
}

/**
* Set localizedfields - 
* @param \Pimcore\Model\DataObject\Localizedfield|null $localizedfields
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\PricingRule
*/
public function setLocalizedfields(?\Pimcore\Model\DataObject\Localizedfield $localizedfields)
{
	$hideUnpublished = \Pimcore\Model\DataObject\Concrete::getHideUnpublished();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished(false);
	$currentData = $this->getLocalizedfields();
	\Pimcore\Model\DataObject\Concrete::setHideUnpublished($hideUnpublished);
	$this->markFieldDirty("localizedfields", true);
	$this->localizedfields = $localizedfields;

	return $this;
}

/**
* Set name - Name
* @param string|null $name
* @return \Pimcore\Model\DataObject\Fieldcollection\Data\PricingRule
*/
public function setName (?string $name, $language = null)
{
	$isEqual = false;
	$this->getLocalizedfields()->setLocalizedValue("name", $name, $language, !$isEqual);

	return $this;
}

}


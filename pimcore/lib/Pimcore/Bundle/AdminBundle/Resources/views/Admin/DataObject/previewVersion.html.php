<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="/pimcore/static6/css/object_versions.css"/>
</head>

<body>


<?php

    use Pimcore\Model\DataObject;

    $fields = $this->object->getClass()->getFieldDefinitions();

?>

<table class="preview" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <th>Name</th>
        <th>Key</th>
        <th>Value</th>
    </tr>
    <tr class="system">
        <td>Date</td>
        <td>o_modificationDate</td>
        <td><?= date('Y-m-d H:i:s', $this->object->getModificationDate()); ?></td>
    </tr>
    <tr class="system">
        <td>Path</td>
        <td>o_path</td>
        <td><?= $this->object->getRealFullPath(); ?></td>
    </tr>
    <tr class="system">
        <td>Published</td>
        <td>o_published</td>
        <td><?= json_encode($this->object->getPublished()); ?></td>
    </tr>

    <tr class="">
        <td colspan="3">&nbsp;</td>
    </tr>

    <?php $c = 0; ?>
    <?php foreach ($fields as $fieldName => $definition) { ?>
        <?php if ($definition instanceof DataObject\ClassDefinition\Data\Localizedfields) { ?>
            <?php foreach (\Pimcore\Tool::getValidLanguages() as $language) { ?>
                <?php foreach ($definition->getFieldDefinitions() as $lfd) { ?>
                    <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                        <td><?= $lfd->getTitle() ?> (<?= $language; ?>)</td>
                        <td><?= $lfd->getName() ?></td>
                        <td>
                            <?php
                            if ($this->object->getValueForFieldName($fieldName)) {
                                echo $lfd->getVersionPreview($this->object->getValueForFieldName($fieldName)->getLocalizedValue($lfd->getName(), $language));
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                    $c++;
                } ?>
            <?php } ?>
        <?php } else if ($definition instanceof DataObject\ClassDefinition\Data\Objectbricks) { ?>
            <?php foreach ($definition->getAllowedTypes() as $asAllowedType) { ?>
                <?php
                $collectionDef = DataObject\Objectbrick\Definition::getByKey($asAllowedType);

                foreach ($collectionDef->getFieldDefinitions() as $lfd) {

                    $value = null;
                    $bricks = $this->object->{"get" . ucfirst($fieldName)}();

                    if (!$bricks) {
                    continue;
                    }

                    $brickValue = $bricks->{"get" . $asAllowedType}();

                    if ($lfd instanceof DataObject\ClassDefinition\Data\Localizedfields) { ?>
                        <?php foreach (\Pimcore\Tool::getValidLanguages() as $language) { ?>
                            <?php foreach ($lfd->getFieldDefinitions() as $localizedFieldDefinition) { ?>
                                <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                                    <td><?= $localizedFieldDefinition->getTitle() ?> (<?= $language; ?>)</td>
                                    <td><?= $localizedFieldDefinition->getName() ?></td>
                                    <td>
                                        <?php
                                        if ($brickValue) {
                                            /** @var  $localizedBrickValues DataObject\Localizedfield */
                                            $localizedBrickValues = $brickValue->getLocalizedFields();
                                            $localizedBrickValue = $localizedBrickValues->getLocalizedValue($localizedFieldDefinition->getName(), $language);
                                            $versionPreview = $localizedFieldDefinition->getVersionPreview($localizedBrickValue);
                                            echo $versionPreview;

                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php
                                $c++;
                            } ?>
                        <?php }
                    } else {

                        if ($brickValue) {
                            $value = $lfd->getVersionPreview($brickValue->getValueForFieldName($lfd->getName()));
                        }

                        ?>
                        <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                            <td><?= ucfirst($asAllowedType) . " - " . $lfd->getTitle() ?></td>
                            <td><?= $lfd->getName() ?></td>
                            <td><?= $value ?></td>
                        </tr>
                        <?php
                        $c++;
                    }
                } ?>
            <?php } ?>
        <?php } else if ($definition instanceof DataObject\ClassDefinition\Data\Classificationstore) {
            /** @var $storedata DataObject\Classificationstore */
            $storedata = $definition->getVersionPreview($this->object->getValueForFieldName($fieldName));

            if (!$storedata) {
                continue;
            }
            $activeGroups = $storedata->getActiveGroups();
            if (!$activeGroups) {
                continue;
            }

            $languages = ["default"];

            if ($definition->isLocalized()) {
                $languages = array_merge($languages, \Pimcore\Tool::getValidLanguages());
            }

            foreach ($activeGroups as $activeGroupId => $enabled) {
                if (!$enabled) {
                    continue;
                }
                /** @var $groupDefinition DataObject\Classificationstore\GroupConfig */
                $groupDefinition = Pimcore\Model\DataObject\Classificationstore\GroupConfig::getById($activeGroupId);
                if (!$groupDefinition) {
                    continue;
                }

                /** @var $keyGroupRelation DataObject\Classificationstore\KeyGroupRelation */
                $keyGroupRelations = $groupDefinition->getRelations();

                foreach ($keyGroupRelations as $keyGroupRelation) {

                    $keyDef = DataObject\Classificationstore\Service::getFieldDefinitionFromJson(json_decode($keyGroupRelation->getDefinition()), $keyGroupRelation->getType());
                    if (!$keyDef) {
                        continue;
                    }

                    foreach ($languages as $language) {

                        $keyData = $storedata->getLocalizedKeyValue($activeGroupId, $keyGroupRelation->getKeyId(), $language, true, true);
                        $preview = $keyDef->getVersionPreview($keyData);
                        ?>

                    <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                        <td><?= $definition->getTitle() ?></td>
                        <td><?= $groupDefinition->getName() ?> - <?= $keyGroupRelation->getName()?> / <?= $definition->isLocalized() ? $language : "" ?></td>
                        <td><?= $preview ?></td>
                    </tr>
                    <?php
                    $c++;
                }
            }
        }
        ?>
    <?php } else if ($definition instanceof DataObject\ClassDefinition\Data\Fieldcollections) {
            $fields = $this->object->{"get" . ucfirst($fieldName)}();
            if($fields) {
                $fieldDefinitions = $fields->getItemDefinitions();
                $fieldItems = $fields->getItems();
            }

            if (count($fieldItems)) {

                foreach ($fieldItems as $fkey => $fieldItem) {
                    $fieldKeys = $fieldDefinitions[$fieldItem->type]->getFieldDefinitions();
                    foreach ($fieldKeys as $fieldKey) {


                       $value = $fieldItem->{"get" . ucfirst($fieldKey->name)}();
                       // if ($fieldValue) { ?>
                             <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                                <td><?= ucfirst($fieldItem->type) . " - " . $fieldKey->title ?> (<?= $language; ?>)</td>
                                <td><?= $fieldKey->name ?></td>
                                <td><?= $fieldKey->getVersionPreview($value) ?></td>
                            </tr>
                        <?php
                        $c++;
                      //  }
                    }
                }
            }
    ?>
    <?php } else { ?>
        <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
            <td><?= $definition->getTitle() ?></td>
            <td><?= $definition->getName() ?></td>
            <td><?= $definition->getVersionPreview($this->object->getValueForFieldName($fieldName)) ?></td>
        </tr>
    <?php } ?>
<?php $c++;
} ?>
</table>


</body>
</html>

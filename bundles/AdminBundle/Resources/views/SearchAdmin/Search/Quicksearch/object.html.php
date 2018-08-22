<?php
/**
 * @var \Pimcore\Model\DataObject\Concrete $element
 */

use Pimcore\Model\DataObject;

$fields = $element->getClass()->getFieldDefinitions();

?>


<div class="small-icon <?= $this->iconCls ?>"></div>
<?= $this->render('PimcoreAdminBundle:SearchAdmin/Search/Quicksearch:info-table.html.php', ['element' => $element, 'cls' => 'no-opacity']) ?>

<table class="data-table" style="top: 70px;">
    <?php $c = 0; ?>
    <?php foreach ($fields as $fieldName => $definition) {
        if($c > 30) {
            break;
        }

        ?>
        <?php if ($definition instanceof DataObject\ClassDefinition\Data\Localizedfields) { ?>
            <?php foreach (\Pimcore\Tool::getValidLanguages() as $language) { ?>
                <?php foreach ($definition->getFieldDefinitions() as $lfd) { ?>
                    <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                        <th><?= $lfd->getTitle() ? $lfd->getTitle() : $lfd->getName() ?> (<?= $language; ?>)</th>
                        <td>
                            <div class="limit-height">
                                <?php
                                if ($element->getValueForFieldName($fieldName)) {
                                    echo $lfd->getVersionPreview($element->getValueForFieldName($fieldName)->getLocalizedValue($lfd->getName(), $language));
                                }
                                ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                    $c++;
                } ?>
                <?php break; } ?>
        <?php } else if($definition instanceof DataObject\ClassDefinition\Data\Classificationstore) {

            /** @var $storedata DataObject\Classificationstore */
            $storedata = $definition->getVersionPreview($element->getValueForFieldName($fieldName));

            $existingGroups = array();


            if ($storedata) {
                $activeGroups = $storedata->getActiveGroups();
            } else {
                $activeGroups = array();
            }


            foreach ($activeGroups as $activeGroupId => $enabled) {
                $existingGroups[$activeGroupId] = $activeGroupId;
            }

            if (!$existingGroups) {
                continue;
            }

            $languages = array("default");

            if ($definition->isLocalized()) {
                $languages = array_merge($languages, \Pimcore\Tool::getValidLanguages());
            }

            foreach ($existingGroups as $activeGroupId => $enabled) {
                if (!$activeGroups[$activeGroupId]) {
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

                    $keyDef = DataObject\Classificationstore\Service::getFieldDefinitionFromJson(json_decode($keyGroupRelation->getDefinition()),
                        $keyGroupRelation->getType());
                    if (!$keyDef) {
                        continue;
                    }

                    foreach ($languages as $language) {
                        $keyData = $storedata ? $storedata->getLocalizedKeyValue($activeGroupId,
                            $keyGroupRelation->getKeyId(), $language, true, true) : null;
                        $preview = $keyDef->getVersionPreview($keyData);

                        ?>

                        <tr class="<?php if ($c % 2) { ?>odd<?php } ?>">
                            <td><?= $definition->getTitle() ?></td>
                            <td><?= $groupDefinition->getName() ?>
                                - <?= $keyGroupRelation->getName() ?> <?= $definition->isLocalized() ? "/ " . $language : "" ?></td>
                            <?php if (!$this->isImportPreview || !$this->isNew) { ?>
                                <td><?= $preview ?></td>
                            <?php } ?>
                        </tr>
                        <?php
                        $c++;
                    }
                }
            }
        } else if ($definition instanceof DataObject\ClassDefinition\Data\Objectbricks) { ?>
            <?php foreach ($definition->getAllowedTypes() as $asAllowedType) { ?>
                <?php
                $collectionDef = DataObject\Objectbrick\Definition::getByKey($asAllowedType);

                foreach ($collectionDef->getFieldDefinitions() as $lfd) {

                    $value = null;
                    $bricks = $element->{"get" . ucfirst($fieldName)}();

                    if (!$bricks) {
                        continue;
                    }

                    $brickValue = $bricks->{"get" . $asAllowedType}();

                    if ($lfd instanceof DataObject\ClassDefinition\Data\Localizedfields) { ?>
                        <?php foreach (\Pimcore\Tool::getValidLanguages() as $language) { ?>
                            <?php foreach ($lfd->getFieldDefinitions() as $localizedFieldDefinition) { ?>
                                <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                                    <th><?= $localizedFieldDefinition->getTitle() ? $localizedFieldDefinition->getTitle() : $localizedFieldDefinition->getName() ?> (<?= $language; ?>)</th>
                                    <td>
                                        <div class="limit-height">
                                            <?php
                                                if ($brickValue) {
                                                    /** @var  $localizedBrickValues DataObject\Localizedfield */
                                                    $localizedBrickValues = $brickValue->getLocalizedFields();
                                                    $localizedBrickValue = $localizedBrickValues->getLocalizedValue($localizedFieldDefinition->getName(), $language);
                                                    $versionPreview = $localizedFieldDefinition->getVersionPreview($localizedBrickValue);
                                                    echo $versionPreview;

                                                }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                $c++;
                            } ?>
                            <?php
                            break;
                        }
                    } else {

                        if ($brickValue) {
                            $value = $lfd->getVersionPreview($brickValue->getValueForFieldName($lfd->getName()));
                        }

                        ?>
                        <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                            <th><?= ucfirst($asAllowedType) . " - " . ($lfd->getTitle() ? $lfd->getTitle() : $lfd->getName()) ?></th>
                            <td>
                                <div class="limit-height">
                                    <?= $value ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                        $c++;
                    }
                } ?>
            <?php } ?>
        <?php } else { ?>
            <tr<?php if ($c % 2) { ?> class="odd"<?php } ?>>
                <th><?= $definition->getTitle() ? $definition->getTitle() : $definition->getName() ?></th>
                <td>
                    <div class="limit-height">
                        <?= $definition->getVersionPreview($element->getValueForFieldName($fieldName)) ?>
                    </div>
                </td>
            </tr>
        <?php } ?>
        <?php $c++;
    } ?>
</table>

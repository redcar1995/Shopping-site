<?php
/** @var \Pimcore\Templating\PhpEngine $view */
/** @var \Pimcore\Templating\PhpEngine $this */
/** @var \Pimcore\Templating\GlobalVariables $app */
$app = $view->app;

$language = $app->getRequest()->getLocale();
$this->get("translate")->setDomain("admin");

/** @var \Pimcore\Bundle\AdminBundle\Security\User\User $userProxy */
$userProxy = $app->getUser();
$user      = $userProxy->getUser();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>

    <link rel="icon" type="image/png" href="/bundles/pimcoreadmin/img/favicon/favicon-32x32.png"/>
    <meta name="google" value="notranslate">

    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        #pimcore_loading {
            margin: 0 auto;
            width: 300px;
            padding: 300px 0 0 0;
            text-align: center;
        }

        .spinner {
            margin: 100px auto 0;
            width: 70px;
            text-align: center;
        }

        .spinner > div {
            width: 18px;
            height: 18px;
            background-color: #3d3d3d;

            border-radius: 100%;
            display: inline-block;
            -webkit-animation: sk-bouncedelay 1.4s infinite ease-in-out both;
            animation: sk-bouncedelay 1.4s infinite ease-in-out both;
        }

        .spinner .bounce1 {
            -webkit-animation-delay: -0.32s;
            animation-delay: -0.32s;
        }

        .spinner .bounce2 {
            -webkit-animation-delay: -0.16s;
            animation-delay: -0.16s;
        }

        @-webkit-keyframes sk-bouncedelay {
            0%, 80%, 100% {
                -webkit-transform: scale(0)
            }
            40% {
                -webkit-transform: scale(1.0)
            }
        }

        @keyframes sk-bouncedelay {
            0%, 80%, 100% {
                -webkit-transform: scale(0);
                transform: scale(0);
            }
            40% {
                -webkit-transform: scale(1.0);
                transform: scale(1.0);
            }
        }
    </style>

    <title><?= htmlentities(\Pimcore\Tool::getHostname(), ENT_QUOTES, 'UTF-8') ?> :: Pimcore</title>

    <script>
        var pimcore = {}; // namespace

        // hide symfony toolbar by default
        var symfonyToolbarKey = 'sf2/profiler/toolbar/displayState';
        if(!window.localStorage.getItem(symfonyToolbarKey)) {
            window.localStorage.setItem(symfonyToolbarKey, 'none');
        }
    </script>
</head>

<body>

<div id="pimcore_loading">
    <div class="spinner">
        <div class="bounce1"></div>
        <div class="bounce2"></div>
        <div class="bounce3"></div>
    </div>
</div>

<?php
$runtimePerspective = \Pimcore\Config::getRuntimePerspective($user);
?>

<div id="pimcore_sidebar">
    <div id="pimcore_navigation" style="display:none;">
        <ul>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "file")) { ?>
                <li id="pimcore_menu_file" data-menu-tooltip="<?= $this->translate("file") ?>" class="pimcore_menu_item pimcore_menu_needs_children">
                    <svg id="icon-file" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18.4 23"><path d="M14.5,1H5.3A2.31,2.31,0,0,0,3,3.3V21.7A2.31,2.31,0,0,0,5.3,24H19.1a2.31,2.31,0,0,0,2.3-2.3V7.9Zm0,3.28L18.12,7.9H14.5ZM5.3,21.7V3.3h6.9v6.9h6.9V21.7Z" transform="translate(-3 -1)"/></svg>
                </li>
            <?php } ?>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "extras")) { ?>
                <li id="pimcore_menu_extras" data-menu-tooltip="<?= $this->translate("tools") ?>" class="pimcore_menu_item pimcore_menu_needs_children">
                    <svg id="icon-tools" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path d="M23.65,19.34l-8.23-8.23A7.44,7.44,0,0,0,5.24,1.73l5,5L6.74,10.25l-5-5a7.44,7.44,0,0,0,9.38,10.18l8.23,8.23a1.11,1.11,0,0,0,1.61,0l2.7-2.7A1.11,1.11,0,0,0,23.65,19.34Z" transform="translate(-1 -1)"/></svg>
                </li>
            <?php } ?>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "marketing")) { ?>
                <li id="pimcore_menu_marketing" data-menu-tooltip="<?= $this->translate("marketing") ?>" class="pimcore_menu_item pimcore_menu_needs_children">
                    <svg id="icon-markting" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path d="M9.47,24h6.05V1H9.47ZM1,24H7.05V10.68H1ZM17.95,7.05V24H24V7.05Z" transform="translate(-1 -1)"/></svg>
                </li>
            <?php } ?>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "settings")) { ?>
                <li id="pimcore_menu_settings" data-menu-tooltip="<?= $this->translate("settings") ?>" class="pimcore_menu_item pimcore_menu_needs_children">
                    <svg id="icon-settings" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23.47"><path d="M21.21,13.85a7.48,7.48,0,0,0,.06-1.17c0-.41-.06-.76-.06-1.17l2.46-1.94a.55.55,0,0,0,.12-.76l-2.35-4a.59.59,0,0,0-.7-.23L17.81,5.69a8.49,8.49,0,0,0-2-1.17L15.4,1.47A.63.63,0,0,0,14.82,1H10.12a.63.63,0,0,0-.59.47L9.07,4.58a9.87,9.87,0,0,0-2,1.17L4.14,4.58a.56.56,0,0,0-.7.23l-2.35,4a.62.62,0,0,0,.12.76l2.52,1.94c0,.41-.06.76-.06,1.17s.06.76.06,1.17L1.26,15.85a.55.55,0,0,0-.12.76l2.35,4a.59.59,0,0,0,.7.23l2.93-1.17a8.49,8.49,0,0,0,2,1.17L9.6,24a.57.57,0,0,0,.59.47h4.69a.63.63,0,0,0,.59-.47l.47-3.11a9.87,9.87,0,0,0,2-1.17l2.93,1.17a.54.54,0,0,0,.7-.23l2.35-4a.62.62,0,0,0-.12-.76Zm-8.74,3a4.11,4.11,0,1,1,4.11-4.11A4.08,4.08,0,0,1,12.47,16.84Z" transform="translate(-1 -1)"/></svg>
                </li>
            <?php } ?>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "ecommerce")) { ?>
                <li id="pimcore_menu_ecommerce" data-menu-tooltip="<?= $this->translate("bundle_ecommerce_mainmenu") ?>" class="pimcore_menu_item pimcore_menu_needs_children" style="display: none;">
                    <svg id="icon-ecommerce" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 19.41"><path d="M8.81,19.67a1.78,1.78,0,0,1-1.74,1.74,1.74,1.74,0,1,1,1.22-3A1.67,1.67,0,0,1,8.81,19.67Zm12.4,0a1.78,1.78,0,1,1-.52-1.22A1.67,1.67,0,0,1,21.2,19.67Zm1.8-15v7.07a.74.74,0,0,1-.23.58.8.8,0,0,1-.58.29L7.76,14.28a8.67,8.67,0,0,1,.17,1,2.16,2.16,0,0,1-.35.87H20.28a.93.93,0,0,1,0,1.85H6.2a1,1,0,0,1-.93-.93,2.61,2.61,0,0,1,.12-.46c.06-.17.17-.35.23-.52l.29-.58a1.86,1.86,0,0,1,.23-.41L3.71,3.74H.87a.93.93,0,0,1-.64-.29A.67.67,0,0,1,0,2.87a.93.93,0,0,1,.29-.64A.67.67,0,0,1,.87,2H4.4a.84.84,0,0,1,.41.12.58.58,0,0,1,.29.23.94.94,0,0,1,.17.35A1.2,1.2,0,0,1,5.39,3c0,.12.06.23.06.41a1,1,0,0,1,.06.35H22.07a.93.93,0,0,1,.64.29A.71.71,0,0,1,23,4.66Z" transform="translate(0 -2)"/></svg>
                </li>
            <?php } ?>
            <?php if (\Pimcore\Config::inPerspective($runtimePerspective, "search")) { ?>
                <li id="pimcore_menu_search" data-menu-tooltip="<?= $this->translate("search") ?>" class="pimcore_menu_item pimcore_menu_needs_children">
                    <svg id="icon-search" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path d="M18,15.81a9.37,9.37,0,1,0-7.62,3.88A9.66,9.66,0,0,0,15.81,18l6,6L24,21.84ZM3.88,10.34a6.47,6.47,0,1,1,6.47,6.47A6.44,6.44,0,0,1,3.88,10.34Z" transform="translate(-1 -1)"/></svg>
                </li>
            <?php } ?>
            <li id="pimcore_menu_maintenance" data-menu-tooltip="<?= $this->translate("deactivate_maintenance") ?>" class="pimcore_menu_item " style="display:none;"></li>
        </ul>
    </div>
    <div id="pimcore_status">
        <div href="#" style="display: none" id="pimcore_notification" data-menu-tooltip="<?= $this->translate("notifications") ?>" class="pimcore_icon_comments">
            <span id="notification_value" style="display:none;"></span>
        </div>
        <div id="pimcore_status_dev" data-menu-tooltip="DEV MODE" style="display: none;"></div>
        <div id="pimcore_status_debug" data-menu-tooltip="<?= $this->translate("debug_mode_on") ?>" style="display: none;"></div>
        <div id="pimcore_status_email" data-menu-tooltip="<?= $this->translate("mail_settings_incomplete") ?>" style="display: none;"></div>
        <a id="pimcore_status_maintenance" data-menu-tooltip="<?= $this->translate("maintenance_not_active") ?>" style="display: none;" href="https://pimcore.com/docs/5.0.x/Getting_Started/Installation.html#page_5-Maintenance-Cron-Job"></a>
        <div id="pimcore_status_update" data-menu-tooltip="<?= $this->translate("update_available") ?>" style="display: none;"></div>
    </div>
    <div id="pimcore_avatar" style="display:none;">
        <img src="/admin/user/get-image" data-menu-tooltip="<?= $user->getName() ?> | <?= $this->translate('my_profile') ?>"/>
    </div>
    <a id="pimcore_logout" data-menu-tooltip="<?= $this->translate("logout") ?>" href="<?= $view->router()->path('pimcore_admin_logout') ?>" style="display: none">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path d="M10.06,17.09l1.8,1.8,6.39-6.39L11.86,6.11l-1.8,1.8,3.3,3.31H1v2.56H13.36ZM21.44,1H3.56A2.55,2.55,0,0,0,1,3.56V8.67H3.56V3.56H21.44V21.44H3.56V16.33H1v5.11A2.55,2.55,0,0,0,3.56,24H21.44A2.56,2.56,0,0,0,24,21.44V3.56A2.56,2.56,0,0,0,21.44,1Z" transform="translate(-1 -1)"/></svg>
    </a>
    <div id="pimcore_signet" data-menu-tooltip="Pimcore Platform (<?= \Pimcore\Version::getVersion() ?>|<?= \Pimcore\Version::getRevision() ?>)" style="text-indent: -10000px">
        BE RESPECTFUL AND HONOR OUR WORK FOR FREE & OPEN SOURCE SOFTWARE BY NOT REMOVING OUR LOGO.
        WE OFFER YOU THE POSSIBILITY TO ADDITIONALLY ADD YOUR OWN LOGO IN PIMCORE'S SYSTEM SETTINGS. THANK YOU!
    </div>
</div>

<div id="pimcore_tooltip" style="display: none;"></div>
<div id="pimcore_quicksearch"></div>

<?php // define stylesheets ?>
<?php

$disableMinifyJs = Pimcore::disableMinifyJs();

// SCRIPT LIBRARIES
$debugSuffix = "";
if ($disableMinifyJs) {
    $debugSuffix = "-debug";
}

$styles = array(
    "/admin/misc/admin-css",
    "/bundles/pimcoreadmin/css/icons.css",
    "/bundles/pimcoreadmin/js/lib/leaflet/leaflet.css",
    "/bundles/pimcoreadmin/js/lib/leaflet.draw/leaflet.draw.css",
    "/bundles/pimcoreadmin/js/lib/ext/classic/theme-triton/resources/theme-triton-all.css",
    "/bundles/pimcoreadmin/js/lib/ext/classic/theme-triton/resources/charts-all" . $debugSuffix . ".css",
    "/bundles/pimcoreadmin/css/admin.css"
);
?>

<!-- stylesheets -->
<style type="text/css">
    <?php
    // use @import here, because if IE9 CSS file limitations (31 files)
    // see also: http://blogs.telerik.com/blogs/posts/10-05-03/internet-explorer-css-limits.aspx
    // @import bypasses this problem in an elegant way
    foreach ($styles as $style) { ?>
    @import url(<?= $style ?>?_dc=<?= \Pimcore\Version::getRevision() ?>);
    <?php } ?>
</style>


<?php //****************************************************************************************** ?>


<?php // define scripts ?>
<?php


$scriptLibs = array(

    // library
    "lib/class.js",
    "lib/jquery-3.3.1.min.js",
    "lib/ext/ext-all" . $debugSuffix . ".js",
    "lib/ext/classic/theme-triton/theme-triton" . $debugSuffix . ".js",

    "lib/ext/packages/charts/classic/charts" . $debugSuffix . ".js",              // TODO

    "lib/ext-plugins/portlet/PortalDropZone.js",
    "lib/ext-plugins/portlet/Portlet.js",
    "lib/ext-plugins/portlet/PortalColumn.js",
    "lib/ext-plugins/portlet/PortalPanel.js",

    "lib/ckeditor/ckeditor.js",

    "lib/leaflet/leaflet.js",
    "lib/leaflet.draw/leaflet.draw.js",
    "lib/vrview/build/vrview.min.js",

    // locale
    "lib/ext/classic/locale/locale-" . $language . ".js",
);

// PIMCORE SCRIPTS
$scripts = array(

    // runtime
    "pimcore/functions.js",
    "pimcore/common.js",
    "pimcore/elementservice.js",
    "pimcore/helpers.js",
    "pimcore/error.js",

    "pimcore/treenodelocator.js",
    "pimcore/helpers/generic-grid.js",
    "pimcore/helpers/quantityValue.js",
    "pimcore/overrides.js",

    "pimcore/perspective.js",
    "pimcore/user.js",

    // tools
    "pimcore/tool/paralleljobs.js",
    "pimcore/tool/genericiframewindow.js",

    // settings
    "pimcore/settings/user/panels/abstract.js",
    "pimcore/settings/user/panel.js",

    "pimcore/settings/user/usertab.js",
    "pimcore/settings/user/editorSettings.js",
    "pimcore/settings/user/websiteTranslationSettings.js",
    "pimcore/settings/user/role/panel.js",
    "pimcore/settings/user/role/tab.js",
    "pimcore/settings/user/user/objectrelations.js",
    "pimcore/settings/user/user/settings.js",
    "pimcore/settings/user/user/keyBindings.js",
    "pimcore/settings/user/workspaces.js",
    "pimcore/settings/user/workspace/asset.js",
    "pimcore/settings/user/workspace/document.js",
    "pimcore/settings/user/workspace/object.js",
    "pimcore/settings/user/workspace/customlayouts.js",
    "pimcore/settings/user/workspace/language.js",
    "pimcore/settings/user/workspace/special.js",
    "pimcore/settings/user/role/settings.js",
    "pimcore/settings/profile/panel.js",
    "pimcore/settings/profile/twoFactorSettings.js",
    "pimcore/settings/thumbnail/item.js",
    "pimcore/settings/thumbnail/panel.js",
    "pimcore/settings/videothumbnail/item.js",
    "pimcore/settings/videothumbnail/panel.js",
    "pimcore/settings/translations.js",
    "pimcore/settings/translationEditor.js",
    "pimcore/settings/translation/website.js",
    "pimcore/settings/translation/admin.js",
    "pimcore/settings/translation/translationmerger.js",
    "pimcore/settings/translation/xliff.js",
    "pimcore/settings/translation/word.js",
    "pimcore/settings/metadata/predefined.js",
    "pimcore/settings/properties/predefined.js",
    "pimcore/settings/docTypes.js",
    "pimcore/settings/system.js",
    "pimcore/settings/web2print.js",
    "pimcore/settings/website.js",
    "pimcore/settings/staticroutes.js",
    "pimcore/settings/redirects.js",
    "pimcore/settings/glossary.js",
    "pimcore/settings/recyclebin.js",
    "pimcore/settings/fileexplorer/file.js",
    "pimcore/settings/fileexplorer/explorer.js",
    "pimcore/settings/maintenance.js",
    "pimcore/settings/robotstxt.js",
    "pimcore/settings/httpErrorLog.js",
    "pimcore/settings/email/log.js",
    "pimcore/settings/email/blacklist.js",
    "pimcore/settings/targeting/condition/abstract.js",
    "pimcore/settings/targeting/conditions.js",
    "pimcore/settings/targeting/action/abstract.js",
    "pimcore/settings/targeting/actions.js",
    "pimcore/settings/targeting/rules/panel.js",
    "pimcore/settings/targeting/rules/item.js",
    "pimcore/settings/targeting/targetGroups/panel.js",
    "pimcore/settings/targeting/targetGroups/item.js",
    "pimcore/settings/targeting_toolbar.js",

    "pimcore/settings/gdpr/gdprPanel.js",
    "pimcore/settings/gdpr/dataproviders/assets.js",
    "pimcore/settings/gdpr/dataproviders/dataObjects.js",
    "pimcore/settings/gdpr/dataproviders/sentMail.js",
    "pimcore/settings/gdpr/dataproviders/pimcoreUsers.js",

    // element
    "pimcore/element/abstract.js",
    "pimcore/element/selector/selector.js",
    "pimcore/element/selector/abstract.js",
    "pimcore/element/selector/document.js",
    "pimcore/element/selector/asset.js",
    "pimcore/element/properties.js",
    "pimcore/element/scheduler.js",
    "pimcore/element/dependencies.js",
    "pimcore/element/metainfo.js",
    "pimcore/element/history.js",
    "pimcore/element/notes.js",
    "pimcore/element/note_details.js",
    "pimcore/element/workflows.js",
    "pimcore/element/tag/imagecropper.js",
    "pimcore/element/tag/imagehotspotmarkereditor.js",
    "pimcore/element/replace_assignments.js",
    "pimcore/element/permissionchecker.js",
    "pimcore/object/helpers/grid.js",
    "pimcore/object/helpers/gridcolumnconfig.js",
    "pimcore/object/helpers/gridConfigDialog.js",
    "pimcore/object/helpers/import/csvPreviewTab.js",
    "pimcore/object/helpers/import/columnConfigurationTab.js",
    "pimcore/object/helpers/import/resolverSettingsTab.js",
    "pimcore/object/helpers/import/csvSettingsTab.js",
    "pimcore/object/helpers/import/saveAndShareTab.js",
    "pimcore/object/helpers/import/configDialog.js",
    "pimcore/object/helpers/import/reportTab.js",
    "pimcore/object/helpers/classTree.js",
    "pimcore/object/helpers/gridTabAbstract.js",
    "pimcore/object/helpers/gridCellEditor.js",
    "pimcore/object/helpers/metadataMultiselectEditor.js",
    "pimcore/object/helpers/customLayoutEditor.js",
    "pimcore/object/helpers/optionEditor.js",
    "pimcore/object/helpers/imageGalleryDropZone.js",
    "pimcore/object/helpers/imageGalleryPanel.js",
    "pimcore/element/selector/object.js",
    "pimcore/element/tag/configuration.js",
    "pimcore/element/tag/assignment.js",
    "pimcore/element/tag/tree.js",

    // documents
    "pimcore/document/properties.js",
    "pimcore/document/document.js",
    "pimcore/document/page_snippet.js",
    "pimcore/document/edit.js",
    "pimcore/document/versions.js",
    "pimcore/document/settings_abstract.js",
    "pimcore/document/pages/settings.js",
    "pimcore/document/pages/preview.js",
    "pimcore/document/snippets/settings.js",
    "pimcore/document/emails/settings.js",
    "pimcore/document/newsletters/settings.js",
    "pimcore/document/newsletters/sendingPanel.js",
    "pimcore/document/newsletters/addressSourceAdapters/default.js",
    "pimcore/document/newsletters/addressSourceAdapters/csvList.js",
    "pimcore/document/newsletters/addressSourceAdapters/report.js",
    "pimcore/document/link.js",
    "pimcore/document/hardlink.js",
    "pimcore/document/folder.js",
    "pimcore/document/tree.js",
    "pimcore/document/snippet.js",
    "pimcore/document/email.js",
    "pimcore/document/newsletter.js",
    "pimcore/document/page.js",
    "pimcore/document/printpages/pdf_preview.js",
    "pimcore/document/printabstract.js",
    "pimcore/document/printpage.js",
    "pimcore/document/printcontainer.js",
    "pimcore/document/seopanel.js",
    "pimcore/document/customviews/tree.js",

    // assets
    "pimcore/asset/asset.js",
    "pimcore/asset/unknown.js",
    "pimcore/asset/image.js",
    "pimcore/asset/document.js",
    "pimcore/asset/video.js",
    "pimcore/asset/audio.js",
    "pimcore/asset/text.js",
    "pimcore/asset/folder.js",
    "pimcore/asset/listfolder.js",
    "pimcore/asset/versions.js",
    "pimcore/asset/metadata.js",
    "pimcore/asset/tree.js",
    "pimcore/asset/customviews/tree.js",

    // object
    "pimcore/object/helpers/edit.js",
    "pimcore/object/helpers/layout.js",
    "pimcore/object/classes/class.js",
    "pimcore/object/class.js",
    "pimcore/object/bulk-export.js",
    "pimcore/object/bulk-import.js",
    "pimcore/object/classes/data/data.js",          // THIS MUST BE THE FIRST FILE, DO NOT MOVE THIS DOWN !!!
    "pimcore/object/classes/data/block.js",
    "pimcore/object/classes/data/classificationstore.js",
    "pimcore/object/classes/data/rgbaColor.js",
    "pimcore/object/classes/data/date.js",
    "pimcore/object/classes/data/datetime.js",
    "pimcore/object/classes/data/encryptedField.js",
    "pimcore/object/classes/data/time.js",
    "pimcore/object/classes/data/manyToOneRelation.js",
    "pimcore/object/classes/data/image.js",
    "pimcore/object/classes/data/externalImage.js",
    "pimcore/object/classes/data/hotspotimage.js",
    "pimcore/object/classes/data/imagegallery.js",
    "pimcore/object/classes/data/video.js",
    "pimcore/object/classes/data/input.js",
    "pimcore/object/classes/data/numeric.js",
    "pimcore/object/classes/data/manyToManyObjectRelation.js",
    "pimcore/object/classes/data/advancedManyToManyRelation.js",
    "pimcore/object/classes/data/advancedManyToManyObjectRelation.js",
    "pimcore/object/classes/data/reverseManyToManyObjectRelation.js",
    "pimcore/object/classes/data/booleanSelect.js",
    "pimcore/object/classes/data/select.js",
    "pimcore/object/classes/data/user.js",
    "pimcore/object/classes/data/textarea.js",
    "pimcore/object/classes/data/wysiwyg.js",
    "pimcore/object/classes/data/checkbox.js",
    "pimcore/object/classes/data/consent.js",
    "pimcore/object/classes/data/slider.js",
    "pimcore/object/classes/data/manyToManyRelation.js",
    "pimcore/object/classes/data/table.js",
    "pimcore/object/classes/data/structuredTable.js",
    "pimcore/object/classes/data/country.js",
    "pimcore/object/classes/data/geo/abstract.js",
    "pimcore/object/classes/data/geopoint.js",
    "pimcore/object/classes/data/geobounds.js",
    "pimcore/object/classes/data/geopolygon.js",
    "pimcore/object/classes/data/language.js",
    "pimcore/object/classes/data/password.js",
    "pimcore/object/classes/data/multiselect.js",
    "pimcore/object/classes/data/link.js",
    "pimcore/object/classes/data/fieldcollections.js",
    "pimcore/object/classes/data/objectbricks.js",
    "pimcore/object/classes/data/localizedfields.js",
    "pimcore/object/classes/data/countrymultiselect.js",
    "pimcore/object/classes/data/languagemultiselect.js",
    "pimcore/object/classes/data/firstname.js",
    "pimcore/object/classes/data/lastname.js",
    "pimcore/object/classes/data/email.js",
    "pimcore/object/classes/data/gender.js",
    "pimcore/object/classes/data/newsletterActive.js",
    "pimcore/object/classes/data/newsletterConfirmed.js",
    "pimcore/object/classes/data/targetGroup.js",
    "pimcore/object/classes/data/targetGroupMultiselect.js",
    "pimcore/object/classes/data/persona.js",
    "pimcore/object/classes/data/personamultiselect.js",
    "pimcore/object/classes/data/quantityValue.js",
    "pimcore/object/classes/data/inputQuantityValue.js",
    "pimcore/object/classes/data/calculatedValue.js",
    "pimcore/object/classes/layout/layout.js",
    "pimcore/object/classes/layout/accordion.js",
    "pimcore/object/classes/layout/fieldset.js",
    "pimcore/object/classes/layout/fieldcontainer.js",
    "pimcore/object/classes/layout/panel.js",
    "pimcore/object/classes/layout/region.js",
    "pimcore/object/classes/layout/tabpanel.js",
    "pimcore/object/classes/layout/button.js",
    "pimcore/object/classes/layout/iframe.js",
    "pimcore/object/classes/layout/text.js",
    "pimcore/object/fieldcollection.js",
    "pimcore/object/fieldcollections/field.js",
    "pimcore/object/gridcolumn/Abstract.js",
    "pimcore/object/gridcolumn/operator/IsEqual.js",
    "pimcore/object/gridcolumn/operator/Text.js",
    "pimcore/object/gridcolumn/operator/Anonymizer.js",
    "pimcore/object/gridcolumn/operator/AnyGetter.js",
    "pimcore/object/gridcolumn/operator/AssetMetadataGetter.js",
    "pimcore/object/gridcolumn/operator/Arithmetic.js",
    "pimcore/object/gridcolumn/operator/Boolean.js",
    "pimcore/object/gridcolumn/operator/BooleanFormatter.js",
    "pimcore/object/gridcolumn/operator/CaseConverter.js",
    "pimcore/object/gridcolumn/operator/CharCounter.js",
    "pimcore/object/gridcolumn/operator/Concatenator.js",
    "pimcore/object/gridcolumn/operator/DateFormatter.js",
    "pimcore/object/gridcolumn/operator/ElementCounter.js",
    "pimcore/object/gridcolumn/operator/Iterator.js",
    "pimcore/object/gridcolumn/operator/JSON.js",
    "pimcore/object/gridcolumn/operator/LocaleSwitcher.js",
    "pimcore/object/gridcolumn/operator/Merge.js",
    "pimcore/object/gridcolumn/operator/ObjectFieldGetter.js",
    "pimcore/object/gridcolumn/operator/PHP.js",
    "pimcore/object/gridcolumn/operator/PHPCode.js",
    "pimcore/object/gridcolumn/operator/Base64.js",
    "pimcore/object/gridcolumn/operator/TranslateValue.js",
    "pimcore/object/gridcolumn/operator/RequiredBy.js",
    "pimcore/object/gridcolumn/operator/StringContains.js",
    "pimcore/object/gridcolumn/operator/StringReplace.js",
    "pimcore/object/gridcolumn/operator/Substring.js",
    "pimcore/object/gridcolumn/operator/LFExpander.js",
    "pimcore/object/gridcolumn/operator/Trimmer.js",
    "pimcore/object/gridcolumn/operator/WorkflowState.js",
    "pimcore/object/gridcolumn/value/Href.js",
    "pimcore/object/gridcolumn/value/Objects.js",
    "pimcore/object/gridcolumn/value/DefaultValue.js",
    "pimcore/object/gridcolumn/operator/GeopointRenderer.js",
    "pimcore/object/gridcolumn/operator/ImageRenderer.js",
    "pimcore/object/gridcolumn/operator/HotspotimageRenderer.js",
    "pimcore/object/importcolumn/Abstract.js",
    "pimcore/object/importcolumn/operator/Base64.js",
    "pimcore/object/importcolumn/operator/Ignore.js",
    "pimcore/object/importcolumn/operator/Iterator.js",
    "pimcore/object/importcolumn/operator/LocaleSwitcher.js",
    "pimcore/object/importcolumn/operator/ObjectBrickSetter.js",
    "pimcore/object/importcolumn/operator/PHPCode.js",
    "pimcore/object/importcolumn/operator/Published.js",
    "pimcore/object/importcolumn/operator/Splitter.js",
    "pimcore/object/importcolumn/operator/Unserialize.js",
    "pimcore/object/importcolumn/value/DefaultValue.js",
    "pimcore/object/objectbrick.js",
    "pimcore/object/objectbricks/field.js",
    "pimcore/object/tags/abstract.js",
    "pimcore/object/tags/block.js",
    "pimcore/object/tags/rgbaColor.js",
    "pimcore/object/tags/date.js",
    "pimcore/object/tags/datetime.js",
    "pimcore/object/tags/time.js",
    "pimcore/object/tags/manyToOneRelation.js",
    "pimcore/object/tags/image.js",
    "pimcore/object/tags/encryptedField.js",
    "pimcore/object/tags/externalImage.js",
    "pimcore/object/tags/hotspotimage.js",
    "pimcore/object/tags/imagegallery.js",
    "pimcore/object/tags/video.js",
    "pimcore/object/tags/input.js",
    "pimcore/object/tags/classificationstore.js",
    "pimcore/object/tags/numeric.js",
    "pimcore/object/tags/manyToManyObjectRelation.js",
    "pimcore/object/tags/advancedManyToManyRelation.js",
    "pimcore/object/gridcolumn/operator/FieldCollectionGetter.js",
    "pimcore/object/gridcolumn/operator/ObjectBrickGetter.js",
    "pimcore/object/tags/advancedManyToManyObjectRelation.js",
    "pimcore/object/tags/reverseManyToManyObjectRelation.js",
    "pimcore/object/tags/booleanSelect.js",
    "pimcore/object/tags/select.js",
    "pimcore/object/tags/user.js",
    "pimcore/object/tags/checkbox.js",
    "pimcore/object/tags/consent.js",
    "pimcore/object/tags/textarea.js",
    "pimcore/object/tags/wysiwyg.js",
    "pimcore/object/tags/slider.js",
    "pimcore/object/tags/manyToManyRelation.js",
    "pimcore/object/tags/table.js",
    "pimcore/object/tags/structuredTable.js",
    "pimcore/object/tags/country.js",
    "pimcore/object/tags/geo/abstract.js",
    "pimcore/object/tags/geobounds.js",
    "pimcore/object/tags/geopoint.js",
    "pimcore/object/tags/geopolygon.js",
    "pimcore/object/tags/language.js",
    "pimcore/object/tags/password.js",
    "pimcore/object/tags/multiselect.js",
    "pimcore/object/tags/link.js",
    "pimcore/object/tags/fieldcollections.js",
    "pimcore/object/tags/localizedfields.js",
    "pimcore/object/tags/countrymultiselect.js",
    "pimcore/object/tags/languagemultiselect.js",
    "pimcore/object/tags/objectbricks.js",
    "pimcore/object/tags/firstname.js",
    "pimcore/object/tags/lastname.js",
    "pimcore/object/tags/email.js",
    "pimcore/object/tags/gender.js",
    "pimcore/object/tags/newsletterActive.js",
    "pimcore/object/tags/newsletterConfirmed.js",
    "pimcore/object/tags/targetGroup.js",
    "pimcore/object/tags/targetGroupMultiselect.js",
    "pimcore/object/tags/persona.js",
    "pimcore/object/tags/personamultiselect.js",
    "pimcore/object/tags/quantityValue.js",
    "pimcore/object/tags/inputQuantityValue.js",
    "pimcore/object/tags/calculatedValue.js",
    "pimcore/object/preview.js",
    "pimcore/object/versions.js",
    "pimcore/object/variantsTab.js",
    "pimcore/object/folder/search.js",
    "pimcore/object/edit.js",
    "pimcore/object/abstract.js",
    "pimcore/object/object.js",
    "pimcore/object/folder.js",
    "pimcore/object/variant.js",
    "pimcore/object/tree.js",
    "pimcore/object/layout/iframe.js",
    "pimcore/object/customviews/tree.js",
    "pimcore/object/quantityvalue/unitsettings.js",

    //plugins
    "pimcore/plugin/broker.js",
    "pimcore/plugin/plugin.js",

    "pimcore/event-dispatcher.js",

    // reports
    "pimcore/report/panel.js",
    "pimcore/report/broker.js",
    "pimcore/report/abstract.js",
    "pimcore/report/settings.js",
    "pimcore/report/analytics/settings.js",
    "pimcore/report/analytics/elementoverview.js",
    "pimcore/report/analytics/elementexplorer.js",
    "pimcore/report/webmastertools/settings.js",
    "pimcore/report/tagmanager/settings.js",
    "pimcore/report/custom/item.js",
    "pimcore/report/custom/panel.js",
    "pimcore/report/custom/settings.js",
    "pimcore/report/custom/report.js",
    "pimcore/report/custom/definitions/sql.js",
    "pimcore/report/custom/definitions/analytics.js",

    "pimcore/settings/tagmanagement/panel.js",
    "pimcore/settings/tagmanagement/item.js",

    "pimcore/report/qrcode/panel.js",
    "pimcore/report/qrcode/item.js",

    // extension manager
    "pimcore/extensionmanager/admin.js",

    // application logging
    "pimcore/log/admin.js",
    "pimcore/log/detailwindow.js",

    // layout
    "pimcore/layout/portal.js",
    "pimcore/layout/portlets/abstract.js",
    "pimcore/layout/portlets/modifiedDocuments.js",
    "pimcore/layout/portlets/modifiedObjects.js",
    "pimcore/layout/portlets/modifiedAssets.js",
    "pimcore/layout/portlets/modificationStatistic.js",
    "pimcore/layout/portlets/analytics.js",
    "pimcore/layout/portlets/piwik.js",
    "pimcore/layout/portlets/customreports.js",

    "pimcore/layout/toolbar.js",
    "pimcore/layout/treepanelmanager.js",
    "pimcore/document/seemode.js",

    // classification store
    "pimcore/object/classificationstore/groupsPanel.js",
    "pimcore/object/classificationstore/propertiesPanel.js",
    "pimcore/object/classificationstore/collectionsPanel.js",
    "pimcore/object/classificationstore/keyDefinitionWindow.js",
    "pimcore/object/classificationstore/keySelectionWindow.js",
    "pimcore/object/classificationstore/relationSelectionWindow.js",
    "pimcore/object/classificationstore/storeConfiguration.js",
    "pimcore/object/classificationstore/storeTree.js",
    "pimcore/object/classificationstore/columnConfigDialog.js",

    //workflow
    "pimcore/workflow/transitionPanel.js",
    "pimcore/workflow/transitions.js",

    // Piwik - this needs to be loaded after treepanel manager as
    // it adds panels in pimcore ready
    "pimcore/analytics/piwik/widget_store_provider.js",
    "pimcore/report/piwik/settings.js",
    "pimcore/report/piwik/dashboard_iframe.js",

    // color picker
    "pimcore/colorpicker-overrides.js",

    //notification
    "pimcore/notification/helper.js",
    "pimcore/notification/panel.js",
    "pimcore/notification/modal.js",
);

?>

<!-- some javascript -->
<?php // pimcore constants ?>
<script>
    pimcore.settings = <?= json_encode($this->settings, JSON_PRETTY_PRINT) ?>;
</script>

<script src="/admin/misc/json-translations-system?language=<?= $language ?>&_dc=<?= \Pimcore\Version::getRevision() ?>"></script>
<script src="<?= $view->router()->path('pimcore_admin_user_getcurrentuser') ?>?_dc=<?= \Pimcore\Version::getRevision() ?>"></script>
<script src="/admin/misc/available-languages?_dc=<?= \Pimcore\Version::getRevision() ?>"></script>


<!-- library scripts -->
<?php foreach ($scriptLibs as $scriptUrl) { ?>
    <script src="/bundles/pimcoreadmin/js/<?= $scriptUrl ?>?_dc=<?= \Pimcore\Version::getRevision() ?>"></script>
<?php } ?>


<!-- internal scripts -->
<?php if ($disableMinifyJs) { ?>
    <?php foreach ($scripts as $scriptUrl) { ?>
    <script src="/bundles/pimcoreadmin/js/<?= $scriptUrl ?>?_dc=<?= \Pimcore\Version::getRevision() ?>"></script>
<?php } ?>
<?php } else { ?>
<?php

    $scriptContents = "";
    foreach ($scripts as $scriptUrl) {
        if (is_file(PIMCORE_WEB_ROOT . "/bundles/pimcoreadmin/js/" . $scriptUrl)) {
            $scriptContents .= file_get_contents(PIMCORE_WEB_ROOT . "/bundles/pimcoreadmin/js/" . $scriptUrl) . "\n\n\n";
        }
    }
    $minimizedScriptPath = \Pimcore\Tool\Admin::getMinimizedScriptPath($scriptContents);

?>
    <script src="<?= $minimizedScriptPath ?>"></script>
<?php } ?>


<?php // load plugin scripts ?>
<?php

// only add the timestamp if the devmode is not activated, otherwise it is very hard to develop and debug plugins,
// because the filename changes on every reload and therefore breakpoints, ... are resetted on every reload
$pluginDcValue = time();
if ($disableMinifyJs) {
    $pluginDcValue = 1;
}
?>

<?php foreach ($this->pluginJsPaths as $pluginJsPath): ?>
    <script src="<?= $pluginJsPath ?>?_dc=<?= $pluginDcValue; ?>"></script>
<?php endforeach; ?>

<?php foreach ($this->pluginCssPaths as $pluginCssPath): ?>
    <link rel="stylesheet" type="text/css" href="<?= $pluginCssPath ?>?_dc=<?= $pluginDcValue; ?>"/>
<?php endforeach; ?>

<?php // MUST BE THE LAST LINE ?>
<script src="/bundles/pimcoreadmin/js/pimcore/startup.js?_dc=<?= \Pimcore\Version::getRevision() ?>"></script>
</body>
</html>

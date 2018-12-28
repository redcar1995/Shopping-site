/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

/*global localStorage */
pimcore.registerNS("pimcore.helpers.x");

pimcore.helpers.registerKeyBindings = function (bindEl, ExtJS) {

    if (!ExtJS) {
        ExtJS = Ext;
    }

    var user = pimcore.globalmanager.get("user");
    var bindings = [];

    var decodedKeyBindings = Ext.decode(user.keyBindings);
    if (decodedKeyBindings) {
        for (var i = 0; i < decodedKeyBindings.length; i++) {
            var item = decodedKeyBindings[i];
            if (item == null) {
                continue;
            }

            if (!item.key) {
                continue;
            }
            var action = item.action;
            var handler = pimcore.helpers.keyBindingMapping[action];
            if (handler) {
                var binding = item;
                item["fn"] = handler;
                bindings.push(binding);
            }
        }
    }

    pimcore.keymap = new ExtJS.util.KeyMap({
        target: bindEl,
        binding: bindings
    });
};

pimcore.helpers.openClassEditor = function () {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("classes")) {
        var toolbar = pimcore.globalmanager.get("layout_toolbar");
        toolbar.editClasses();
    }
};

pimcore.helpers.openWelcomePage = function (keyCode, e) {

    if (e["stopEvent"]) {
        e.stopEvent();
    }

    try {
        pimcore.globalmanager.get("layout_portal_welcome").activate();
    }
    catch (e) {
        pimcore.globalmanager.add("layout_portal_welcome", new pimcore.layout.portal());
    }
};

pimcore.helpers.openAsset = function (id, type, options) {

    if (pimcore.globalmanager.exists("asset_" + id) == false) {

        if (!pimcore.asset[type]) {
            pimcore.globalmanager.add("asset_" + id, new pimcore.asset.unknown(id, options));
        }
        else {
            pimcore.globalmanager.add("asset_" + id, new pimcore.asset[type](id, options));
        }

        pimcore.helpers.rememberOpenTab("asset_" + id + "_" + type);

        if (options != undefined) {
            if (options.ignoreForHistory) {
                var element = pimcore.globalmanager.get("asset_" + id);
                element.setAddToHistory(false);
            }
        }

    }
    else {
        pimcore.globalmanager.get("asset_" + id).activate();
    }
};

pimcore.helpers.closeAsset = function (id) {

    try {
        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
        var tabId = "asset_" + id;
        var panel = Ext.getCmp(tabId);
        if (panel) {
            panel.close();
        } else {
            console.log("to close element not found, doing nothing.");
        }

        pimcore.helpers.removeTreeNodeLoadingIndicator("asset", id);
        pimcore.globalmanager.remove("asset_" + id);
    } catch (e) {
        console.log(e);
    }
};

pimcore.helpers.openDocument = function (id, type, options) {
    if (pimcore.globalmanager.exists("document_" + id) == false) {
        if (pimcore.document[type]) {
            pimcore.globalmanager.add("document_" + id, new pimcore.document[type](id, options));
            pimcore.helpers.rememberOpenTab("document_" + id + "_" + type);

            if (options !== undefined) {
                if (options.ignoreForHistory) {
                    var element = pimcore.globalmanager.get("document_" + id);
                    element.setAddToHistory(false);
                }
            }
        }
    }
    else {
        pimcore.globalmanager.get("document_" + id).activate();
    }
};


pimcore.helpers.closeDocument = function (id) {
    try {
        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
        var tabId = "document_" + id;
        var panel = Ext.getCmp(tabId);
        if (panel) {
            panel.close();
        } else {
            console.log("to close element not found, doing nothing.");
        }


        pimcore.helpers.removeTreeNodeLoadingIndicator("document", id);
        pimcore.globalmanager.remove("document_" + id);
    } catch (e) {
        console.log(e);
    }

};

pimcore.helpers.openObject = function (id, type, options) {
    if (pimcore.globalmanager.exists("object_" + id) == false) {

        if (type != "folder" && type != "variant" && type != "object") {
            type = "object";
        }

        pimcore.globalmanager.add("object_" + id, new pimcore.object[type](id, options));
        pimcore.helpers.rememberOpenTab("object_" + id + "_" + type);

        if (options !== undefined) {
            if (options.ignoreForHistory) {
                var element = pimcore.globalmanager.get("object_" + id);
                element.setAddToHistory(false);
            }
        }
    }
    else {
        var tab = pimcore.globalmanager.get("object_" + id);
        tab.activate();
    }
};

pimcore.helpers.closeObject = function (id) {
    try {
        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
        var tabId = "object_" + id;
        var panel = Ext.getCmp(tabId);
        if (panel) {
            panel.close();
        } else {
            console.log("to close element not found, doing nothing.");
        }

        pimcore.helpers.removeTreeNodeLoadingIndicator("object", id);
        pimcore.globalmanager.remove("object_" + id);
    } catch (e) {
        console.log(e);
    }
};

pimcore.helpers.updateObjectStyle = function (id, treeData) {
    if (treeData) {

        var key = "object_" + id;
        if (pimcore.globalmanager.exists(key)) {
            var editMask = pimcore.globalmanager.get(key);
            if (editMask.tab) {
                if (typeof treeData.icon !== "undefined") {
                    editMask.tab.setIcon(treeData.icon);
                }

                if (typeof treeData.iconCls !== "undefined") {
                    editMask.tab.setIconCls(treeData.iconCls);
                }
            }
        }

        var treeNames = pimcore.elementservice.getElementTreeNames("object");

        for (var index = 0; index < treeNames.length; index++) {
            var treeName = treeNames[index];
            var tree = pimcore.globalmanager.get(treeName);
            if (!tree) {
                continue;
            }
            tree = tree.tree;
            var store = tree.getStore();
            var record = store.getById(id);
            if (record) {
                if (typeof treeData.icon !== "undefined") {
                    record.set("icon", treeData.icon);
                }

                if (typeof treeData.iconCls !== "undefined") {
                    record.set("iconCls", treeData.iconCls);
                }
            }
        }
    }
};

pimcore.helpers.getHistory = function () {
    var history = localStorage.getItem("pimcore_element_history");
    if (!history) {
        history = [];
    } else {
        history = JSON.parse(history);
    }
    return history;
};

pimcore.helpers.recordElement = function (id, type, name) {

    var history = pimcore.helpers.getHistory();

    var newDate = new Date();

    for (var i = history.length - 1; i >= 0; i--) {
        var item = history[i];
        if (item.type == type && item.id == id) {
            history.splice(i, 1);
        }
    }


    var historyItem = {
        id: id,
        type: type,
        name: name,
        time: newDate.getTime()
    };
    history.unshift(historyItem);

    history = history.slice(0, 30);

    var json = JSON.stringify(history);
    localStorage.setItem("pimcore_element_history", json);

    try {
        var historyPanel = pimcore.globalmanager.get("element_history");
        if (historyPanel) {
            var thePair = {
                "id": id,
                "type": type,
                "name": name,
                "time": newDate
            };

            var storeCount = historyPanel.store.getCount();
            for (var i = storeCount - 1; i >= 0; i--) {

                var record = historyPanel.store.getAt(i);
                var data = record.data;
                if (i > 100 || (data.id == id && data.type == type)) {
                    historyPanel.store.remove(record);
                }
            }

            historyPanel.store.insert(0, thePair);
            historyPanel.resultpanel.getView().refresh();
        }
    }
    catch (e) {
        console.log(e);
    }

};

pimcore.helpers.openElement = function (id, type, subtype) {
    if (typeof subtype != "undefined") {
        if (type == "document") {
            pimcore.helpers.openDocument(id, subtype);
        }
        else if (type == "asset") {
            pimcore.helpers.openAsset(id, subtype);
        }
        else if (type == "object") {
            pimcore.helpers.openObject(id, subtype);
        }
    } else {
        Ext.Ajax.request({
            url: "/admin/element/get-subtype",
            params: {
                id: id,
                type: type
            },
            success: function (response) {
                var res = Ext.decode(response.responseText);
                if (res.success) {
                    pimcore.helpers.openElement(res.id, res.type, res.subtype);
                } else {
                    Ext.MessageBox.alert(t("error"), t("element_not_found"));
                }
            }
        });
    }
};

pimcore.helpers.getElementTypeByObject = function (object) {
    var type = null;
    if (object instanceof pimcore.document.document) {
        type = "document";
    } else if (object instanceof pimcore.asset.asset) {
        type = "asset";
    } else if (object instanceof pimcore.object.abstract) {
        type = "object";
    }
    return type;
};

pimcore.helpers.getTreeNodeLoadingIndicatorElements = function (type, id) {
    // display loading indicator on treenode
    var elements = [];
    var treeNames = pimcore.elementservice.getElementTreeNames(type);

    for (index = 0; index < treeNames.length; index++) {
        var treeName = treeNames[index];
        var tree = pimcore.globalmanager.get(treeName);
        if (!tree) {
            continue;
        }
        tree = tree.tree;

        try {
            var store = tree.getStore();
            var node = store.getNodeById(id);
            if (node) {
                var view = tree.getView();
                var nodeEl = Ext.fly(view.getNodeByRecord(node));
                var icon = nodeEl.query(".x-tree-icon")[0];

                var iconEl = Ext.get(icon);
                if (iconEl) {
                    elements.push(iconEl);
                }
            }
        } catch (e) {
            //console.log(e);
        }
    }
    return elements;
};

pimcore.helpers.treeNodeLoadingIndicatorTimeouts = {};

pimcore.helpers.addTreeNodeLoadingIndicator = function (type, id) {

    pimcore.helpers.treeNodeLoadingIndicatorTimeouts[type + id] = window.setTimeout(function () {
        // display loading indicator on treenode
        var iconEls = pimcore.helpers.getTreeNodeLoadingIndicatorElements(type, id);
        for (var index = 0; index < iconEls.length; index++) {
            var iconEl = iconEls[index];
            if (iconEl) {
                iconEl.addCls("pimcore_tree_node_loading_indicator");
            }
        }
    }, 200);
};

pimcore.helpers.removeTreeNodeLoadingIndicator = function (type, id) {

    clearTimeout(pimcore.helpers.treeNodeLoadingIndicatorTimeouts[type + id]);

    // display loading indicator on treenode
    var iconEls = pimcore.helpers.getTreeNodeLoadingIndicatorElements(type, id);
    for (var index = 0; index < iconEls.length; index++) {
        var iconEl = iconEls[index];
        if (iconEl) {
            iconEl.removeCls("pimcore_tree_node_loading_indicator");
        }
    }
};


pimcore.helpers.openSeemode = function () {
    if (pimcore.globalmanager.exists("pimcore_seemode")) {
        pimcore.globalmanager.get("pimcore_seemode").start();
    }
    else {
        pimcore.globalmanager.add("pimcore_seemode", new pimcore.document.seemode());
    }
};

pimcore.helpers.isValidFilename = function (value) {
    var result = value.match(/[a-zA-Z0-9_.\-~]+/);
    if (result == value) {
        // key must be at least one character, an maximum 30 characters
        if (value.length < 1 && value.length > 30) {
            return false;
        }
        return true;
    }
    return false;
};


pimcore.helpers.getValidFilenameCache = {};

pimcore.helpers.getValidFilename = function (value, type) {

    if (pimcore.helpers.getValidFilenameCache[value + type]) {
        return pimcore.helpers.getValidFilenameCache[value + type];
    }

    var response = Ext.Ajax.request({
        url: "/admin/misc/get-valid-filename",
        async: false,
        params: {
            value: value,
            type: type
        }
    });

    var res = Ext.decode(response.responseText);
    pimcore.helpers.getValidFilenameCache[value + type] = res["filename"];
    return res["filename"];
};

pimcore.helpers.showPrettyError = function (type, title, text, errorText, stack, code, hideDelay) {

    if (type != "ValidationException") {
        pimcore.helpers.showNotification(title, text, "error", errorText, hideDelay);
        return;
    }
    if (errorText != null && errorText != undefined) {

        if (t(errorText) != "~" + errorText + "~") {
            errorText = t(errorText);
        }

        text = text + '<br /><hr />' +
            '<span style="font-size:12px">'
            + '<b>' + errorText + '</b>' +
            "</span>";

    }

    if (stack) {
        stack = str_replace("#", "<br>#", stack);
        var htmlValue = '<a href="#">' + t("details") + '</a>';
        var detailedInfo = {
            xtype: "displayfield",
            readOnly: true,
            value: htmlValue,
            width: 300,
            listeners: {
                render: function (c) {
                    c.getEl().on('click', function () {
                        var detailedWindow = new Ext.Window({
                            modal: true,
                            title: t('details'),
                            width: 1000,
                            height: 600,
                            html: stack,
                            autoScroll: true,
                            bodyStyle: "padding: 10px; background:#fff;",
                            buttonAlign: "center",
                            shadow: false,
                            closable: true,
                            buttons: [{
                                text: t("OK"),
                                handler: function () {
                                    detailedWindow.close();
                                }
                            }]
                        });
                        detailedWindow.show();
                    }, c);
                }.bind(this)
            }
        };
    }

    if (code) {
        title = title + " " + code;
    }
    var errWin = new Ext.Window({
        modal: true,
        iconCls: "pimcore_icon_error",
        title: title,
        width: 600,
        //height: 300,

        layout: 'vbox',
        items: [
            {
                xtype: 'panel',
                html: text,
                width: '100%'
            },
            detailedInfo
        ],
        autoScroll: true,
        bodyStyle: "padding: 10px; background:#fff;",
        buttonAlign: "center",
        shadow: false,
        closable: false,
        buttons: [{
            text: "OK",
            handler: function () {
                errWin.close();
            }
        }]
    });
    errWin.show();

};

pimcore.helpers.showNotification = function (title, text, type, errorText, hideDelay) {
    // icon types: info,error,success
    if (type == "error") {

        if (errorText != null && errorText != undefined) {
            text = text + '<br /><hr /><br />' +
                '<pre style="font-size:11px;word-wrap: break-word;">'
                + strip_tags(errorText) +
                "</pre>";
        }

        var errWin = new Ext.Window({
            modal: true,
            iconCls: "pimcore_icon_error",
            title: title,
            width: 700,
            maxHeight: 500,
            html: text,
            autoScroll: true,
            bodyStyle: "padding: 10px; background:#fff;",
            buttonAlign: "center",
            shadow: false,
            closable: false,
            buttons: [{
                text: t("OK"),
                handler: function () {
                    errWin.close();
                }
            }]
        });
        errWin.show();
    } else {
        var notification = Ext.create('Ext.window.Toast', {
            iconCls: 'pimcore_icon_' + type,
            title: title,
            html: text,
            autoShow: true,
            width: 'auto',
            maxWidth: 350,
            closeable: true
            //autoDestroy: true
            //,
            //hideDelay:  hideDelay | 1000
        });
        notification.show(document);
    }

};


pimcore.helpers.rename = function (keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        // for document
        var el = activeTab.initialConfig;
        if (el.document && el.document.rename) {
            el.document.rename();

        }
        else if (el.object && el.object.rename) {
            el.object.rename();

        }
        else if (el.asset && el.asset.rename) {
            el.asset.rename();
        }
    }
};

pimcore.helpers.togglePublish = function (publish, keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        // for document
        var el = activeTab.initialConfig;
        if (el.document) {
            if (publish) {
                el.document.publish();
            } else {
                el.document.unpublish();
            }
        }
        else if (el.object) {
            if (publish) {
                el.object.publish();
            } else {
                el.object.unpublish();
            }
        }
        else if (el.asset) {
            el.asset.save();
        }
    }
};


pimcore.helpers.handleCtrlS = function (keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        // for document
        var el = activeTab.initialConfig;
        if (el.document) {
            if (el.document.data.published) {
                el.document.publish();
            } else {
                el.document.unpublish();
            }
        }
        else if (el.object) {
            if (el.object.data.general.o_published) {
                el.object.publish();
            } else {
                el.object.unpublish();
            }
        }
        else if (el.asset) {
            el.asset.save();
        }
    }
};

pimcore.helpers.showMetaInfo = function (keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        if (activeTab.initialConfig.document) {
            activeTab.initialConfig.document.showMetaInfo();
        } else if (activeTab.initialConfig.asset) {
            activeTab.initialConfig.asset.showMetaInfo();
        } else if (activeTab.initialConfig.object) {
            activeTab.initialConfig.object.showMetaInfo();
        }
    }
};

pimcore.helpers.openInTree = function (keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        if (activeTab.initialConfig.document || activeTab.initialConfig.asset || activeTab.initialConfig.object) {
            var tabId = activeTab.id;
            var parts = tabId.split("_");
            var type = parts[0];
            var elementId = parts[1];
            pimcore.treenodelocator.showInTree(elementId, type);

        }
    }
};


pimcore.helpers.handleF5 = function (keyCode, e) {

    e.stopEvent();

    var tabpanel = Ext.getCmp("pimcore_panel_tabs");
    var activeTab = tabpanel.getActiveTab();

    if (activeTab) {
        // for document
        if (activeTab.initialConfig.document) {
            activeTab.initialConfig.document.reload();
            return;
        }
        else if (activeTab.initialConfig.object) {
            activeTab.initialConfig.object.reload();
            return;
        }
    }

    var date = new Date();
    location.href = "/admin/?_dc=" + date.getTime();
};

pimcore.helpers.lockManager = function (cid, ctype, csubtype, data) {

    var lockDate = new Date(data.editlock.date * 1000);
    var lockDetails = "<br /><br />";
    lockDetails += "<b>" + t("path") + ": <i>" + data.editlock.cpath + "</i></b><br />";
    lockDetails += "<b>" + t("type") + ": </b>" + t(ctype) + "<br />";
    if (data.editlock.user) {
        lockDetails += "<b>" + t("user") + ":</b> " + data.editlock.user.name + "<br />";
    }
    lockDetails += "<b>" + t("since") + ": </b>" + Ext.util.Format.date(lockDate, "Y-m-d H:i");
    lockDetails += "<br /><br />" + t("element_lock_question");

    Ext.MessageBox.confirm(t("element_is_locked"), t("element_lock_message") + lockDetails,
        function (lock, buttonValue) {
            if (buttonValue == "yes") {
                Ext.Ajax.request({
                    url: "/admin/element/unlock-element",
                    method: 'PUT',
                    params: {
                        id: lock[0],
                        type: lock[1]
                    },
                    success: function () {
                        pimcore.helpers.openElement(lock[0], lock[1], lock[2]);
                    }
                });
            }
        }.bind(this, arguments));
};


pimcore.helpers.closeAllUnmodified = function () {
    var unmodifiedElements = [];

    var tabs = Ext.getCmp("pimcore_panel_tabs").items;
    if (tabs.getCount() > 0) {
        tabs.each(function (item, index, length) {
            if (item.title.indexOf("*") > -1) {
                unmodifiedElements.push(item);
            }
        });
    }
    ;

    pimcore.helpers.closeAllElements(unmodifiedElements);
};

pimcore.helpers.closeAllElements = function (except, tabPanel) {

    var exceptions = [];
    if (except instanceof Ext.Panel) {
        exceptions.push(except);
    } else if (except instanceof Array) {
        exceptions = except;
    }

    if (typeof tabPanel == "undefined") {
        tabPanel = Ext.getCmp("pimcore_panel_tabs");
    }

    var tabs = tabPanel.items;
    if (tabs.getCount() > 0) {
        tabs.each(function (item, index, length) {
            window.setTimeout(function () {
                if (!in_array(item, exceptions)) {
                    tabPanel.remove(item);
                }
            }, 100 * index);
        });
    }
};


pimcore.helpers.loadingShow = function () {
    pimcore.globalmanager.get("loadingmask").show();
};

pimcore.helpers.loadingHide = function () {
    pimcore.globalmanager.get("loadingmask").hide();
};

pimcore.helpers.itemselector = function (muliselect, callback, restrictions, config) {
    var itemselector = new pimcore.element.selector.selector(muliselect, callback, restrictions, config);
};


pimcore.helpers.activateMaintenance = function () {

    Ext.Ajax.request({
        url: "/admin/misc/maintenance?activate=true",
        method: "POST"
    });

    var button = Ext.get("pimcore_menu_maintenance");
    if (!button.isVisible()) {
        pimcore.helpers.showMaintenanceDisableButton();
    }
};

pimcore.helpers.deactivateMaintenance = function () {

    Ext.Ajax.request({
        url: "/admin/misc/maintenance?deactivate=true",
        method: "POST"
    });

    var button = Ext.get("pimcore_menu_maintenance");
    button.setStyle("display", "none");
};

pimcore.helpers.showMaintenanceDisableButton = function () {
    var button = Ext.get("pimcore_menu_maintenance");
    button.show();
    button.clearListeners();
    button.on("click", pimcore.helpers.deactivateMaintenance);
};

pimcore.helpers.download = function (url) {
    pimcore.settings.showCloseConfirmation = false;
    window.setTimeout(function () {
        pimcore.settings.showCloseConfirmation = true;
    }, 1000);

    url = pimcore.helpers.addCsrfTokenToUrl(url);
    location.href = url;
};

pimcore.helpers.getFileExtension = function (filename) {
    var extensionP = filename.split("\.");
    return extensionP[extensionP.length - 1];
};


pimcore.helpers.getOpenTab = function () {
    var openTabs = localStorage.getItem("pimcore_opentabs");
    if (!openTabs) {
        openTabs = [];
    } else {
        // using native JSON functionalities here because of /admin/login/deeplink -> No ExtJS should be loaded
        openTabs = JSON.parse(openTabs);
    }

    return openTabs;
};

pimcore.helpers.clearOpenTab = function () {
    localStorage.setItem("pimcore_opentabs", JSON.stringify([]));
};

pimcore.helpers.rememberOpenTab = function (item, forceOpenTab) {
    var openTabs = pimcore.helpers.getOpenTab();

    if (!in_array(item, openTabs)) {
        openTabs.push(item);
    }

    // limit to the latest 10
    openTabs.reverse();
    openTabs.splice(10, 1000);
    openTabs.reverse();

    // using native JSON functionalities here because of /admin/login/deeplink -> No ExtJS should be loaded
    localStorage.setItem("pimcore_opentabs", JSON.stringify(openTabs));
    if (forceOpenTab) {
        localStorage.setItem("pimcore_opentabs_forceopenonce", true);
    }
};

pimcore.helpers.forgetOpenTab = function (item) {

    var openTabs = pimcore.helpers.getOpenTab();

    if (in_array(item, openTabs)) {
        var pos = array_search(item, openTabs);
        openTabs.splice(pos, 1);
    }

    // using native JSON functionalities here because of /admin/login/deeplink -> No ExtJS should be loaded
    localStorage.setItem("pimcore_opentabs", JSON.stringify(openTabs));
};

pimcore.helpers.forceOpenMemorizedTabsOnce = function () {
    if (localStorage.getItem("pimcore_opentabs_forceopenonce")) {
        localStorage.removeItem("pimcore_opentabs_forceopenonce");
        return true;
    }
    return false;
};

pimcore.helpers.openMemorizedTabs = function () {
    var openTabs = pimcore.helpers.getOpenTab();
    var openedTabs = [];

    for (var i = 0; i < openTabs.length; i++) {
        if (!empty(openTabs[i])) {
            if (!in_array(openTabs[i], openedTabs)) {
                var parts = openTabs[i].split("_");
                window.setTimeout(function (parts) {
                    if (parts[1] && parts[2]) {
                        if (parts[0] == "asset") {
                            pimcore.helpers.openAsset(parts[1], parts[2], {
                                ignoreForHistory: true,
                                ignoreNotFoundError: true
                            });
                        } else if (parts[0] == "document") {
                            pimcore.helpers.openDocument(parts[1], parts[2], {
                                ignoreForHistory: true,
                                ignoreNotFoundError: true
                            });
                        } else if (parts[0] == "object") {
                            pimcore.helpers.openObject(parts[1], parts[2], {
                                ignoreForHistory: true,
                                ignoreNotFoundError: true
                            });
                        }
                    }
                }.bind(this, parts), 200);
            }
            openedTabs.push(openTabs[i]);
        }
    }
};

pimcore.helpers.assetSingleUploadDialog = function (parent, parentType, success, failure, context) {

    if (typeof success != "function") {
        success = function () {
        };
    }

    if (typeof failure != "function") {
        failure = function () {
        };
    }

    var url = '/admin/asset/add-asset-compatibility?parent' + ucfirst(parentType) + '=' + parent;

    if (context) {
        url += "&context=" + Ext.encode(context);
    }
    url = pimcore.helpers.addCsrfTokenToUrl(url);

    var uploadWindowCompatible = new Ext.Window({
        autoHeight: true,
        title: t('add_assets'),
        closeAction: 'close',
        width: 400,
        modal: true
    });

    var uploadForm = new Ext.form.FormPanel({
        fileUpload: true,
        width: 400,
        bodyStyle: 'padding: 10px;',
        items: [{
            xtype: 'fileuploadfield',
            emptyText: t("select_a_file"),
            fieldLabel: t("asset"),
            width: 360,
            name: 'Filedata',
            buttonText: "",
            buttonConfig: {
                iconCls: 'pimcore_icon_upload'
            },
            listeners: {
                change: function () {
                    uploadForm.getForm().submit({
                        url: url,
                        waitMsg: t("please_wait"),
                        success: function (el, res) {
                            success(res);
                            uploadWindowCompatible.close();
                        },
                        failure: function (el, res) {
                            failure(res);
                            uploadWindowCompatible.close();
                            pimcore.helpers.showNotification(t("error"), res.response.responseText, "error");
                        }
                    });
                }
            }
        }]
    });

    uploadWindowCompatible.add(uploadForm);
    uploadWindowCompatible.show();
    uploadWindowCompatible.setWidth(401);
    uploadWindowCompatible.updateLayout();
};

pimcore.helpers.addCsrfTokenToUrl = function (url) {

    // only for /admin urls
    if(url.indexOf('/admin') !== 0) {
        return url;
    }

    if (url.indexOf('?') === -1) {
        url = url + "?";
    } else {
        url = url + "&";
    }
    url = url + 'csrfToken=' + pimcore.settings['csrfToken'];

    return url;
};

pimcore.helpers.uploadDialog = function (url, filename, success, failure) {

    if (typeof success != "function") {
        success = function () {
        };
    }

    if (typeof failure != "function") {
        failure = function () {
        };
    }

    if (typeof filename != "string") {
        filename = "Filedata";
    }

    url = pimcore.helpers.addCsrfTokenToUrl(url);

    if (empty(filename)) {
        filename = "Filedata";
    }

    var uploadWindowCompatible = new Ext.Window({
        autoHeight: true,
        title: t('upload'),
        closeAction: 'close',
        width: 400,
        modal: true
    });

    var uploadForm = new Ext.form.FormPanel({
        fileUpload: true,
        width: 500,
        bodyStyle: 'padding: 10px;',
        items: [{
            xtype: 'fileuploadfield',
            emptyText: t("select_a_file"),
            fieldLabel: t("file"),
            width: 470,
            name: filename,
            buttonText: "",
            buttonConfig: {
                iconCls: 'pimcore_icon_upload'
            },
            listeners: {
                change: function () {
                    uploadForm.getForm().submit({
                        url: url,
                        waitMsg: t("please_wait"),
                        success: function (el, res) {
                            // content-type in response has to be text/html, otherwise (when application/json is sent)
                            // chrome will complain in Ext.form.Action.Submit and mark the submission as failed
                            success(res);
                            uploadWindowCompatible.close();
                        },
                        failure: function (el, res) {
                            failure(res);
                            uploadWindowCompatible.close();
                        }
                    });
                }
            }
        }]
    });

    uploadWindowCompatible.add(uploadForm);
    uploadWindowCompatible.show();
    uploadWindowCompatible.setWidth(501);
    uploadWindowCompatible.updateLayout();
};


pimcore.helpers.getClassForIcon = function (icon) {

    var styleContainerId = "pimcore_dynamic_class_for_icon";
    var styleContainer = Ext.get(styleContainerId);
    if (!styleContainer) {
        styleContainer = Ext.getBody().insertHtml("beforeEnd", '<style type="text/css" id="' + styleContainerId
            + '"></style>', true);
    }

    var content = styleContainer.dom.innerHTML;
    var classname = "pimcore_dynamic_class_for_icon_" + uniqid();
    content += ("." + classname + " { background: url(" + icon + ") left center no-repeat !important; }\n");
    styleContainer.dom.innerHTML = content;

    return classname;
};

pimcore.helpers.searchAction = function (type) {
    pimcore.helpers.itemselector(false, function (selection) {
            pimcore.helpers.openElement(selection.id, selection.type, selection.subtype);
        }, {type: [type]},
        {moveToTab: true,
            context: {
                scope: "globalSearch"
            }
        });
};


pimcore.helpers.openElementByIdDialog = function (type, keyCode, e) {

    if (e["stopEvent"]) {
        e.stopEvent();
    }

    Ext.MessageBox.prompt(t('open_' + type + '_by_id'), t('please_enter_the_id_of_the_' + type),
        function (button, value, object) {
            if (button == "ok" && !Ext.isEmpty(value)) {
                pimcore.helpers.openElement(value, type);
            }
        });
};

pimcore.helpers.openDocumentByPath = function (path) {
    pimcore.helpers.openElement(path, "document");
};

pimcore.helpers.sanitizeAllowedTypes = function (data, name) {
    if (data[name]) {
        var newList = [];
        for (var i = 0; i < data[name].length; i++) {
            newList.push(data[name][i][name]);
        }
        data[name] = newList;
    }
};


pimcore.helpers.generatePagePreview = function (id, path, callback) {

    var cb = callback;

    if (pimcore.settings.htmltoimage) {
        Ext.Ajax.request({
            url: '/admin/page/generate-screenshot',
            method: "POST",
            ignoreErrors: true,
            params: {
                id: id
            },
            success: function () {
                if (typeof cb == "function") {
                    cb();
                }
            }
        });
    }
};

pimcore.helpers.treeNodeThumbnailTimeout = null;
pimcore.helpers.treeNodeThumbnailLastClose = 0;

pimcore.helpers.treeNodeThumbnailPreview = function (treeView, record, item, index, e, eOpts) {

    if (typeof record.data["thumbnail"] != "undefined" ||
        typeof record.data["thumbnails"] != "undefined") {

        // only display thumbnails when dnd is not active
        if (Ext.dd.DragDropMgr.dragCurrent) {
            return;
        }

        var imageHtml = "";
        var uriPrefix = window.location.protocol + "//" + window.location.host;

        var thumbnails = record.data["thumbnails"];
        if (thumbnails && thumbnails.length) {
            imageHtml += '<div class="thumbnails">';
            for (var i = 0; i < thumbnails.length; i++) {
                imageHtml += '<div class="thumb small"><img src="' + uriPrefix + thumbnails[i]
                    + '" onload="this.parentNode.className += \' complete\';" /></div>';
            }
            imageHtml += '</div>';
        }

        var thumbnail = record.data["thumbnail"];
        if (thumbnail) {
            var srcset = thumbnail + ' 1x';
            var thumbnailHdpi = record.data["thumbnailHdpi"];
            if(thumbnailHdpi) {
                    srcset += ', ' + thumbnailHdpi + " 2x";
            }

            imageHtml = '<div class="thumb big"><img src="' + uriPrefix + thumbnail
                + '" onload="this.parentNode.className += \' complete\';" srcset="' + srcset + '" /></div>';
        }

        if (imageHtml) {
            var treeEl = Ext.get("pimcore_panel_tree_" + this.position);
            var position = treeEl.getOffsetsTo(Ext.getBody());
            position = position[0];

            if (this.position == "right") {
                position = position - 420;
            } else {
                position = treeEl.getWidth() + position;
            }

            var container = Ext.get("pimcore_tree_preview");
            if (!container) {
                container = Ext.getBody().insertHtml("beforeEnd", '<div id="pimcore_tree_preview"></div>');
                container = Ext.get(container);
                container.addCls("hidden");
            }

            // check for an existing iframe
            var existingIframe = container.query("iframe")[0];
            if (existingIframe) {
                // stop loading the existing iframe (images, etc.)
                var existingIframeWin = existingIframe.contentWindow;
                if (typeof existingIframeWin["stop"] == "function") {
                    existingIframeWin.stop();
                } else if (typeof existingIframeWin.document["execCommand"] == "function") {
                    existingIframeWin.document.execCommand('Stop');
                }
            }

            var styles = "left: " + position + "px";

            // we need to create an iframe so that we can use window.stop();
            var iframe = document.createElement("iframe");
            iframe.setAttribute("frameborder", "0");
            iframe.setAttribute("scrolling", "no");
            iframe.setAttribute("marginheight", "0");
            iframe.setAttribute("marginwidth", "0");
            iframe.setAttribute("style", "width: 100%; height: 2500px;");

            imageHtml =
                '<style type="text/css">' +
                'body { margin:0; padding: 0; } ' +
                '.thumbnails { width: 410px; } ' +
                '.thumb { border: 1px solid #999; background: url(' + uriPrefix + '/bundles/pimcoreadmin/img/flat-color-icons/hourglass.svg) no-repeat center center; background-size: 20px 20px; box-sizing: border-box; } ' +
                '.big { min-height: 300px; } ' +
                '.complete { border:none; border-radius: 0; background:none; }' +
                '.small { width: 130px; height: 130px; float: left; overflow: hidden; margin: 0 5px 5px 0; } ' +
                '.small.complete img { min-width: 100%; max-height: 100%; } ' +
                '.big.complete img { max-width: 100%; } ' +
                '/* firefox fix: remove loading/broken image icon */ @-moz-document url-prefix() { img:-moz-loading { visibility: hidden; } img:-moz-broken { -moz-force-broken-image-icon: 0;}} ' +
                '</style>' +
                imageHtml;

            iframe.onload = function () {
                this.contentWindow.document.body.innerHTML = imageHtml;
            };

            container.update(""); // remove all
            container.clean(true);
            container.dom.appendChild(iframe);
            container.applyStyles(styles);

            var date = new Date();
            if (pimcore.helpers.treeNodeThumbnailLastClose === 0 || (date.getTime() - pimcore.helpers.treeNodeThumbnailLastClose) > 300) {
                // open deferred
                pimcore.helpers.treeNodeThumbnailTimeout = window.setTimeout(function () {
                    container.removeCls("hidden");
                }, 500);
            } else {
                // open immediately
                container.removeCls("hidden");
            }
        }
    }
};

pimcore.helpers.treeNodeThumbnailPreviewHide = function () {

    if (pimcore.helpers.treeNodeThumbnailTimeout) {
        clearTimeout(pimcore.helpers.treeNodeThumbnailTimeout);
        pimcore.helpers.treeNodeThumbnailTimeout = null;
    }

    var container = Ext.get("pimcore_tree_preview");
    if (container) {
        if (!container.hasCls("hidden")) {
            var date = new Date();
            pimcore.helpers.treeNodeThumbnailLastClose = date.getTime();
        }
        container.addCls("hidden");
    }
};

pimcore.helpers.showUser = function (specificUser) {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("users")) {
        var panel = null;
        try {
            panel = pimcore.globalmanager.get("users");
            panel.activate();
        }
        catch (e) {
            panel = new pimcore.settings.user.panel();
            pimcore.globalmanager.add("users", panel);
        }

        if (specificUser) {
            panel.openUser(specificUser);
        }
    }
};

pimcore.helpers.insertTextAtCursorPosition = function (text) {

    // get focused element
    var focusedElement = document.activeElement;
    var win = window;
    var doc = document;

    // now check if the focus is inside an iframe
    try {
        while (focusedElement.tagName.toLowerCase() == "iframe") {
            win = window[focusedElement.getAttribute("name")];
            doc = win.document;
            focusedElement = doc.activeElement;
        }
    } catch (e) {
        console.log(e);
    }

    var elTagName = focusedElement.tagName.toLowerCase();

    if (elTagName == "input" || elTagName == "textarea") {
        insertTextToFormElementAtCursor(focusedElement, text);
    } else if (elTagName == "div" && focusedElement.getAttribute("contenteditable")) {
        insertTextToContenteditableAtCursor(text, win, doc);
    }

};


pimcore.helpers.getMainTabMenuItems = function () {
    items = [{
        text: t('close_others'),
        iconCls: "",
        handler: function (menuItem) {
            var tabPanel = Ext.getCmp("pimcore_panel_tabs");
            var plugin = tabPanel.getPlugin("tabclosemenu");
            el = plugin.item;
            pimcore.helpers.closeAllElements(el);
            // clear the opentab store, so that also non existing elements are flushed
            pimcore.helpers.clearOpenTab();
        }.bind(this)
    }, {
        text: t('close_unmodified'),
        iconCls: "",
        handler: function (item) {
            pimcore.helpers.closeAllUnmodified();
            // clear the opentab store, so that also non existing elements are flushed
            pimcore.helpers.clearOpenTab();
        }.bind(this)
    }];


    // every tab panel can get this
    items.push({
        text: t('close_all'),
        iconCls: "",
        handler: function (item) {
            var tabPanel = Ext.getCmp("pimcore_panel_tabs");
            pimcore.helpers.closeAllElements(null, tabPanel);
            // clear the opentab store, so that also non existing elements are flushed
            pimcore.helpers.clearOpenTab();
        }.bind(this)
    });

    return items;
};


//pimcore.helpers.handleTabRightClick = function (tabPanel, el, index) {
//
//
//    if(Ext.get(el.tab)) {
//        Ext.get(el.tab).on("contextmenu", function (e) {
//
//            var items = [];
//
//            // this is only for the main tab panel
//            if(tabPanel.getId() == "pimcore_panel_tabs") {
//                items = [{
//                    text: t('close_others'),
//                    iconCls: "",
//                    handler: function (item) {
//                        pimcore.helpers.closeAllElements(el);
//                        // clear the opentab store, so that also non existing elements are flushed
//                        pimcore.helpers.clearOpenTab();
//                    }.bind(this)
//                }, {
//                    text: t('close_unmodified'),
//                    iconCls: "",
//                    handler: function (item) {
//                        pimcore.helpers.closeAllUnmodified();
//                        // clear the opentab store, so that also non existing elements are flushed
//                        pimcore.helpers.clearOpenTab();
//                    }.bind(this)
//                }];
//            }
//
//            // every tab panel can get this
//            items.push({
//                text: t('close_all'),
//                iconCls: "",
//                handler: function (item) {
//                    pimcore.helpers.closeAllElements(null,tabPanel);
//                    // clear the opentab store, so that also non existing elements are flushed
//                    pimcore.helpers.clearOpenTab();
//                }.bind(this)
//            });
//
//
//            var menu = new Ext.menu.Menu({
//                items: items
//            });
//
//            menu.showAt(e.getXY());
//            e.stopEvent();
//        });
//    }
//};

pimcore.helpers.uploadAssetFromFileObject = function (file, url, callbackSuccess, callbackProgress, callbackFailure) {

    url = pimcore.helpers.addCsrfTokenToUrl(url);

    if (typeof callbackSuccess != "function") {
        callbackSuccess = function () {
        };
    }
    if (typeof callbackProgress != "function") {
        callbackProgress = function () {
        };
    }
    if (typeof callbackFailure != "function") {
        callbackFailure = function () {
        };
    }

    if (file["size"]) {
        if (file["size"] > pimcore.settings["upload_max_filesize"]) {
            callbackSuccess();
            pimcore.helpers.showNotification(t("error"), t("file_is_bigger_that_upload_limit") + " " + file.name, "error");
            return;
        }
    }

    var data = new FormData();
    data.append('Filedata', file);
    data.append("filename", file.name);

    jQuery.ajax({
        xhr: function () {
            var xhr = new window.XMLHttpRequest();

            //Upload progress
            xhr.upload.addEventListener("progress", function (evt) {
                callbackProgress(evt);
            }, false);

            return xhr;
        },
        processData: false,
        contentType: false,
        type: 'POST',
        url: url,
        data: data,
        success: callbackSuccess,
        error: callbackFailure
    });

};


pimcore.helpers.searchAndMove = function (parentId, callback, type) {
    if (type == "object") {
        config = {
            type: ["object"],
            subtype: {
                object: ["object", "folder"]
            },
            specific: {
                classes: null
            }
        };
    } else {
        config = {
            type: [type]
        }
    }
    pimcore.helpers.itemselector(true, function (selection) {

        var jobs = [];

        if (selection && selection.length > 0) {
            for (var i = 0; i < selection.length; i++) {
                var params;
                if (type == "object") {
                    params = {
                        id: selection[i]["id"],
                        values: Ext.encode({
                            parentId: parentId
                        })
                    };
                } else {
                    params = {
                        id: selection[i]["id"],
                        parentId: parentId
                    };
                }
                jobs.push([{
                    url: "/admin/" + type + "/update",
                    method: 'PUT',
                    params: params
                }]);
            }
        }

        if (jobs.length == 0) {
            return;
        }

        this.addChildProgressBar = new Ext.ProgressBar({
            text: t('initializing')
        });

        this.addChildWindow = new Ext.Window({
            layout: 'fit',
            width: 500,
            bodyStyle: "padding: 10px;",
            closable: false,
            plain: true,
            modal: true,
            items: [this.addChildProgressBar]
        });

        this.addChildWindow.show();

        var pj = new pimcore.tool.paralleljobs({
            success: function (callbackFunction) {

                if (this.addChildWindow) {
                    this.addChildWindow.close();
                }

                this.deleteProgressBar = null;
                this.addChildWindow = null;

                if (typeof callbackFunction == "function") {
                    callbackFunction();
                }

                try {
                    var node = pimcore.globalmanager.get("layout_object_tree").tree.getNodeById(this.object.id);
                    if (node) {
                        tree.getStore().load({
                            node: node
                        });
                    }
                } catch (e) {
                    // node is not present
                }
            }.bind(this, callback),
            update: function (currentStep, steps, percent) {
                if (this.addChildProgressBar) {
                    var status = currentStep / steps;
                    this.addChildProgressBar.updateProgress(status, percent + "%");
                }
            }.bind(this),
            failure: function (response) {
                this.addChildWindow.close();
                Ext.MessageBox.alert(t("error"), t(response));
            }.bind(this),
            jobs: jobs
        });

    }.bind(this), config);
};


pimcore.helpers.sendTestEmail = function (from, to, subject, emailType, documentPath, content) {

    if(!emailType) {
        emailType = 'text';
    }

    var emailContentTextField = new Ext.form.TextArea({
        name: "content",
        fieldLabel: t("content"),
        height: 300,
    });
    emailContentTextField.hide();

    var documentTextField = new Ext.form.TextField({
        name: 'documentPath',
        flex: 1,
        editable: false
    });
    var searchDocumentButton = new Ext.Button({
        name: 'searchDocument',
        fieldLabel: t('document'),
        iconCls: 'pimcore_icon_search',
        handler: function() {
            pimcore.helpers.itemselector(false, function(e) {
                documentTextField.setValue(e.fullpath);
            }, {
                type: ["document"],
                subtype: {
                    document: ["email", "newsletter"]
                }
            });
        }
    });

    var documentComponent = Ext.create('Ext.form.FieldContainer', {
        fieldLabel: t('document'),
        layout: 'hbox',
        items: [
            documentTextField,
            searchDocumentButton
        ],
        componentCls: "object_field",
        border: false,
        style: {
            padding: 0
        }
    });
    documentComponent.hide();


    var emailTypeDropdown = new Ext.form.ComboBox({
        name: 'emailType',
        width: 300,
        value: emailType,
        store: [
            ['document', t('document')],
            ['html', t('html')],
            ['text', t('text')]
        ],
        fieldLabel: t('type'),
        listeners: {
            select: function(t) {
                if(t.value == 'text' || t.value == 'html') {
                    emailContentTextField.show();
                } else {
                    emailContentTextField.hide();
                }

                if(t.value == 'document') {
                    documentComponent.show();
                    paramGrid.show();
                } else {
                    documentComponent.hide();
                    paramGrid.hide();
                }
            }
        }
    });

    var fromTextField = new Ext.form.TextField({
        name: "from",
        fieldLabel: t("from"),
    });

    var toTextField = new Ext.form.TextField({
        name: "to",
        fieldLabel: t("to"),
    });

    var subjectTextField = new Ext.form.TextField({
        name: "subject",
        fieldLabel: t("subject"),
    });

    var paramsStore = new Ext.data.ArrayStore({
        fields: [
            {name: 'key', type: 'string', persist: false},
            {name: 'value', type: 'string', persist: false}
        ]
    });

    var paramGrid = Ext.create('Ext.grid.Panel', {
        store: paramsStore,
        columns: [
            {
                text: t('key'),
                dataIndex: 'key',
                editor: new Ext.form.TextField(),
                width: 200
            },
            {
                text: t('value'),
                dataIndex: 'value',
                editor: new Ext.form.TextField(),
                flex: 1
            }
        ],
        stripeRows: true,
        columnLines: true,
        bodyCls: "pimcore_editable_grid",
        autoHeight: true,
        selModel: Ext.create('Ext.selection.CellModel'),
        hideHeaders: false,
        plugins: [
            Ext.create('Ext.grid.plugin.CellEditing', {})
        ],
        tbar: [
            {
                iconCls: "pimcore_icon_table_row pimcore_icon_overlay_add",
                handler: function() {
                    paramsStore.add({'key' : '', 'value': ''});
                }
            },
            {
                xtype: 'label',
                html: t('parameters')
            }
        ]
    });
    paramGrid.hide();

    var win = new Ext.Window({

        width: 800,
        height: 600,
        modal: true,
        title: t("send_test_email"),
        layout: "fit",
        closeAction: "close",
        items: [{
            xtype: "form",
            bodyStyle: "padding:10px;",
            itemId: "form",
            items: [
                fromTextField,
                toTextField,
                subjectTextField,
                emailTypeDropdown,
                emailContentTextField,
                documentComponent,
                paramGrid
            ],
            defaults: {
                width: 780
            }
        }],
        buttons: [{
            text: t("send"),
            iconCls: "pimcore_icon_email",
            handler: function () {
                send();
            }
        }]
    });

    var send = function () {


        var params = win.getComponent("form").getForm().getFieldValues();
        if(emailTypeDropdown.getValue() === 'document') {
            var allRecords = paramsStore
                .queryBy(function() { return true; }) // returns a collection
                .getRange();
            var emailParamsArray = [];
            for (var i = 0; i < allRecords.length; i++) {
                emailParamsArray.push({"key": allRecords[i].data['key'], "value": allRecords[i].data['value']});

            }
            params['mailParamaters'] =  JSON.stringify(emailParamsArray);
        }


        win.disable();
        Ext.Ajax.request({
            url: "/admin/email/send-test-email",
            params: params,
            method: "post",
            success: function () {
                Ext.Msg.show({
                    title: t("send_test_email"),
                    message: t("send_test_email_success"),
                    buttons: Ext.Msg.YESNO,
                    icon: Ext.Msg.QUESTION,
                    fn: function (btn) {
                        win.enable();
                        if (btn === 'no') {
                            win.close();
                        }
                    }
                });
            },
            failure: function () {
                win.close();
            }
        });

    };



    if(emailType) {
        emailTypeDropdown.setValue(emailType);
        if(emailType == 'document') {
            documentComponent.show();
            paramGrid.show();
        }
        if(emailType == 'html' || emailType == 'text') {
            emailContentTextField.show();
        }
    }
    if(documentPath) {
        documentTextField.setValue(documentPath);
    }
    if(content) {
        emailContentTextField.setValue(content);
    }
    if(from) {
        fromTextField.setValue(from);
    }
    if(to) {
        toTextField.setValue(to);
    }
    if(subject) {
        subjectTextField.setValue(subject);
    }


    win.show();


};

/* this is here so that it can be opened in the parent window when in editmode frame */
pimcore.helpers.openImageCropper = function (imageId, data, saveCallback, config) {
    var cropper = new top.pimcore.element.tag.imagecropper(imageId, data, saveCallback, config);
    return cropper;
};

/* this is here so that it can be opened in the parent window when in editmode frame */
pimcore.helpers.openImageHotspotMarkerEditor = function (imageId, data, saveCallback, config) {
    var editor = new pimcore.element.tag.imagehotspotmarkereditor(imageId, data, saveCallback, config);
    return editor;
};


pimcore.helpers.editmode = {};

pimcore.helpers.editmode.openLinkEditPanel = function (data, callback) {


    var internalTypeField = new Ext.form.Hidden({
        fieldLabel: 'internalType',
        value: data.internalType,
        name: 'internalType',
        readOnly: true,
        width: 520
    });

    var linkTypeField = new Ext.form.Hidden({
        fieldLabel: 'linktype',
        value: data.linktype,
        name: 'linktype',
        readOnly: true,
        width: 520
    });

    var fieldPath = new Ext.form.TextField({
        fieldLabel: t('path'),
        value: data.path,
        name: "path",
        width: 520,
        fieldCls: "pimcore_droptarget_input",
        enableKeyEvents: true,
        listeners: {
            keyup: function (el) {
                if (el.getValue().match(/^www\./)) {
                    el.setValue("http://" + el.getValue());
                    internalTypeField.setValue(null);
                    linkTypeField.setValue("direct");
                }
            }
        }
    });


    fieldPath.on("render", function (el) {
        // add drop zone
        new Ext.dd.DropZone(el.getEl(), {
            reference: this,
            ddGroup: "element",
            getTargetFromEvent: function (e) {
                return fieldPath.getEl();
            },

            onNodeOver: function (target, dd, e, data) {
                if (data.records.length === 1 && data.records[0].data.type !== "folder") {
                    return Ext.dd.DropZone.prototype.dropAllowed;
                }
            }.bind(this),

            onNodeDrop: function (target, dd, e, data) {

                if(!pimcore.helpers.dragAndDropValidateSingleItem(data)) {
                    return false;
                }

                data = data.records[0].data;
                if (data.type !== "folder") {
                    internalTypeField.setValue(data.elementType);
                    linkTypeField.setValue('internal');
                    fieldPath.setValue(data.path);
                    return true;
                }
                return false;
            }.bind(this)
        });
    }.bind(this));

    var form = new Ext.FormPanel({
        itemId: "form",
        items: [
            {
                xtype: 'tabpanel',
                deferredRender: false,
                defaults: {autoHeight: true, bodyStyle: 'padding:10px'},
                border: false,
                items: [
                    {
                        title: t('basic'),
                        layout: 'vbox',
                        border: false,
                        defaultType: 'textfield',
                        items: [
                            // do not change the order, the server-side works with setValues - setPath expects
                            // the types are already set correctly
                            internalTypeField,
                            linkTypeField,
                            {
                                fieldLabel: t('text'),
                                name: 'text',
                                value: data.text
                            },
                            {
                                xtype: "fieldcontainer",
                                layout: 'hbox',
                                border: false,
                                items: [fieldPath, {
                                    xtype: "button",
                                    iconCls: "pimcore_icon_search",
                                    style: "margin-left: 5px",
                                    handler: function () {
                                        pimcore.helpers.itemselector(false, function (item) {
                                            if (item) {
                                                internalTypeField.setValue(item.type);
                                                linkTypeField.setValue('internal');
                                                fieldPath.setValue(item.fullpath);
                                                return true;
                                            }
                                        }, {
                                            type: ["asset", "document", "object"]
                                        });
                                    }
                                }]
                            },
                            {
                                xtype: 'fieldset',
                                layout: 'vbox',
                                title: t('properties'),
                                collapsible: false,
                                defaultType: 'textfield',
                                width: '100%',
                                defaults: {
                                    width: 250
                                },
                                items: [
                                    {
                                        xtype: "combo",
                                        fieldLabel: t('target'),
                                        name: 'target',
                                        triggerAction: 'all',
                                        editable: true,
                                        mode: "local",
                                        store: ["", "_blank", "_self", "_top", "_parent"],
                                        value: data.target,
                                        width: 300
                                    },
                                    {
                                        fieldLabel: t('parameters'),
                                        name: 'parameters',
                                        value: data.parameters
                                    },
                                    {
                                        fieldLabel: t('anchor'),
                                        name: 'anchor',
                                        value: data.anchor
                                    },
                                    {
                                        fieldLabel: t('title'),
                                        name: 'title',
                                        value: data.title
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        title: t('advanced'),
                        layout: 'form',
                        defaultType: 'textfield',
                        border: false,
                        items: [
                            {
                                fieldLabel: t('accesskey'),
                                name: 'accesskey',
                                value: data.accesskey
                            },
                            {
                                fieldLabel: t('relation'),
                                name: 'rel',
                                width: 300,
                                value: data.rel
                            },
                            {
                                fieldLabel: ('tabindex'),
                                name: 'tabindex',
                                value: data.tabindex
                            },
                            {
                                fieldLabel: t('class'),
                                name: 'class',
                                width: 300,
                                value: data["class"]
                            },
                            {
                                fieldLabel: t('attributes') + ' (key="value")',
                                name: 'attributes',
                                width: 300,
                                value: data["attributes"]
                            }
                        ]
                    }
                ]
            }
        ],
        buttons: [
            {
                text: t("empty"),
                listeners: {
                    "click": callback["empty"]
                },
                iconCls: "pimcore_icon_empty"
            },
            {
                text: t("cancel"),
                listeners: {
                    "click": callback["cancel"]
                },
                iconCls: "pimcore_icon_cancel"
            },
            {
                text: t("save"),
                listeners: {
                    "click": callback["save"]
                },
                iconCls: "pimcore_icon_save"
            }
        ]
    });


    var window = new Ext.Window({
        modal: false,
        width: 600,
        height: 470,
        title: t("edit_link"),
        items: [form],
        layout: "fit"
    });

    window.show();

    return window;
};


pimcore.helpers.editmode.openVideoEditPanel = function (data, callback) {

    var form = null;
    var fieldPath = new Ext.form.TextField({
        fieldLabel: t('path'),
        itemId: "path",
        value: data.path,
        name: "path",
        width: 420,
        fieldCls: "pimcore_droptarget_input",
        enableKeyEvents: true,
        listeners: {
            keyup: function (el) {
                if ((el.getValue().indexOf("youtu.be") >= 0 || el.getValue().indexOf("youtube.com") >= 0) && el.getValue().indexOf("http") >= 0) {
                    form.getComponent("type").setValue("youtube");
                } else if (el.getValue().indexOf("vimeo") >= 0 && el.getValue().indexOf("http") >= 0) {
                    form.getComponent("type").setValue("vimeo");
                } else if ((el.getValue().indexOf("dai.ly") >= 0 || el.getValue().indexOf("dailymotion") >= 0) && el.getValue().indexOf("http") >= 0) {
                    form.getComponent("type").setValue("dailymotion");
                }
            }.bind(this)
        }
    });

    var poster = new Ext.form.TextField({
        fieldLabel: t('poster_image'),
        value: data.poster,
        name: "poster",
        width: 420,
        fieldCls: "pimcore_droptarget_input",
        enableKeyEvents: true,
        listeners: {
            keyup: function (el) {
                //el.setValue(data.poster)
            }.bind(this)
        }
    });

    var initDD = function (el) {
        // register at global DnD manager
        new Ext.dd.DropZone(el.getEl(), {
            reference: this,
            ddGroup: "element",
            getTargetFromEvent: function (e) {
                return el.getEl();
            },

            onNodeOver: function (target, dd, e, data) {
                if(data.records.length === 1) {
                    data = data.records[0].data;
                    if (target && target.getId() == poster.getId()) {
                        if (data.elementType == "asset" && data.type == "image") {
                            return Ext.dd.DropZone.prototype.dropAllowed;
                        }
                    } else {
                        if (data.elementType == "asset" && data.type == "video") {
                            return Ext.dd.DropZone.prototype.dropAllowed;
                        }
                    }
                }
                return Ext.dd.DropZone.prototype.dropNotAllowed;
            }.bind(this),

            onNodeDrop: function (target, dd, e, data) {

                if(!pimcore.helpers.dragAndDropValidateSingleItem(data)) {
                    return false;
                }

                if (target) {
                    data = data.records[0].data;

                    if (target.getId() == fieldPath.getId()) {
                        if (data.elementType == "asset" && data.type == "video") {
                            fieldPath.setValue(data.path);
                            form.getComponent("type").setValue("asset");
                            return true;
                        }
                    } else if (target.getId() == poster.getId()) {
                        if (data.elementType == "asset" && data.type == "image") {
                            poster.setValue(data.path);
                            return true;
                        }
                    }
                }

                return false;
            }.bind(this)
        });
    };

    fieldPath.on("render", initDD);
    poster.on("render", initDD);

    var searchButton = new Ext.Button({
        iconCls: "pimcore_icon_search",
        handler: function () {
            pimcore.helpers.itemselector(false, function (item) {
                if (item) {
                    fieldPath.setValue(item.fullpath);
                    return true;
                }
            }, {
                type: ["asset"],
                subtype: {
                    asset: ["video"]
                }
            });
        }
    });

    var updateType = function (type) {
        searchButton.enable();

        var labelEl = form.getComponent("pathContainer").getComponent("path").labelEl;
        labelEl.update(t("path"));

        if (type != "asset") {
            searchButton.disable();

            poster.hide();
            poster.setValue("");
            form.getComponent("title").hide();
            form.getComponent("title").setValue("");
            form.getComponent("description").hide();
            form.getComponent("description").setValue("");
        } else {
            poster.show();
            form.getComponent("title").show();
            form.getComponent("description").show();
        }

        if (type == "youtube") {
            labelEl.update("ID");
        }

        if (type == "vimeo") {
            labelEl.update("ID");
        }

        if (type == "dailymotion") {
            labelEl.update("ID");
        }
    };

    form = new Ext.FormPanel({
        itemId: "form",
        bodyStyle: "padding:10px;",
        items: [{
            xtype: "combo",
            itemId: "type",
            fieldLabel: t('type'),
            name: 'type',
            triggerAction: 'all',
            editable: false,
            width: 270,
            mode: "local",
            store: ["asset", "youtube", "vimeo", "dailymotion"],
            value: data.type,
            listeners: {
                select: function (combo) {
                    var type = combo.getValue();
                    updateType(type);
                }.bind(this)
            }
        }, {
            xtype: "fieldcontainer",
            layout: 'hbox',
            border: false,
            itemId: "pathContainer",
            items: [fieldPath, searchButton]
        }, poster, {
            xtype: "textfield",
            name: "title",
            itemId: "title",
            fieldLabel: t('title'),
            width: 420,
            value: data.title
        }, {
            xtype: "textarea",
            itemId: "description",
            name: "description",
            fieldLabel: t('description'),
            width: 420,
            height: 50,
            value: data.description
        }],
        buttons: [
            {
                text: t("save"),
                listeners: {
                    "click": callback["save"]
                },
                iconCls: "pimcore_icon_save"
            },
            {
                text: t("cancel"),
                iconCls: "pimcore_icon_cancel",
                listeners: {
                    "click": callback["cancel"]
                }
            }
        ]
    });


    var window = new Ext.Window({
        width: 500,
        height: 370,
        title: t("video"),
        items: [form],
        layout: "fit",
        listeners: {
            afterrender: function () {
                updateType(data.type);
            }.bind(this)
        }
    });
    window.show();

    return window;
};


pimcore.helpers.showAbout = function () {

    var html = '<div class="pimcore_about_window">';
    html += '<br><img src="/bundles/pimcoreadmin/img/logo-gray.svg" style="width: 300px;"><br>';
    html += '<br><b>Version: ' + pimcore.settings.version + '</b>';
    html += '<br><b>Git Hash: ' + pimcore.settings.build + '</b>';
    html += '<br><br>&copy; by pimcore GmbH, Salzburg, Austria (<a href="https://pimcore.com/" target="_blank">pimcore.com</a>)';
    html += '<br>a proud member of the <a href="http://elements.at" target="_blank">elements group</a>';
    html += '<br><br><a href="https://github.com/pimcore/pimcore/blob/master/LICENSE.md" target="_blank">License</a> | ';
    html += '<a href="https://pimcore.com/en/about/contact" target="_blank">Contact</a> | ';
    html += '<a href="https://pimcore.com/en/about/team" target="_blank">Team</a>';
    html += '<img src="/bundles/pimcoreadmin/img/austria-heart.svg" style="position:absolute;top:172px;right:45px;width:32px;">';
    html += '</div>';

    var win = new Ext.Window({
        title: t("about"),
        width: 500,
        height: 300,
        bodyStyle: "padding: 10px;",
        modal: true,
        html: html
    });

    win.show();
};

pimcore.helpers.markColumnConfigAsFavourite = function (objectId, classId, gridConfigId, searchType, global) {

    try {

        Ext.Ajax.request({
            url: '/admin/object-helper/grid-mark-favourite-column-config',
            method: "post",
            params: {
                objectId: objectId,
                classId: classId,
                gridConfigId: gridConfigId,
                searchType: searchType,
                global: global ? 1 : 0
            },
            success: function (response) {
                try {
                    var rdata = Ext.decode(response.responseText);

                    if (rdata && rdata.success) {
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");

                        if (rdata.spezializedConfigs) {
                            pimcore.helpers.removeOtherConfigs(objectId, classId, gridConfigId, searchType);
                        }
                    }
                    else {
                        pimcore.helpers.showNotification(t("error"), t("saving_failed"),
                            "error", t(rdata.message));
                    }
                } catch (e) {
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this),
            failure: function () {
                pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
            }
        });

    } catch (e3) {
        pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
    }
};


pimcore.helpers.removeOtherConfigs = function (objectId, classId, gridConfigId, searchType) {
    Ext.MessageBox.show({
        title: t('apply_to_all_objects'),
        msg: t('apply_to_all_objects_msg'),
        buttons: Ext.Msg.YESNO,
        icon: Ext.MessageBox.INFO,
        fn: function (btn) {
            if (btn == "yes") {
                Ext.Ajax.request({
                    url: '/admin/object-helper/grid-config-apply-to-all',
                    method: "post",
                    params: {
                        objectId: objectId,
                        classId: classId,
                        gridConfigId: gridConfigId,
                        searchType: searchType,
                    }
                });
            }

        }.bind(this)
    });
};

pimcore.helpers.saveColumnConfig = function (objectId, classId, configuration, searchType, button, callback, settings) {


    try {
        var data = {
            id: objectId,
            class_id: classId,
            gridconfig: Ext.encode(configuration),
            searchType: searchType,
            settings: Ext.encode(settings)
        };

        Ext.Ajax.request({
            url: '/admin/object-helper/grid-save-column-config',
            method: "post",
            params: data,
            success: function (response) {
                try {
                    var rdata = Ext.decode(response.responseText);
                    if (rdata && rdata.success) {
                        if (button) {
                            button.hide();
                        }
                        if (typeof callback == "function") {
                            callback(rdata);
                        }
                        pimcore.helpers.showNotification(t("success"), t("saved_successfully"), "success");
                    }
                    else {
                        pimcore.helpers.showNotification(t("error"), t("saving_failed"),
                            "error", t(rdata.message));
                    }
                } catch (e) {
                    pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
                }
            }.bind(this),
            failure: function () {
                pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
            }
        });

    } catch (e3) {
        pimcore.helpers.showNotification(t("error"), t("saving_failed"), "error");
    }
};

pimcore.helpers.openGenericIframeWindow = function (id, src, iconCls, title) {
    try {
        pimcore.globalmanager.get(id).activate();
    }
    catch (e) {
        pimcore.globalmanager.add(id, new pimcore.tool.genericiframewindow(id, src, iconCls, title));
    }
};

pimcore.helpers.hideRedundantSeparators = function (menu) {
    var showSeparator = false;

    for (var i = 0; i < menu.items.length; i++) {
        var item = menu.items.getAt(i);

        if (item instanceof Ext.menu.Separator) {
            if (!showSeparator || i == menu.items.length - 1) {
                item.hide();
            }
            showSeparator = false;
        } else {
            showSeparator = true;
        }
    }
};

pimcore.helpers.initMenuTooltips = function () {

    var items = jQuery("[data-menu-tooltip]:not(.initialized)");
    jQuery('#pimcore_navigation li:not(:has(>svg))').addClass('compatibility');

    items.mouseenter(function (e) {
        jQuery("#pimcore_tooltip").show();
        jQuery("#pimcore_tooltip").removeClass('right');
        jQuery("#pimcore_tooltip").html(jQuery(this).data("menu-tooltip"));

        var closestEl = jQuery(e.target).closest('[data-menu-tooltip]');
        var offset = closestEl.offset();
        var top = offset.top;
        top = top + (closestEl.height() / 2);

        jQuery("#pimcore_tooltip").css({top: top, left: 60, right: 'auto'});
    });

    items.mouseleave(function () {
        jQuery("#pimcore_tooltip").hide();
    });

    items.addClass("initialized", "true");
};

pimcore.helpers.requestNicePathDataGridDecorator = function (gridView, targets) {
    targets.each(function (record) {
        var el = gridView.getRow(record);
        if (el) {
            el = Ext.fly(el);
            el.addCls("grid_nicepath_requested");
        }
    }, this);

};

pimcore.helpers.requestNicePathData = function (source, targets, config, fieldConfig, context, decorator, responseHandler) {
    if (typeof targets === "undefined" || !fieldConfig.pathFormatterClass) {
        return;
    }

    if (!targets.getCount() > 0) {
        return;
    }

    config = config || {};
    Ext.applyIf(config, {
        idProperty: "id"
    });

    var elementData = {};

    targets.each(function (record) {
        var recordId = record.data[config.idProperty];
        elementData[recordId] = record.data;
    }, this);

    if (decorator) {
        decorator(targets);
    }

    elementData = Ext.encode(elementData);

    Ext.Ajax.request({
        method: 'POST',
        url: "/admin/element/get-nice-path",
        params: {
            source: Ext.encode(source),
            targets: elementData,
            context: Ext.encode(context)

        },
        success: function (response) {
            try {
                var rdata = Ext.decode(response.responseText);
                if (rdata.success) {

                    var responseData = rdata.data;
                    responseHandler(responseData);

                    pimcore.layout.refresh();
                }
            } catch (e) {
                console.log(e);
            }
        }.bind(this)
    });
};


pimcore.helpers.getNicePathHandlerStore = function (store, config, gridView, responseData) {
    config = config || {};
    Ext.applyIf(config, {
        idProperty: "id",
        pathProperty: "path"
    });

    store.ignoreDataChanged = true;
    store.each(function (record, id) {
        var recordId = record.data[config.idProperty];
        if (typeof responseData[recordId] != "undefined") {
            record.set(config.pathProperty, responseData[recordId], {dirty: false});

            var el = gridView.getRow(record);
            if (el) {
                el = Ext.fly(el);
                el.removeCls("grid_nicepath_requested");
            }
        }
    }, this);
    store.ignoreDataChanged = false;

    gridView.updateLayout();

};

pimcore.helpers.isValidPassword = function (pass) {
    var passRegExp = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{10,}$/;
    if (!pass.match(passRegExp)) {
        return false;
    }
    return true;
};

pimcore.helpers.csvExportWarning = function (callback) {

    var iconComponent = new Ext.Component({
        cls: "x-message-box-warning x-dlg-icon"
    });

    var textContainer = Ext.Component({
        html: t('csv_object_export_warning')
    });

    var promptContainer = new Ext.container.Container({
        flex: 1,
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        padding: '0px 0px 0px 10px',
        items: [textContainer]
    });

    var topContainer = new Ext.container.Container({
            layout: 'hbox',
            padding: 10,
            style: {
                overflow: 'hidden'
            },
            items: [iconComponent, promptContainer]
        }
    );

    var enableInheritance = new Ext.form.Checkbox({
        fieldLabel: t('enable_inheritance'),
        name: 'enableInheritance',
        inputValue: true,
        labelWidth: 200
    });

    var objectSettingsContainer = new Ext.form.FieldSet({
        title: t('object_settings'),
        items: [
            enableInheritance
        ]
    });

    var delimiter = new Ext.form.TextField({
        fieldLabel: t('delimiter'),
        name: 'delimiter',
        maxLength: 1,
        labelWidth: 200,
        value: ';'
    });


    var csvSettingsContainer = new Ext.form.FieldSet({
        title: t('csv_settings'),
        items: [
            delimiter
        ]
    });

    var formPanel = new Ext.form.FormPanel({
        bodyStyle: 'padding:10px',
        items: [objectSettingsContainer, csvSettingsContainer]
    });


    var window = new Ext.Window({
        modal: true,
        title: t('export_csv'),
        width: 600,
        height: 450,
        bodyStyle: "padding: 10px; background:#fff;",
        buttonAlign: "center",
        shadow: false,
        closable: true,
        items: [topContainer, formPanel],
        buttons: [{
            text: t("OK"),
            handler: function () {
                callback(formPanel.getValues());
                window.close();
            }.bind(this)
        },
            {
                text: t("cancel"),
                handler: function () {
                    window.close();
                }
            }
        ]
    });
    window.show();
};

pimcore.helpers.generatePassword = function (len) {
    var length = (len) ? (len) : (10);
    var string = "abcdefghijklmnopqrstuvwxyz"; //to upper
    var numeric = '0123456789';
    var punctuation = '!@#$%^&*()_+~`|}{[]\:;?><,./-=';
    var password = "";
    var character = "";
    while (password.length < length) {
        entity1 = Math.ceil(string.length * Math.random() * Math.random());
        entity2 = Math.ceil(numeric.length * Math.random() * Math.random());
        entity3 = Math.ceil(punctuation.length * Math.random() * Math.random());
        hold = string.charAt(entity1);
        hold = (entity1 % 2 == 0) ? (hold.toUpperCase()) : (hold);
        character += hold;
        character += numeric.charAt(entity2);
        character += punctuation.charAt(entity3);
        password = character;
    }
    return password;
};

pimcore.helpers.isValidPassword = function (pass) {
    var passRegExp = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{10,}$/;
    if (!pass.match(passRegExp)) {
        return false;
    }
    return true;
};

pimcore.helpers.getDeeplink = function (type, id, subtype) {
    return window.location.protocol + "//"
        + window.location.hostname
        + (window.location.port && window.location.port !== "80" && window.location.port !== "443" ? ":" + window.location.port : "")
        + "/admin/login/deeplink?" + type + "_" + id + "_" + subtype;
};

pimcore.helpers.showElementHistory = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("objects") || user.isAllowed("documents") || user.isAllowed("assets")) {
        pimcore.layout.toolbar.prototype.showElementHistory();
    }
};

pimcore.helpers.closeAllTabs = function() {
    pimcore.helpers.closeAllElements();
    // clear the opentab store, so that also non existing elements are flushed
    pimcore.helpers.clearOpenTab();

};

pimcore.helpers.searchAndReplaceAssignments = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("objects") || user.isAllowed("documents") || user.isAllowed("assets")) {
        new pimcore.element.replace_assignments();
    }
};

pimcore.helpers.glossary = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("glossary")) {
        pimcore.layout.toolbar.prototype.editGlossary();
    }
};

pimcore.helpers.redirects = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("redirects")) {
        pimcore.layout.toolbar.prototype.editRedirects();
    }
};

pimcore.helpers.sharedTranslations = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("translations")) {
        pimcore.layout.toolbar.prototype.editTranslations();
    }
};

pimcore.helpers.recycleBin = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("recyclebin")) {
        pimcore.layout.toolbar.prototype.recyclebin();
    }
};

pimcore.helpers.notesEvents = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("notes_events")) {
        pimcore.layout.toolbar.prototype.notes();
    }
};

pimcore.helpers.applicationLogger = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("application_logging")) {
        pimcore.layout.toolbar.prototype.logAdmin();
    }
};

pimcore.helpers.reports = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("reports")) {
        pimcore.layout.toolbar.prototype.showReports(null);
    }
};

pimcore.helpers.tagManager = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("tag_snippet_management")) {
        pimcore.layout.toolbar.prototype.showTagManagement();
    }
};

pimcore.helpers.seoDocumentEditor = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("documents") && user.isAllowed("seo_document_editor")) {
        pimcore.layout.toolbar.prototype.showDocumentSeo();
    }
};

pimcore.helpers.robots = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("robots.txt")) {
        pimcore.layout.toolbar.prototype.showRobotsTxt();
    }
};

pimcore.helpers.httpErrorLog = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("http_errors")) {
        pimcore.layout.toolbar.prototype.showHttpErrorLog();
    }
};

pimcore.helpers.customReports = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("reports")) {
        pimcore.layout.toolbar.prototype.showCustomReports();
    }
};

pimcore.helpers.tagConfiguration = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("tags_configuration")) {
        pimcore.layout.toolbar.prototype.showTagConfiguration();
    }
};

pimcore.helpers.users = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("users")) {
        pimcore.layout.toolbar.prototype.editUsers();
    }
};

pimcore.helpers.roles = function() {
    var user = pimcore.globalmanager.get("user");
    if (user.isAllowed("users")) {
        pimcore.layout.toolbar.prototype.editRoles();
    }
};

pimcore.helpers.clearAllCaches = function() {
    var user = pimcore.globalmanager.get("user");
    if ((user.isAllowed("clear_cache") || user.isAllowed("clear_temp_files") || user.isAllowed("clear_fullpage_cache"))) {
        pimcore.layout.toolbar.prototype.clearCache({'env[]': ['dev','prod']});
    }
};

pimcore.helpers.clearDataCache = function() {
    var user = pimcore.globalmanager.get("user");
    if ((user.isAllowed("clear_cache") || user.isAllowed("clear_temp_files") || user.isAllowed("clear_fullpage_cache"))) {
        pimcore.layout.toolbar.prototype.clearCache({'only_pimcore_cache': true})
    }
};

pimcore.helpers.showQuickSearch = function () {

    // close all windows, tooltips and previews
    // we use each() because .hideAll() doesn't hide the modal (seems to be an ExtJS bug)
    Ext.WindowManager.each(function (win) {
        win.close();
    });
    pimcore.helpers.treeNodeThumbnailPreviewHide();
    jQuery("#pimcore_tooltip").hide();

    var quicksearchContainer = Ext.get('pimcore_quicksearch');
    quicksearchContainer.show();
    quicksearchContainer.removeCls('filled');

    var combo = Ext.getCmp('quickSearchCombo');
    combo.reset();
    combo.focus();

    Ext.get('pimcore_body').addCls('blurry');
    Ext.get('pimcore_sidebar').addCls('blurry');
};

pimcore.helpers.hideQuickSearch = function () {
    var quicksearchContainer = Ext.get('pimcore_quicksearch');
    quicksearchContainer.hide();
    Ext.get('pimcore_body').removeCls('blurry');
    Ext.get('pimcore_sidebar').removeCls('blurry');
};


// HAS TO BE THE VERY LAST ENTRY !!!
pimcore.helpers.keyBindingMapping = {
    "save": pimcore.helpers.handleCtrlS,
    "publish": pimcore.helpers.togglePublish.bind(this, true),
    "unpublish": pimcore.helpers.togglePublish.bind(this, false),
    "rename": pimcore.helpers.rename.bind(this),
    "refresh": pimcore.helpers.handleF5,
    "openDocument": pimcore.helpers.openElementByIdDialog.bind(this, "document"),
    "openAsset": pimcore.helpers.openElementByIdDialog.bind(this, "asset"),
    "openObject": pimcore.helpers.openElementByIdDialog.bind(this, "object"),
    "openClassEditor": pimcore.helpers.openClassEditor,
    "openInTree": pimcore.helpers.openInTree,
    "showMetaInfo": pimcore.helpers.showMetaInfo,
    "searchDocument": pimcore.helpers.searchAction.bind(this, "document"),
    "searchAsset": pimcore.helpers.searchAction.bind(this, "asset"),
    "searchObject": pimcore.helpers.searchAction.bind(this, "object"),
    "showElementHistory": pimcore.helpers.showElementHistory,
    "closeAllTabs": pimcore.helpers.closeAllTabs,
    "searchAndReplaceAssignments": pimcore.helpers.searchAndReplaceAssignments,
    "glossary": pimcore.helpers.glossary,
    "redirects": pimcore.helpers.redirects,
    "sharedTranslations": pimcore.helpers.sharedTranslations,
    "recycleBin": pimcore.helpers.recycleBin,
    "notesEvents": pimcore.helpers.notesEvents,
    "applicationLogger": pimcore.helpers.applicationLogger,
    "reports": pimcore.helpers.reports,
    "tagManager": pimcore.helpers.tagManager,
    "seoDocumentEditor": pimcore.helpers.seoDocumentEditor,
    "robots": pimcore.helpers.robots,
    "httpErrorLog": pimcore.helpers.httpErrorLog,
    "customReports": pimcore.helpers.customReports,
    "tagConfiguration": pimcore.helpers.tagConfiguration,
    "users": pimcore.helpers.users,
    "roles": pimcore.helpers.roles,
    "clearAllCaches": pimcore.helpers.clearAllCaches,
    "clearDataCache": pimcore.helpers.clearDataCache,
    "quickSearch": pimcore.helpers.showQuickSearch
};

pimcore.helpers.showPermissionError = function(permission) {
    Ext.MessageBox.alert(t("error"), sprintf(t('permission_missing'), ts(permission)));
};

pimcore.helpers.registerAssetDnDSingleUpload = function (element, parent, parentType, success, failure, context) {

    if (typeof success != "function") {
        success = function () {
        };
    }

    if (typeof failure != "function") {
        failure = function () {
        };
    }

    var fn = function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        return false;
    };

    element.addEventListener("dragenter", fn, true);
    element.addEventListener("dragover", fn, true);
    element.addEventListener("drop", function (e) {

        e.stopPropagation();
        e.preventDefault();

        var dataTransfer = e.dataTransfer;

        var win = new Ext.Window({
            items: [],
            modal: true,
            closable: false,
            bodyStyle: "padding:10px;",
            width: 500,
            autoHeight: true,
            autoScroll: true
        });
        win.show();

        if(dataTransfer["files"]) {
            if(dataTransfer["files"][0]) {
                var file = dataTransfer["files"][0];

                if (window.FileList && file.name && file.size) { // check for size (folder has size=0)
                    var pbar = new Ext.ProgressBar({
                        width:465,
                        text: file.name,
                        style: "margin-bottom: 5px"
                    });

                    win.add(pbar);
                    win.updateLayout();

                    var uploadUrl = "/admin/asset/add-asset?";
                    if(parentType === 'path') {
                        uploadUrl += "parentPath=" + parent;
                    } else if (parentType === 'id') {
                        uploadUrl += "parentId=" + parent;
                    }

                    if (context) {
                        uploadUrl += "&context=" + Ext.encode(context);
                    }

                    pimcore.helpers.uploadAssetFromFileObject(file, uploadUrl,
                        function (evt) {
                            // success
                            win.close();
                            success(evt);
                        },
                        function (evt) {
                            //progress
                            if (evt.lengthComputable) {
                                var percentComplete = evt.loaded / evt.total;
                                var progressText = file.name + " ( " + Math.floor(percentComplete*100) + "% )";
                                if(percentComplete == 1) {
                                    progressText = file.name + " " + t("please_wait");
                                }

                                pbar.updateProgress(percentComplete, progressText);
                            }
                        },
                        function (evt) {
                            // error
                            pimcore.helpers.showNotification(t("error"), e["responseText"], "error");
                            win.close();
                            failure(evt);
                        }
                    );

                } else if (!empty(file.type) && file.size < 1) { //throw error for 0 byte file
                    Ext.MessageBox.alert(t('error'), t('error_empty_file_upload'));
                    win.close();
                } else {
                    Ext.MessageBox.alert(t('error'), t('unsupported_filetype'));
                    win.close();
                }
            } else {
                // if no files are uploaded (doesn't match criteria, ...) close the progress win immediately
                win.close();
            }
        }
    }.bind(this), true);
};

pimcore.helpers.dragAndDropValidateSingleItem = function (data) {
    if(data.records.length > 1) {
        Ext.MessageBox.alert(t('error'), t('you_can_only_drop_one_element_here'));
        return false;
    }

    return true;
};




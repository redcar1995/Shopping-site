<?php
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

namespace Pimcore\Event;

final class AdminEvents
{
    /**
     * The LOGIN_CREDENTIALS event is triggered after login credentials were resolved from request.
     *
     * This event allows you to influence the credentials resolved in the authenticator before
     * they are passed to getUser().
     *
     * @Event("Pimcore\Event\Admin\Login\LoginCredentialsEvent")
     *
     * @var string
     */
    const LOGIN_CREDENTIALS = 'pimcore.admin.login.credentials';

    /**
     * The LOGIN_FAILED event is triggered when credentials were invalid.
     *
     * This event allows you to set a custom user which is resolved from the given credentials
     * from a third-party authentication system (e.g. an external service).
     *
     * @Event("Pimcore\Event\Admin\Login\LoginFailedEvent")
     *
     * @var string
     */
    const LOGIN_FAILED = 'pimcore.admin.login.failed';

    /**
     * The LOGIN_LOSTPASSWORD event is triggered before the lost password email
     * is sent.
     *
     * This event allows you to alter the lost password mail or to prevent
     * mail sending at all. For full control, it allows you to set the response
     * to be returned.
     *
     * @Event("Pimcore\Event\Admin\Login\LostPasswordEvent")
     *
     * @var string
     */
    const LOGIN_LOSTPASSWORD = 'pimcore.admin.login.lostpassword';

    /**
     * The LOGIN_LOGOUT event is triggered before the user is logged out.
     *
     * By setting a response on the event, you're able to control the response
     * returned after logout.
     *
     * @Event("Pimcore\Event\Admin\Login\LogoutEvent")
     *
     * @var string
     */
    const LOGIN_LOGOUT = 'pimcore.admin.login.logout';

    /**
     * The INDEX_SETTINGS event is triggered when the settings object is built for the index page.
     *
     * @Event("Pimcore\Event\Admin\IndexSettingsEvent")
     *
     * @var string
     */
    const INDEX_SETTINGS = 'pimcore.admin.index.settings';

    /**
     * Fired before the request params are parsed.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Searchadmin\SearchController
     * Arguments:
     *  - requestParams | contains the request parameters
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const SEARCH_LIST_BEFORE_FILTER_PREPARE = 'pimcore.admin.search.list.beforeFilterPrepare';

    /**
     * Allows you to modify the search backend list before it is loaded.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Searchadmin\SearchController
     * Arguments:
     *  - list | the search backend list
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const SEARCH_LIST_BEFORE_LIST_LOAD = 'pimcore.admin.search.list.beforeListLoad';

    /**
     * Allows you to modify the the result after the list was loaded.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Searchadmin\SearchController
     * Arguments:
     *  - list | raw result as an array
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const SEARCH_LIST_AFTER_LIST_LOAD = 'pimcore.admin.search.list.afterListLoad';

    /**
     * Fired before the request params are parsed. This event apply to the grid list.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - requestParams | contains the request parameters
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const OBJECT_LIST_BEFORE_FILTER_PREPARE = 'pimcore.admin.object.list.beforeFilterPrepare';

    /**
     * Allows you to modify the object list before it is loaded. This is a global event (search list, grid list, tree list, ...).
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | the object list
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const OBJECT_LIST_BEFORE_LIST_LOAD = 'pimcore.admin.object.list.beforeListLoad';

    /**
     * Allows you to modify the the result after the list was loaded. This event apply to the grid list.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | raw result as an array
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const OBJECT_LIST_AFTER_LIST_LOAD = 'pimcore.admin.object.list.afterListLoad';

    /**
     * Fired before the request params are parsed. This event apply to both the folder content preview list and the grid list.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - requestParams | contains the request parameters
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const ASSET_LIST_BEFORE_FILTER_PREPARE = 'pimcore.admin.asset.list.beforeFilterPrepare';

    /**
     * Allows you to modify the asset list before it is loaded. This is a global event (folder content preview list, grid list, tree list, ...).
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | the object list
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const ASSET_LIST_BEFORE_LIST_LOAD = 'pimcore.admin.asset.list.beforeListLoad';

    /**
     * Allows you to modify the the result after the list was loaded. This event apply to both the folder content preview list and the grid list.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | raw result as an array
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const ASSET_LIST_AFTER_LIST_LOAD = 'pimcore.admin.asset.list.afterListLoad';

    /**
     * Fired before the request params are parsed. This event apply to the seo panel tree.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - requestParams | contains the request parameters
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const DOCUMENT_LIST_BEFORE_FILTER_PREPARE = 'pimcore.admin.document.list.beforeFilterPrepare';

    /**
     * Allows you to modify the document list before it is loaded. This is a global event (seo panel tree, tree list, ...).
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | the object list
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const DOCUMENT_LIST_BEFORE_LIST_LOAD = 'pimcore.admin.document.list.beforeListLoad';

    /**
     * Allows you to modify the the result after the list was loaded. This event apply to the seo panel tree.
     *
     * Subject: A controller extending \Pimcore\Bundle\AdminBundle\Controller\AdminController
     * Arguments:
     *  - list | raw result as an array
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const DOCUMENT_LIST_AFTER_LIST_LOAD = 'pimcore.admin.document.list.afterListLoad';

    /**
     * Fired before the request params are parsed.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\AssetController
     * Arguments:
     *  - data | array | the response data, this can be modified
     *  - asset | Asset | the current asset
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const ASSET_GET_PRE_SEND_DATA = 'pimcore.admin.asset.get.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\AssetController
     * Arguments:
     *  - assets | array | the list of asset tree nodes
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const ASSET_TREE_GET_CHILDREN_BY_ID_PRE_SEND_DATA = 'pimcore.admin.asset.treeGetChildsById.preSendData';

    /**
     * Fired before the request params are parsed.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\ElementControllerBase
     * Arguments:
     *  - data | array | the response data, this can be modified
     *  - document | Document | the current document
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const DOCUMENT_GET_PRE_SEND_DATA = 'pimcore.admin.document.get.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\DocumentController
     * Arguments:
     *  - documents | array | the list of document tree nodes
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const DOCUMENT_TREE_GET_CHILDREN_BY_ID_PRE_SEND_DATA = 'pimcore.admin.document.treeGetChildsById.preSendData';

    /**
     * Fired before the request params are parsed.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\DataObjectController
     * Arguments:
     *  - data | array | the response data, this can be modified
     *  - object | AbstractObject | the current object
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const OBJECT_GET_PRE_SEND_DATA = 'pimcore.admin.dataobject.get.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\DataObjectController
     * Arguments:
     *  - objects | array | the list of object tree nodes
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const OBJECT_TREE_GET_CHILDREN_BY_ID_PRE_SEND_DATA = 'pimcore.admin.dataobject.treeGetChildsById.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\ClassController
     * Arguments:
     *  - list | array | the list of field collections
     *  - objectId | int | id of the origin object
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const CLASS_FIELDCOLLECTION_LIST_PRE_SEND_DATA = 'pimcore.admin.class.fieldcollectionList.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\ClassController
     * Arguments:
     *  - icons | array | the list of selectable icons
     *  - classId | string | classid of class definition
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const CLASS_OBJECT_ICONS_PRE_SEND_DATA = 'pimcore.admin.class.dataobject.preSendData';

    /**
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\ClassController
     * Arguments:
     *  - list | array | the list of object bricks
     *  - objectId | int | id of the origin object
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const CLASS_OBJECTBRICK_LIST_PRE_SEND_DATA = 'pimcore.admin.class.objectbrickList.preSendData';

    /**
     * Allows you to modify the search backend list before it is loaded.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Searchadmin\SearchController
     * Arguments:
     *  - list | the search backend list
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const QUICKSEARCH_LIST_BEFORE_LIST_LOAD = 'pimcore.admin.quickSearch.list.beforeListLoad';

    /**
     * Allows you to modify the the result after the list was loaded.
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Searchadmin\SearchController
     * Arguments:
     *  - list | raw result as an array
     *  - context | contains contextual information
     *
     * @Event("Pimcore\Event\Model\GenericEvent")
     *
     * @var string
     */
    const QUICKSEARCH_LIST_AFTER_LIST_LOAD = 'pimcore.admin.quickSearch.list.afterListLoad';

    /**
     * Fired before the an element is opened
     *
     * Subject: \Pimcore\Bundle\AdminBundle\Controller\Admin\ElementController
     * Arguments:
     *  - type element type
     *  - id
     *
     * @Event("Pimcore\Event\Model\ResolveElementEvent")
     *
     * @var string
     */
    const RESOLVE_ELEMENT = 'pimcore.admin.resolve.element';
}

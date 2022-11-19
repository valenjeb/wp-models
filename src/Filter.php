<?php

declare(strict_types=1);

namespace Devly\WP\Models;

class Filter
{
    /* ---------------- User model filters ---------------- */

    /**
     * Runs before meta field retrieved.
     */
    public const USER_PRE_GET_META_FIELD = 'devly/models/user/pre_get_meta_field';

    /**
     * Runs after meta field retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const USER_GET_META_FIELD = 'devly/models/user/get_meta_field';

    /**
     * Runs before meta field is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const USER_PRE_SET_META_FIELD = 'devly/models/user/pre_set_meta_field';

    /**
     * Runs before meta field is deleted.
     */
    public const USER_PRE_DELETE_META_FIELD = 'devly/models/user/pre_delete_meta_field';

    /**
     * Runs before user option is retrieved from database.
     */
    public const USER_PRE_GET_OPTION = 'devly/models/user/pre_get_option';

    /**
     * Runs after user option is retrieved from database.
     *
     * should be prefixed with the actual option key name.
     */
    public const USER_GET_OPTION = 'devly/models/user/get_option';

    /**
     * Runs before user option is saved to the database.
     *
     * return `false` to skip process. Useful if the option updated using third party plugin (CMB2, ACF etc.)
     */
    public const USER_PRE_SET_OPTION = 'devly/models/user/pre_set_option';

    /**
     * Runs before user option is deleted.
     */
    public const USER_PRE_DELETE_OPTION = 'devly/models/user/pre_delete_option';

    /* ---------------- Post model filters ---------------- */

    /**
     * Runs before meta field retrieved.
     */
    public const POST_PRE_GET_META_FIELD = 'devly/models/post/pre_get_meta_field';

    /**
     * Runs after meta field retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const POST_GET_META_FIELD = 'devly/models/post/get_meta_field';

    /**
     * Runs before meta field is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const POST_PRE_SET_META_FIELD = 'devly/models/post/pre_set_meta_field';

    /**
     * Runs before meta field is deleted.
     */
    public const POST_PRE_DELETE_META_FIELD = 'devly/models/post/pre_delete_meta_field';

    /* ---------------- Term model filters ---------------- */

    /**
     * Runs before meta field retrieved from database.
     */
    public const TERM_PRE_GET_META_FIELD = 'devly/models/term/pre_get_meta_field';

    /**
     * Runs after meta field retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const TERM_GET_META_FIELD = 'devly/models/term/get_meta_field';

    /**
     * Runs before meta field is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const TERM_PRE_SET_META_FIELD = 'devly/models/term/pre_delete_meta_field';

    /**
     * Runs before meta field is deleted.
     */
    public const TERM_PRE_DELETE_META_FIELD = 'devly/models/term/pre_delete_meta_field';

    /* ---------------- Attachment model filters ---------------- */

    /**
     * Runs before meta field retrieved from database.
     */
    public const ATTACHMENT_PRE_GET_META_FIELD = 'devly/models/attachment/pre_get_meta_field';

    /**
     * Runs after meta field retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const ATTACHMENT_GET_META_FIELD = 'devly/models/attachment/get_meta_field';

    /**
     * Runs before meta field is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const ATTACHMENT_PRE_SET_META_FIELD = 'devly/models/attachment/pre_set_meta_field';

    /* ---------------- Theme model filters ---------------- */

    /**
     * Runs before option retrieved from database.
     */
    public const THEME_PRE_GET_OPTION = 'devly/models/theme/pre_get_option';

    /**
     * Runs after option retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const THEME_GET_OPTION = 'devly/models/theme/get_option';

    /**
     * Runs before option is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const THEME_PRE_SET_OPTION = 'devly/models/theme/pre_set_option';

    /**
     * Runs before option is deleted.
     */
    public const THEME_PRE_DELETE_OPTION = 'devly/models/theme/pre_delete_option';

    /* ---------------- Site model filters ---------------- */

    /**
     * Runs before option is saved to the database.
     *
     * return `false` to skip process. Useful if the field updated using third party plugin (CMB2, ACF etc.)
     */
    public const SITE_PRE_SET_OPTION = 'devly/models/site/pre_set_option';

    /**
     * Runs before option retrieved from database.
     */
    public const SITE_PRE_GET_OPTION = 'devly/models/site/pre_get_option';

    /**
     * Runs after option retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const SITE_GET_OPTION = 'devly/models/site/get_option';

    /**
     * Runs before option is deleted.
     */
    public const SITE_PRE_DELETE_OPTION = 'devly/models/site/pre_delete_option';

    /* ---------------- Network model filters ---------------- */

    /**
     * Runs before option retrieved from database.
     */
    public const NETWORK_PRE_GET_OPTION = 'devly/models/network/pre_get_option';

    /**
     * Runs after option retrieved from database.
     *
     * should be prefixed with the actual meta key name.
     */
    public const NETWORK_GET_OPTION     = 'devly/models/network/get_option';
    public const NETWORK_PRE_ADD_OPTION = 'devly/models/network/pre_add_option';

    /**
     * should be prefixed with the actual meta key name.
     */
    public const NETWORK_ADD_OPTION        = 'devly/models/network/add_option';
    public const NETWORK_PRE_UPDATE_OPTION = 'devly/models/network/pre_update_option';

    /**
     * should be prefixed with the actual meta key name.
     */
    public const NETWORK_UPDATE_OPTION  = 'devly/models/network/update_option';
    public const NETWORK_PRE_DELETE     = 'devly/models/network/pre_delete_option';
    public const NETWORK_PRE_SET_OPTION = 'devly/models/network/pre_set_option';
}

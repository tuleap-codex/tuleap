<?php
/**
 * Copyright (c) Enalean, 2014-Present. All rights reserved
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

use Tuleap\User\Account\AccountTabPresenterCollection;

require_once __DIR__ . '/../include/pre.php';

session_require(array('isloggedin'=>'1'));

$em = EventManager::instance();
$um = UserManager::instance();

$user = $um->getCurrentUser();

$can_change_password = true;
$can_change_realname = true;
$can_change_email    = true;
$extra_user_info     = array();

$em->processEvent(
    'display_change_password',
    array(
        'allow' => &$can_change_password
    )
);

$em->processEvent(
    'display_change_realname',
    array(
        'allow' => &$can_change_realname
    )
);

$em->processEvent(
    'display_change_email',
    array(
        'allow' => &$can_change_email
    )
);

$em->processEvent(
    'account_pi_entry',
    array(
        'user'      => $user,
        'user_info' => &$extra_user_info,
    )
);

$csrf = new CSRFSynchronizerToken('/account/index.php');

$user_helper_preferences = array(
    array(
        'preference_name'  => UserHelper::PREFERENCES_NAME_AND_LOGIN,
        'preference_label' => $Language->getText('account_options', 'tuleap_name_and_login'),
        'is_selected'      => (int) user_get_preference("username_display") === UserHelper::PREFERENCES_NAME_AND_LOGIN
    ),
    array(
        'preference_name'  => UserHelper::PREFERENCES_LOGIN_AND_NAME,
        'preference_label' => $Language->getText('account_options', 'tuleap_login_and_name'),
        'is_selected'      => (int) user_get_preference("username_display") === UserHelper::PREFERENCES_LOGIN_AND_NAME
    ),
    array(
        'preference_name'  => UserHelper::PREFERENCES_LOGIN,
        'preference_label' => $Language->getText('account_options', 'tuleap_login'),
        'is_selected'      => (int) user_get_preference("username_display") === UserHelper::PREFERENCES_LOGIN
    ),
    array(
        'preference_name'  => UserHelper::PREFERENCES_REAL_NAME,
        'preference_label' => $Language->getText('account_options', 'real_name'),
        'is_selected'      => (int) user_get_preference("username_display") === UserHelper::PREFERENCES_REAL_NAME
    )
);

$plugins_prefs = array();
$em->processEvent(
    'user_preferences_appearance',
    array('preferences' => &$plugins_prefs)
);

$all_csv_separator = array();

foreach (PFUser::$csv_separators as $separator) {
    $all_csv_separator[] = array(
        'separator_name'  => $separator,
        'separator_label' => $Language->getText('account_options', $separator),
        'is_selected'     => $separator === user_get_preference("user_csv_separator")
    );
}

$all_csv_dateformat = array();

foreach (PFUser::$csv_dateformats as $dateformat) {
    $all_csv_dateformat[] = array(
        'dateformat_name'  => $dateformat,
        'dateformat_label' => $Language->getText('account_preferences', $dateformat),
        'is_selected'      => $dateformat === user_get_preference("user_csv_dateformat")
    );
}

$user_access_info = $um->getUserAccessInfo($user);
if (! $user_access_info) {
    $user_access_info = array(
        'last_auth_success' => false,
        'last_auth_failure' => false,
        'nb_auth_failure'   => false,
        'prev_auth_success' => false,
    );
}

$user_default_format = user_get_preference('user_edition_default_format');

$default_formats = array(
    array(
        'label'    => $Language->getText('account_preferences', 'html_format'),
        'value'    => 'html',
        'selected' => $user_default_format === 'html'
    ),
    array(
        'label'    => $Language->getText('account_preferences', 'text_format'),
        'value'    => 'text',
        'selected' => ($user_default_format === false || $user_default_format === 'text')
    )
);

$tabs = $em->dispatch(new AccountTabPresenterCollection($user, '/account'));
assert($tabs instanceof AccountTabPresenterCollection);

$presenter = new User_PreferencesPresenter(
    $user,
    $can_change_realname,
    $can_change_email,
    $can_change_password,
    $extra_user_info,
    $user_access_info,
    $csrf,
    $user_helper_preferences,
    $plugins_prefs,
    $all_csv_separator,
    $all_csv_dateformat,
    $default_formats,
    $tabs,
);

$HTML->header(array(
    'title'      => $Language->getText('account_options', 'title'),
    'body_class' => array('account-maintenance')
    ));

$renderer = TemplateRendererFactory::build()->getRenderer(dirname(__FILE__).'/../../templates/user');
$renderer->renderToPage('account-maintenance', $presenter);

$HTML->footer(array());

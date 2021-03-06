<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Statistics;

class UsageProgressPresenter
{
    public $csv_export_button;
    public $usage_progress_help;

    public function __construct()
    {
        $this->usage_progress_label = $GLOBALS['Language']->getText('plugin_statistics', 'usage_progress_title');
        $this->csv_export_button    = $GLOBALS['Language']->getText('plugin_statistics', 'csv_export_button');
        $this->usage_progress_help  = $GLOBALS['Language']->getText('plugin_statistics', 'usage_progress_help');
    }
}

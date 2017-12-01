<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
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

namespace Tuleap\AgileDashboard\REST\v1\Kanban\TrackerReport;

use AgileDashboard_Kanban;
use Tracker_Report;

class TrackerReportUpdater
{
    /**
     * @var TrackerReportDao
     */
    private $dao;

    public function __construct(TrackerReportDao $dao)
    {
        $this->dao = $dao;
    }

    public function save(AgileDashboard_Kanban $kanban, array $tracker_report_ids)
    {
        $this->dao->save($kanban->getId(), $tracker_report_ids);
    }

    public function deleteAllForReport(Tracker_Report $report)
    {
        $this->dao->deleteAllForReport($report->getId());
    }
}

<?php
/**
 * Copyright (c) Enalean, 2013. All Rights Reserved.
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

class Tracker_Permission_ChainOfResponsibility_AllGroups extends Tracker_Permission_Command {

    public function execute(Codendi_Request $request, Tracker_Permission_PermissionSetter $permission_setter) {
        foreach($permission_setter->getAllGroupIds() as $ugroup_id) {
            if ($ugroup_id != Ugroup::ANONYMOUS && $ugroup_id != UGroup::REGISTERED) {
                $this->executeForGroup($permission_setter, $ugroup_id, $request->get(self::PERMISSION_PREFIX.$ugroup_id));
            }
        }

        $this->executeNextCommand($request, $permission_setter);
    }

    private function executeForGroup(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id, $permission_type) {
        switch($permission_type) {
            case Tracker::PERMISSION_ID_FULL:
                if ($this->canSetFull($permission_setter, $ugroup_id)) {
                    $permission_setter->revokeAll($ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_FULL, $ugroup_id);
                }
                break;

            case Tracker::PERMISSION_ID_ASSIGNEE:
                if ($this->canSetAssignee($permission_setter, $ugroup_id)) {
                    $permission_setter->revokeAll($ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_ASSIGNEE, $ugroup_id);
                }
                break;

            case Tracker::PERMISSION_ID_SUBMITTER:
                 if ($this->canSetSubmitter($permission_setter, $ugroup_id)) {
                    $permission_setter->revokeAll($ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_SUBMITTER, $ugroup_id);
                }
                break;

            case Tracker::PERMISSION_ID_ASSIGNEE_AND_SUBMITTER:
                if ($this->canSetSubmitterAndAssignee($permission_setter, $ugroup_id)) {
                    $permission_setter->revokeAll($ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_ASSIGNEE, $ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_SUBMITTER, $ugroup_id);
                }
                break;

            case Tracker::PERMISSION_ID_ADMIN:
                if (! $permission_setter->groupHasPermission(Tracker::PERMISSION_ADMIN, $ugroup_id)) {
                    $permission_setter->revokeAll($ugroup_id);
                    $permission_setter->grantAccess(Tracker::PERMISSION_ADMIN, $ugroup_id);
                }
                break;

            case Tracker::PERMISSION_ID_NONE:
                 $permission_setter->revokeAll($ugroup_id);
                break;
        }
    }

    private function canSetFull(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return ! $permission_setter->groupHasPermission(Tracker::PERMISSION_FULL, $ugroup_id) && $this->notAlreadySet($permission_setter, $ugroup_id);
    }

    private function canSetAssignee(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return $this->isNotAlreadyAssignee($permission_setter, $ugroup_id) && $this->notAlreadySet($permission_setter, $ugroup_id);
    }

    private function isNotAlreadyAssignee(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return ! $permission_setter->groupHasPermission(Tracker::PERMISSION_ASSIGNEE, $ugroup_id) ||
           ($permission_setter->groupHasPermission(Tracker::PERMISSION_ASSIGNEE, $ugroup_id) && $permission_setter->groupHasPermission(Tracker::PERMISSION_SUBMITTER, $ugroup_id));
    }

    private function canSetSubmitter(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return $this->isNotAlreadySubmitter($permission_setter, $ugroup_id) && $this->notAlreadySet($permission_setter, $ugroup_id);
    }

    private function isNotAlreadySubmitter(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return ! $permission_setter->groupHasPermission(Tracker::PERMISSION_SUBMITTER, $ugroup_id) ||
           ($permission_setter->groupHasPermission(Tracker::PERMISSION_ASSIGNEE, $ugroup_id) && $permission_setter->groupHasPermission(Tracker::PERMISSION_SUBMITTER, $ugroup_id));
    }

    private function canSetSubmitterAndAssignee(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return $this->isNotAlreadySubmitterAndAssignee($permission_setter, $ugroup_id) && $this->notAlreadySet($permission_setter, $ugroup_id);
    }

    private function isNotAlreadySubmitterAndAssignee(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        return ! ($permission_setter->groupHasPermission(Tracker::PERMISSION_SUBMITTER, $ugroup_id) && $permission_setter->groupHasPermission(Tracker::PERMISSION_ASSIGNEE, $ugroup_id));
    }

    private function notAlreadySet(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        if ($permission_setter->anonymousHaveFullAccess()) {
            $this->warnAnonymous($permission_setter, $ugroup_id);
            return false;
        } else if ($permission_setter->registeredHaveFullAccess()) {
            $this->warnRegistered($permission_setter, $ugroup_id);
            return false;
        }
        return true;
    }

    private function warnAnonymous(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        $GLOBALS['Response']->addFeedback(
            Feedback::WARN,
            $GLOBALS['Language']->getText(
                'tracker_admin_permissions',
                'tracker_ignore_g_anon_full',
                array(
                    $permission_setter->getUGroupName($ugroup_id),
                    $permission_setter->getUGroupName(UGroup::ANONYMOUS),
                )
            )
        );
    }

    private function warnRegistered(Tracker_Permission_PermissionSetter $permission_setter, $ugroup_id) {
        $GLOBALS['Response']->addFeedback(
            Feedback::WARN,
            $GLOBALS['Language']->getText(
                'tracker_admin_permissions',
                'tracker_ignore_g_regis_full',
                array(
                    $permission_setter->getUGroupName($ugroup_id),
                    $permission_setter->getUGroupName(UGroup::REGISTERED),
                )
            )
        );
    }
}

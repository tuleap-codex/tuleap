<?php
/**
 * Copyright (c) Enalean, 2014. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once 'MediawikiGroups.class.php';

/**
 * This class do the mapping between Tuleap And Mediawiki groups
 */
class MediawikiGroupsMapper {

    /** @var MediawikiDao */
    private $dao;

    public function __construct(MediawikiDao $dao) {
        $this->dao = $dao;
    }

    public function defineUserMediawikiGroups(PFUser $user, Group $project) {
        $mediawiki_groups = new MediawikiGroups($this->dao->getMediawikiGroupsForUser($user, $project));
        $this->addGroupsAccordingToMapping($mediawiki_groups, $user, $project);
        return $mediawiki_groups->getAddedRemoved();
    }

    private function addGroupsAccordingToMapping(MediawikiGroups $mediawiki_groups, PFUser $user, Group $project) {
        $mediawiki_groups->add('*');
        if (! $user->isAnonymous()) {
            $dar = $this->dao->getMediawikiGroupsMappedForUGroups($user, $project);
            foreach ($dar as $row) {
                $mediawiki_groups->add($row['real_name']);
            }
        }
    }
}

<?php
/**
 * Copyright Zikula Foundation 2014 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license MIT
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Zikula_View|Smarty template plugin
 * Convert the state integer to a string giving more information (e.g. "Do not download - development build!").
 *
 *
 * Example
 *   {$var|elReleaseStateToAlert}
 *
 * @param $state
 * @return string The release state.
 */
function smarty_modifier_elReleaseStateToAlert($state)
{
    switch ($state) {
        case \Zikula\Module\CoreManagerModule\Entity\CoreReleaseEntity::STATE_SUPPORTED:
            return "";
        case \Zikula\Module\CoreManagerModule\Entity\CoreReleaseEntity::STATE_OUTDATED:
            return "<div class=\"alert alert-warning\"><i class='fa fa-ban fa-3x pull-left'></i> " . __("You are about to download an OUTDATED and no longer supported core version. It does not receive bug fixes or maintenance any longer. Please use one of the supported versions instead.") . "</div>";
        case \Zikula\Module\CoreManagerModule\Entity\CoreReleaseEntity::STATE_PRERELEASE:
            return "<div class=\"alert alert-danger\"><i class='fa fa-warning fa-3x pull-left'></i> " . __("This core version is a pre-release only. NEVER use it on production sites. If you like to help, we invite you to test this version and report bugs.") . "</div>";
        case \Zikula\Module\CoreManagerModule\Entity\CoreReleaseEntity::STATE_DEVELOPMENT:
            return "<div class=\"alert alert-danger\"><i class='fa fa-warning fa-3x pull-left'></i> " . __("DANGER: This is an in-development build. NEVER use it on production sites. It can likely be broken and absolutely not working. Really.") . "</div>";
    }
}

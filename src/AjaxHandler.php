<?php

namespace ISXF;

use ISXF\Ajax\AbstractAjaxController;
use ISXF\Ajax\AnalyticsController;
use ISXF\Ajax\EmailToolsController;
use ISXF\Ajax\EntriesController;
use ISXF\Ajax\SettingsController;
use ISXF\Ajax\SubmissionController;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX facade (Phase 2.4 — see docs/ROADMAP.md).
 *
 * The former monolithic handler is split into focused controllers under
 * ISXF\Ajax\ (src/Ajax/): Submission, Settings, Entries, Analytics and
 * EmailTools.
 * This class stays as the slim coordinator that isxf_bootstrap() instantiates:
 * constructing it registers every wp_ajax hook with the same names and in the
 * same request lifecycle as before. It also keeps the ISXF_AJAX_Handler alias
 * (and the shared base methods, via inheritance) working for any custom code.
 * No behavior change.
 */
class AjaxHandler extends AbstractAjaxController {

    public function __construct() {
        new SubmissionController();
        new SettingsController();
        new EntriesController();
        new AnalyticsController();
        new EmailToolsController();
    }
}

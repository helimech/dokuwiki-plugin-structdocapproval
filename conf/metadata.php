<?php
$meta['email_enable'] = ['onoff'];
$meta['review_queue_cc'] = ['string'];
$meta['publish_notify'] = ['string'];
$meta['compact_view'] = ['onoff'];
$meta['minor_edit_preserve_published'] = ['onoff'];
$meta['admin_override_enable'] = ['onoff'];
$meta['ack_due_days'] = ['numeric', '_min' => 0];

$meta['ack_report_visibility'] = ['multichoice', '_choices' => ['off','admins','managers','publishers','workflow','authenticated']];
$meta['ack_report_content'] = ['multichoice', '_choices' => ['acknowledged','pending','both']];

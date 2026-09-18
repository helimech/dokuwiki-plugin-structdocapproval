<?php

namespace dokuwiki\plugin\structdocapproval\meta;

class Constants
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_AWAITING_TRAINING = 'awaiting_training';
    public const STATUS_READY_TO_PUBLISH = 'ready_to_publish';
    public const STATUS_PUBLISHED = 'published';

    public const ACTION_EDIT = 'edit';
    public const ACTION_MINOR_EDIT = 'minor_edit';
    public const ACTION_INITIALIZE = 'initialize';
    public const ACTION_READY_FOR_REVIEW = 'ready_for_review';
    public const ACTION_REVIEW_APPROVED = 'review_approved';
    public const ACTION_REVIEW_RETURNED = 'review_returned';
    public const ACTION_TRAINING_REVIEWED = 'training_reviewed';
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_RETURN_TRAINING = 'return_training';
    public const ACTION_RETURN_DRAFT = 'return_draft';
    public const ACTION_ADMIN_OVERRIDE = 'admin_override';

    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_TRAINING = 'training';
    public const ROLE_PUBLISHER = 'publisher';

    public const TRAINING_NA = 'na';
    public const TRAINING_EXISTING = 'existing_adequate';
    public const TRAINING_UPDATE = 'update_existing';
    public const TRAINING_NEW = 'new_training';
    public const TRAINING_AWARENESS = 'awareness';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_AWAITING_REVIEW,
            self::STATUS_AWAITING_TRAINING,
            self::STATUS_READY_TO_PUBLISH,
            self::STATUS_PUBLISHED,
        ];
    }

    public static function trainingDispositions(): array
    {
        return [
            self::TRAINING_NA,
            self::TRAINING_EXISTING,
            self::TRAINING_UPDATE,
            self::TRAINING_NEW,
            self::TRAINING_AWARENESS,
        ];
    }

    public static function trainingNoteRequired(string $disposition): bool
    {
        return in_array($disposition, [
            self::TRAINING_UPDATE,
            self::TRAINING_NEW,
            self::TRAINING_AWARENESS,
        ], true);
    }
    /**
     * Display/store simple numeric document revisions as three digits.
     * Non-numeric version labels are left untouched.
     */
    public static function formatVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') return '';
        if (ctype_digit($version)) return str_pad((string)((int)$version), 3, '0', STR_PAD_LEFT);
        return $version;
    }

    /** Suggest the next document revision while preserving non-simple version formats. */
    public static function nextVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') return '001';
        if (ctype_digit($version)) return self::formatVersion((string)((int)$version + 1));

        $parts = explode('.', $version);
        $last = array_pop($parts);
        if (is_numeric($last)) $last = (string)((int)$last + 1);
        $parts[] = $last;
        return implode('.', $parts);
    }

}

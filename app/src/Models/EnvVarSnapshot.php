<?php

namespace SilverStripe\Bambusa\Models;

use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ValidationException;

/**
 * The component handling environment variable snapshots, solving our particular use case which is
 * distribution of database snapshots across kubernetes pods that don't have environment variables
 * defined on the original environment (where the database snapshot gets taken).
 *
 * This functionality lets us control environment variables in a centralised way
 * through SilverStripe Platform UI and then distribute them across kubernetes cluster through the database
 * snapshots.
 *
 * How it works:
 *  - On /dev/build it persists into the database all environment variables starting with SS_*
 *  - If you override variable with an empty string, then it deletes it from the database
 */
class EnvVarSnapshot extends DataObject
{
    private static $table_name = 'EnvVarSnapshot';

    private static $db = [
        'key' => 'Varchar(255)',
        'val' => 'Varchar(255)'
    ];

    private static $indexes = [
        'key' => [
            'type' => 'unique'
        ]
    ];

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canView($member = null)
    {
        return false;
    }

    /**
     * Take a snapshot of the environment variables and persist it to the database
     *
     * {@inheritdoc}
     * @throws ValidationException
     */
    public function requireDefaultRecords()
    {
        foreach (array_merge($_ENV, $_SERVER, Environment::getVariables()['env']) as $key => $val) {
            if (substr($key, 0, 3) !== 'SS_') {
                continue;
            }

            $var = static::get()->filter(['key' => $key])->first();
            if ($var !== null) {
                if (strlen($val) === 0 || $val === null) {
                    $var->delete();
                    DB::alteration_message('Deleted environment variable ' . $key, 'deleted');
                    continue;
                }
                $var->val = $val;
                DB::alteration_message('Set environment variable ' . $key, 'changed');
                $var->write();
            } elseif (strlen($val) !== 0 && $val !== null) {
                $var = static::create(['key' => $key, 'val' => $val]);
                DB::alteration_message('Created environment variable ' . $key, 'created');
                $var->write();
            }
        }

        return parent::requireDefaultRecords();
    }
}

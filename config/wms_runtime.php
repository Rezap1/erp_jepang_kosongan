<?php

return [
    // Runtime follows the explicitly configured application connection. The
    // M1 importer remains isolated on the `wms_migration` connection.
    'connection' => env('WMS_RUNTIME_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'database' => env('WMS_RUNTIME_DB_DATABASE', 'wms_mysql'),
    'sequence_table' => 'WMS_ID_SEQUENCE',
    'allow_test_truncate' => env('WMS_ALLOW_TEST_TRUNCATE', false),
    'allow_missing_test_schema' => true,

    // Columns required by the active attendance workflows that were not
    // present in the historical Sheets header. They are additive runtime
    // metadata and are deliberately kept out of the M1 source manifest.
    'runtime_columns' => [
        'MASTER_ANNOUNCEMENT' => [
            'Audience_Type',
            'Target_Batch_ID',
            'Target_Class_ID',
        ],
        'MASTER_ATTENDANCE' => [
            'Employee_ID',
            'User_ID',
            'Batch_ID',
            'Session_ID',
            'Late_Minutes',
            'Verification_Method',
            'Device_Info',
        ],
    ],
];

<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}

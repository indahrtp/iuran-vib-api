<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;

class ChartOfAccountController extends MasterController
{
    protected string $model = ChartOfAccount::class;
}

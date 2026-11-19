<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Village;

class VillageController extends MasterController
{
    protected string $model = Village::class;
}

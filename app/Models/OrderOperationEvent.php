<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['order_id', 'actor_id', 'actor_name_snapshot', 'status', 'note'])]
class OrderOperationEvent extends Model {}

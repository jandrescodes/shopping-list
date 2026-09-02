<?php

namespace App\Http\Requests;

/**
 * Renaming a list (RF-7) uses the same validation rules as creating one (RF-2).
 */
class UpdateListRequest extends StoreListRequest {}

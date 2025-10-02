<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(title="TERRA APIs ", version="1.0.0"),
 *
 * @OA\SecurityScheme(
 *       securityScheme="bearerAuth",
 *       in="header",
 *       name="Authorization",
 *       type="http",
 *       scheme="Bearer"
 *  ),
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}

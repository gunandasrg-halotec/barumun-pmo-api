<?php

namespace App\Core;

use App;
use App\Enums\DeliveryProcessLogEnum;
use Context;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions as IlluminateExcptions;
use Laravel\Pail\ValueObjects\Origin\Console;
use Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\Access\AuthorizationException;
class ExceptionResponse
{
    public static function setReponse(IlluminateExcptions $exceptions)
    {
        

        

        $exceptions->render(function (ValidationException $validationException, Request $request) {
            Context::add("type", $validationException::class);
            $rvalue = [];
            $rvalue["invalid_fields"] = $validationException->errors();
            $rvalue["message"] = $validationException->getMessage();
            $rvalue["type"] = "ValidationException";
            $rvalue["src"] = $request->url();

            return response()->json(
                $rvalue,
                $validationException->status
            );
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            Context::add("type", $e::class);
            $rvalue = [];
            $rvalue["message"] = $e->getMessage();
            $rvalue["type"] = "SystemError";
            $rvalue["src"] = $request->url();
            if ($previous = $e->getPrevious()) {
                Context::add("type", $previous::class);
                if (get_class($previous) == ModelNotFoundException::class) {
                    $rvalue["message"] = $previous->getMessage();
                    $rvalue["type"] = "ModelNotFoundException";
                }


                return response()->json($rvalue, $e->getStatusCode());
            }
            return response()->json($rvalue, $e->getStatusCode());
        });


        $exceptions->render(function (HttpException $e, Request $request) {
            Context::add("type", $e::class);
            $rvalue = [];
            $message = $e->getMessage();

            $rvalue["message"] = $message;
            $rvalue["type"] = "SystemError";
            if ($e->getStatusCode()==401){
                $rvalue["type"] = "Credential Errors";
            }
            if (json_validate($message) && !App::isProduction()) {
                $rvalue["json"] = json_decode($message, false);
            }

            $rvalue["src"] = $request->url();
            $statusCode = 500;
            if (method_exists($e, "getStatusCode")) {
                $statusCode = $e->getStatusCode();
            }
            if ($previous = $e->getPrevious()) {
                Context::add("type", $previous::class);
                if ($previous instanceof AuthorizationException) {
                    $statusCode = 403;
                    $rvalue["type"]="Credential Errors";
                    return response()->json($rvalue, $statusCode);
                }

            }




            return response()->json($rvalue, $e->getStatusCode(), $e->getHeaders());
        });

        // Catch-all untuk \RuntimeException polos, dipakai luas di layer Service untuk
        // validasi business rule (mis. "Tanggal progress tidak boleh lebih awal...", "Only
        // DRAFT versions can be submitted", dll). HARUS didaftarkan TERAKHIR — Symfony's
        // HttpException (dan turunannya NotFoundHttpException) sebenarnya juga meng-extend
        // \RuntimeException, jadi handler ini hanya boleh menangkap sisa yang tidak match
        // renderer HTTP-exception yang lebih spesifik di atas. Tanpa handler ini, pesannya
        // disembunyikan Laravel jadi generic 500 "Server Error" di production.
        $exceptions->render(function (\RuntimeException $e, Request $request) {
            Context::add("type", $e::class);
            return response()->json([
                "message" => $e->getMessage(),
                "type"    => "BusinessRuleException",
                "src"     => $request->url(),
            ], 422);
        });
    }
}
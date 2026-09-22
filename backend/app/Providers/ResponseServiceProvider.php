<?php

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class ResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        Response::macro('apiSuccess',function($data,$message=null,int $code=200):JsonResponse{
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $data
            ],$code);
        });

        Response::macro('apiError',function(mixed $data,string $message,int $code,string $error):JsonResponse{
            return response()->json([
                'status' => false,
                'message' => $message,
                'data' => $data,
                'error' => $error
            ],$code);
        });
    }
}

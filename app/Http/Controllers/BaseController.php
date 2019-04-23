<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BaseController extends Controller
{
    public function sendResponse($message)
    {
    	$response = [
            'success' => true,
            'message' => $message,
        ];
        return response()->json($response, 200);
    }
    public function sendError($error)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        return response()->json($response);
    }
}

<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Payment;

class PaymentController extends BaseController
{
    public function mpesaRequest(Request $request)
    {
        $mpesa= new \Safaricom\Mpesa\Mpesa();
        $amount = $request->amount;
        $billingNumber = $request->billing_number;
        $request= $mpesa->STKPushSimulation(174379,
            env("MPESA_PASSKEY"), 
            env("MPESA_TRANSACTION_TYPE"), 
            $amount, 
            $billingNumber, 
            174379,
            $billingNumber, 
            env("MPESA_CALLBACK"),
            $billingNumber, 
            "Mpesa test 12", 
            "Testing test");
        $response = json_decode($request);

        if(array_key_exists('ResponseCode', $response))
        {
            if($response->ResponseCode == 0)
            {
                $this->savePayment($response);
                return $this->sendResponse($response->CustomerMessage);
            }
            else
            {
                return $this->sendError("Error with payment request");
            }
        }
        else
        {
            $error = $response->errorMessage;
            return $this->sendError($error);
        }
    }

    public function savePayment($resp)
    {
        $payment = new Payment();
        $payment->merchant_request_id = $resp->MerchantRequestID;
        $payment->checkout_request_id = $resp->CheckoutRequestID;
        $payment->save();
    }

    public function mpesaResponse()
    {
        if ($request->Body['stkCallback']['ResultCode'] == 0) 
        {
            $payment = Payment::where('merchant_request_id', $request->Body['stkCallback']['MerchantRequestID'])
                ->where('checkout_request_id', $request->Body['stkCallback']['CheckoutRequestID'])
                ->firstOrFail();
            $payment->amount = $request->Body['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
            $payment->mpesa_receipt_number = $request->Body['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
            $payment->mpesa_transaction_date = $request->Body['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
            $payment->phone_number = $request->Body['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
            return $this->sendResponse($request->Body['stkCallback']['ResultDesc']);
        } 
        else 
        {
            $payment = Payment::where('merchant_request_id', $request->Body['stkCallback']['MerchantRequestID'])
                ->where('checkout_request_id', $request->Body['stkCallback']['CheckoutRequestID'])
                ->firstOrFail();
            return $this->sendError("Error with payment request");
        }    
    }

    public function getPublicIP()
    {
        $ip = file_get_contents('https://api.ipify.org');
        return $this->sendResponse($ip);
    }
}

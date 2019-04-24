<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Payment;

class PaymentController extends BaseController
{
    public function paymentRequest(Request $request)
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
                $this->savePayment($response, $billingNumber);
                return $this->sendResponse($response);
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

    public function savePayment($resp, $phoneNumber)
    {
        $payment = new Payment();
        $payment->merchant_request_id = $resp->MerchantRequestID;
        $payment->checkout_request_id = $resp->CheckoutRequestID;
        $payment->phone_number = $phoneNumber;
        $payment->save();
    }

    public function paymentCallback(Request $request)
    {
        $merchant_request_id = $request->Body['stkCallback']['MerchantRequestID'];
        $checkout_request_id = $request->Body['stkCallback']['CheckoutRequestID'];

        if ($request->Body['stkCallback']['ResultCode'] == 0) 
        {            
            Payment::where('merchant_request_id', $merchant_request_id)
                ->where('checkout_request_id', $checkout_request_id)
                ->update([
                    'amount' => $request->Body['stkCallback']['CallbackMetadata']['Item'][0]['Value'],
                    'mpesa_receipt_number' => $request->Body['stkCallback']['CallbackMetadata']['Item'][1]['Value'],
                    'mpesa_transaction_date' => $request->Body['stkCallback']['CallbackMetadata']['Item'][3]['Value'],
                    'payment_status' => "sucess"
                    ]);
            $data = "Payment Success";
            return $this->sendResponse($data);
        } 
        else 
        {
            Payment::where('merchant_request_id', $merchant_request_id)
                ->where('checkout_request_id', $checkout_request_id)
                ->update([
                        'payment_status' => "failed",
                    ]);
            $data = "Payment Failed";
            return $this->sendResponse($data);
        }     
    }

    public function queryPayment (Request $request)
    {
        $merchant_request_id =  $request->merchant_request_id;
        $checkout_request_id = $request->checkout_request_id;
        return $this->sendResponse(Payment::where('checkout_request_id', $checkout_request_id)
            ->where('merchant_request_id', $merchant_request_id)    
            ->get());
    }
}

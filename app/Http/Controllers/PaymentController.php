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
                $this->savePayment($response);
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

    public function savePayment($resp)
    {
        $payment = new Payment();
        $payment->merchant_request_id = $resp->MerchantRequestID;
        $payment->checkout_request_id = $resp->CheckoutRequestID;
        $payment->save();
    }

    public function paymentCallback(Request $request)
    {
        if ($request->Body['stkCallback']['ResultCode'] == 0) 
        {
            $payment = Payment::where('merchant_request_id', $request->Body['stkCallback']['MerchantRequestID'])
                ->where('checkout_request_id', $request->Body['stkCallback']['CheckoutRequestID'])
                ->where('payment_status', null)
                ->firstOrFail();

            $payment->update([
                'mpesa_receipt_number' => $request->Body['stkCallback']['CallbackMetadata']['Item'][1]['Value'],
                'mpesa_transaction_date' => $request->Body['stkCallback']['CallbackMetadata']['Item'][3]['Value'],
                'payment_status' => 'success',
            ]);

            $data = ['message' => 'payment complete'];
            return $this->sendResponse($data);
        } 
        else 
        {
            $payment = Payment::where('merchant_request_id', $request->Body['stkCallback']['MerchantRequestID'])
                ->where('checkout_request_id', $request->Body['stkCallback']['CheckoutRequestID'])
                ->where('payment_status', 'failed')
                ->firstOrFail();

            $data = ['message' => 'payment error'];
            return $this->sendError($data);
        }
    }

    public function queryPayment(Request $request)
    {
        $merchant_request_id = $request->merchant_request_id;
        $checkout_request_id = $request->checkout_request_id;
        $payment = Payment::where('merchant_request_id', $merchant_request_id)
                ->where('checkout_request_id', $checkout_request_id);

        return $this->sendResponse($payment);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentManager $payments, string $gateway): Response
    {
        try {
            $payments->gateway($gateway)->handleWebhook($request);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response('OK');
    }
}

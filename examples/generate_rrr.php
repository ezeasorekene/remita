<?php
// Include the class. This should be done using PSR-4
include '../src/RemitaPayments.php';

// Select the class to use
use ezeasorekene\NGPaymentGateway\RemitaPayments;

// Instantiate the RemitaPayments class using the demo credentials
$remita = new RemitaPayments("DEMO");

// Set the amount
$remita->setAmount(20000);

// Set the parameters
$parameters =
  array
  (
    'serviceTypeId' => $remita->serviceTypeId,
    'amount' => $remita->amount,
    'orderId' => $remita->transaction_id,
    'payerName' => 'Ekene Ezeasor',
    'payerEmail' => 'ezeasorekene@gmail.com',
    'payerPhone' => '08063961963',
    'description' => 'Testing'
  );

// Generate RRR using the set parameters
$rrr = $remita->generateRRR($parameters);

// Output the result or error
if ($rrr) {
  print $rrr;
} else {
  print "RRR could not be generated.";
}

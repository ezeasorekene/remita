# Remita Payment API Class
This class can generate RRR, check transaction status either using RRR or transaction id. It can also save RRR details into the database and update them accordingly.

A dedicated page listens to payments

You can set a cron job to also fetch payment status and update them equally.

## Basic Usage
### Initiate the class
```
use ezeasorekene\NGPaymentGateway\RemitaPayments;

$demo = new RemitaPayments("DEMO");
$live = new RemitaPayments("LIVE",$merchantId,$apiKey,$serviceTypeId);
```
### Generate RRR
```
$demo->setAmount(20000);
$live->setAmount(20000);

$parameters =
  array
  (
    'serviceTypeId' => $this->serviceTypeId,
    'amount' => $this->amount,
    'orderId' => $this->transaction_id,
    'payerName' => $payerName,
    'payerEmail' => $payerEmail,
    'payerPhone' => $payerPhone,
    'description' => $paymentDescription
  );

$rrr = $demo->generateRRR($parameters)
$rrr = $live->generateRRR($parameters)
```
###### Generate RRR Response
```
RRR | false
```

### Check RRR Status
```
$status = $demo->checkRRRStatus($rrr)
$status = $live->checkRRRStatus($rrr)
```
###### Check RRR Status Response
```
true | false
```

Check out different examples [here](examples "RemitaPayments API Examples")

You can read more about the class [here](docs/api/index.html "RemitaPayments API Documentation")

### License
[GNU Public License](http://opensource.org/licenses/gpl-license.php)

### Copyright
Copyright (c) 2021, [Ekene Ezeasor](https://github.com/ezeasorekene/remita)
